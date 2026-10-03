<?php

namespace App\Services;

use App\Exceptions\ApiError;
use App\Models\Outlet;
use App\Models\Shift;
use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ShiftService
{
    public function open(array $authUser, string $openingCash, mixed $outletId = null): Shift
    {
        $role = $authUser['role'];
        $cashierId = (int) $authUser['id'];

        if ($role === 'superadmin') {
            if ($outletId === null || $outletId === '') {
                throw ApiError::make(422, 'OUTLET_REQUIRED', 'outlet_id wajib untuk superadmin.', [
                    'outlet_id' => ['outlet_id wajib diisi.'],
                ]);
            }
        } else {
            $outletId = $authUser['outlet_id'];
        }

        $outlet = Outlet::find($outletId);

        if (! $outlet || ! $outlet->is_active) {
            throw ApiError::make(422, 'OUTLET_INVALID', 'Outlet tidak valid atau nonaktif.');
        }

        try {
            return DB::transaction(function () use ($outlet, $cashierId, $openingCash) {
                $exists = Shift::where('cashier_id', $cashierId)
                    ->where('status', 'open')
                    ->lockForUpdate()
                    ->exists();

                if ($exists) {
                    throw ApiError::make(409, 'SHIFT_ALREADY_OPEN', 'Kasir masih memiliki shift yang terbuka.');
                }

                $now = now();

                return Shift::create([
                    'outlet_id' => $outlet->id,
                    'cashier_id' => $cashierId,
                    'business_date' => $now->toDateString(),
                    'opened_at' => $now,
                    'opening_cash' => Money::normalize($openingCash),
                    'status' => 'open',
                    'active_guard' => "cashier-{$cashierId}",
                ]);
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                throw ApiError::make(409, 'SHIFT_ALREADY_OPEN', 'Kasir masih memiliki shift yang terbuka.');
            }

            throw $e;
        }
    }

    public function current(int $cashierId): Shift
    {
        $shift = Shift::where('cashier_id', $cashierId)->where('status', 'open')->first();

        if (! $shift) {
            throw ApiError::make(404, 'SHIFT_NOT_FOUND', 'Tidak ada shift yang terbuka.');
        }

        return $shift;
    }

    public function findFor(array $authUser, int $id): Shift
    {
        $shift = Shift::find($id);

        if (! $shift) {
            throw ApiError::make(404, 'NOT_FOUND', 'Shift tidak ditemukan.');
        }

        $this->assertCanView($authUser, $shift);

        return $shift;
    }

    public function assertCanView(array $authUser, Shift $shift): void
    {
        $role = $authUser['role'];

        if ($role === 'superadmin') {
            return;
        }

        if ($role === 'kasir' && (int) $shift->cashier_id === (int) $authUser['id']) {
            return;
        }

        if ($role === 'supervisor_pos' && (int) $shift->outlet_id === (int) $authUser['outlet_id']) {
            return;
        }

        throw ApiError::make(403, 'FORBIDDEN', 'Akses ditolak untuk shift ini.');
    }

    /** Ringkasan realtime. Nol bila tabel transaksi belum ada (pre-Fase 5). */
    public function summary(Shift $shift): array
    {
        $zero = ['cash' => '0.00', 'debit' => '0.00', 'qris' => '0.00'];

        if (! Schema::hasTable('transactions')) {
            return [
                'paid_count' => 0,
                'void_count' => 0,
                'pending_count' => 0,
                'totals' => $zero,
                'expected_cash' => (string) $shift->opening_cash,
            ];
        }

        $rows = DB::table('transactions')
            ->selectRaw('status, payment_method, COUNT(*) c, COALESCE(SUM(grand_total),0) t')
            ->where('shift_id', $shift->id)
            ->groupBy('status', 'payment_method')
            ->get();

        $totals = $zero;
        $paid = $void = $pending = 0;

        foreach ($rows as $r) {
            if ($r->status === 'paid' && isset($totals[$r->payment_method])) {
                $totals[$r->payment_method] = Money::normalize($r->t);
                $paid += (int) $r->c;
            } elseif ($r->status === 'void') {
                $void += (int) $r->c;
            } elseif ($r->status === 'pending') {
                $pending += (int) $r->c;
            }
        }

        return [
            'paid_count' => $paid,
            'void_count' => $void,
            'pending_count' => $pending,
            'totals' => $totals,
            'expected_cash' => Money::add((string) $shift->opening_cash, $totals['cash']),
        ];
    }

    /**
     * Tutup shift: tolak bila ada pending, coba sekali resync yang belum synced,
     * tetap boleh tutup walau Finance gagal. Kembalikan [shift, unsynced_count].
     */
    public function close(array $authUser, Shift $shift, string $actualCash): array
    {
        $this->assertCanClose($authUser, $shift);

        // Resync di luar lock (HTTP) sebelum commit penutupan.
        $this->resyncShiftOnce($shift->id);

        $closed = DB::transaction(function () use ($shift, $actualCash) {
            $locked = Shift::where('id', $shift->id)->lockForUpdate()->first();

            if ($locked->status !== 'open') {
                throw ApiError::make(409, 'SHIFT_NOT_OPEN', 'Shift sudah ditutup.');
            }

            if (Schema::hasTable('transactions')) {
                // Kunci baris transaksi agar race dengan pay/void tertahan di sini.
                $ids = DB::table('transactions')->where('shift_id', $locked->id)->pluck('id');
                if ($ids->isNotEmpty()) {
                    DB::table('transactions')->whereIn('id', $ids)->lockForUpdate()->get();
                }

                $pending = DB::table('transactions')
                    ->where('shift_id', $locked->id)
                    ->where('status', 'pending')
                    ->exists();

                if ($pending) {
                    throw ApiError::make(409, 'PENDING_TRANSACTIONS', 'Masih ada transaksi pending pada shift ini.');
                }
            }

            $expected = $this->summary($locked)['expected_cash'];
            $actual = Money::normalize($actualCash);

            $locked->update([
                'status' => 'closed',
                'closed_at' => now(),
                'expected_cash' => $expected,
                'actual_cash' => $actual,
                'cash_variance' => Money::sub($actual, $expected),
                'active_guard' => null,
            ]);

            return $locked->fresh();
        });

        return ['shift' => $closed, 'unsynced_count' => $this->unsyncedCount($closed->id)];
    }

    public function unsyncedCount(int $shiftId): int
    {
        if (! Schema::hasTable('transactions')) {
            return 0;
        }

        return DB::table('transactions')
            ->where('shift_id', $shiftId)
            ->whereIn('status', ['paid', 'void'])
            ->where('finance_sync_status', '!=', 'synced')
            ->count();
    }

    private function resyncShiftOnce(int $shiftId): void
    {
        if (! Schema::hasTable('transactions')) {
            return;
        }

        $ids = DB::table('transactions')
            ->where('shift_id', $shiftId)
            ->whereIn('status', ['paid', 'void'])
            ->where('finance_sync_status', '!=', 'synced')
            ->pluck('id');

        $resync = app(TransactionService::class);

        foreach ($ids as $id) {
            $trx = Transaction::find($id);

            if ($trx) {
                try {
                    $resync->resync($trx);
                } catch (\Throwable) {
                    // Tutup shift tetap jalan; dihitung di unsynced_count.
                }
            }
        }
    }

    private function assertCanClose(array $authUser, Shift $shift): void
    {
        if ($shift->status !== 'open') {
            throw ApiError::make(409, 'SHIFT_NOT_OPEN', 'Shift sudah ditutup.');
        }

        $role = $authUser['role'];

        if ($role === 'superadmin') {
            return;
        }

        if ($role === 'kasir' && (int) $shift->cashier_id === (int) $authUser['id']) {
            return;
        }

        throw ApiError::make(403, 'FORBIDDEN', 'Akses ditolak untuk shift ini.');
    }
}
