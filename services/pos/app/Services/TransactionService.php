<?php

namespace App\Services;

use App\Exceptions\ApiError;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Shift;
use App\Models\Transaction;
use App\Models\TrxSequence;
use App\Services\Finance\FinanceSyncService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    public function __construct(private FinanceSyncService $sync) {}

    public function create(array $authUser, array $items, mixed $discount = '0'): Transaction
    {
        $shift = Shift::where('cashier_id', $authUser['id'])->where('status', 'open')->first();

        if (! $shift) {
            throw ApiError::make(409, 'NO_OPEN_SHIFT', 'Tidak ada shift yang terbuka.');
        }

        if (empty($items)) {
            throw ApiError::make(422, 'ITEMS_REQUIRED', 'Minimal satu item.', ['items' => ['Minimal satu item.']]);
        }

        $products = Product::whereIn('id', collect($items)->pluck('product_id'))->get()->keyBy('id');

        $lines = [];
        $subtotal = '0.00';

        foreach ($items as $i => $item) {
            $product = $products->get($item['product_id']);

            if (! $product) {
                throw ApiError::make(422, 'PRODUCT_NOT_FOUND', "Produk #{$item['product_id']} tidak ditemukan.");
            }

            if (! $product->is_active) {
                throw ApiError::make(422, 'PRODUCT_INACTIVE', "Produk {$product->sku} nonaktif.");
            }

            $qty = (int) $item['quantity'];

            if ($qty < 1) {
                throw ApiError::make(422, 'QTY_INVALID', 'Quantity minimal 1.');
            }

            $unit = (string) $product->price;
            $line = Money::mul($unit, (string) $qty);
            $subtotal = Money::add($subtotal, $line);

            $lines[] = [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'product_name' => $product->name,
                'quantity' => $qty,
                'unit_price' => $unit,
                'subtotal' => $line,
            ];
        }

        $discountAmount = Money::normalize($discount ?? '0');

        if (Money::cmp($discountAmount, '0.00') < 0 || Money::cmp($discountAmount, $subtotal) > 0) {
            throw ApiError::make(422, 'DISCOUNT_INVALID', 'Diskon harus 0 <= diskon <= subtotal.', [
                'discount_amount' => ['Diskon tidak valid.'],
            ]);
        }

        $rate = (string) env('TAX_RATE', '0.11');
        $taxable = Money::sub($subtotal, $discountAmount);
        $tax = Money::round(bcmul($taxable, $rate, 6));
        $grand = Money::add($taxable, $tax);

        return DB::transaction(function () use ($shift, $authUser, $lines, $subtotal, $discountAmount, $tax, $grand) {
            $outlet = Outlet::find($shift->outlet_id);
            $bizDate = $shift->business_date instanceof \DateTimeInterface
                ? $shift->business_date->format('Y-m-d')
                : substr((string) $shift->business_date, 0, 10);
            $trxNumber = $this->nextTrxNumber($outlet, $bizDate);

            $trx = Transaction::create([
                'trx_number' => $trxNumber,
                'outlet_id' => $shift->outlet_id,
                'outlet_code' => $outlet->code,
                'shift_id' => $shift->id,
                'cashier_id' => $authUser['id'],
                'business_date' => $shift->business_date,
                'status' => 'pending',
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount' => $tax,
                'grand_total' => $grand,
                'finance_sync_status' => 'none',
            ]);

            foreach ($lines as $line) {
                $trx->items()->create($line);
            }

            return $trx->fresh('items');
        });
    }

    public function pay(array $authUser, Transaction $trx, string $method, mixed $paidAmount): Transaction
    {
        if ($trx->status === 'paid') {
            throw ApiError::make(409, 'ALREADY_PAID', 'Transaksi sudah dibayar.');
        }

        if ($trx->status === 'void') {
            throw ApiError::make(409, 'TRANSACTION_VOID', 'Transaksi sudah di-void.');
        }

        if (($authUser['role'] ?? null) === 'kasir' && (int) $trx->cashier_id !== (int) $authUser['id']) {
            throw ApiError::make(403, 'FORBIDDEN', 'Akses ditolak untuk transaksi ini.');
        }

        $grand = (string) $trx->grand_total;
        $paid = Money::normalize($paidAmount);

        if ($method === 'cash') {
            if (Money::cmp($paid, $grand) < 0) {
                throw ApiError::make(422, 'INSUFFICIENT_CASH', 'Uang diterima kurang dari total.', [
                    'paid_amount' => ['Uang diterima kurang dari grand_total.'],
                ]);
            }
            $change = Money::sub($paid, $grand);
        } else {
            if (Money::cmp($paid, $grand) !== 0) {
                throw ApiError::make(422, 'AMOUNT_MISMATCH', 'Nominal harus sama dengan grand_total untuk non-tunai.', [
                    'paid_amount' => ['Nominal harus sama dengan grand_total.'],
                ]);
            }
            $change = '0.00';
        }

        DB::transaction(function () use ($trx, $method, $paid, $change) {
            $locked = Transaction::where('id', $trx->id)->lockForUpdate()->first();

            if ($locked->status !== 'pending') {
                throw ApiError::make(409, 'ALREADY_PAID', 'Transaksi sudah dibayar.');
            }

            $locked->update([
                'status' => 'paid',
                'payment_method' => $method,
                'paid_amount' => $paid,
                'change_amount' => $change,
                'paid_at' => now(),
            ]);
        });

        $trx = $trx->fresh('items');

        // Setelah commit: tandai pending, kirim, lalu synced/failed.
        // Kegagalan Finance tidak membatalkan pembayaran.
        $this->syncSale($trx);

        return $trx->fresh('items');
    }

    public function void(array $authUser, Transaction $trx, string $reason): Transaction
    {
        if ($trx->status !== 'paid') {
            throw ApiError::make(409, $trx->status === 'void' ? 'ALREADY_VOID' : 'TRANSACTION_NOT_PAID', 'Hanya transaksi paid yang bisa di-void.');
        }

        $shift = Shift::find($trx->shift_id);

        if (! $shift || $shift->status !== 'open') {
            throw ApiError::make(409, 'SHIFT_CLOSED', 'Shift transaksi sudah ditutup.');
        }

        if (($authUser['role'] ?? null) === 'supervisor_pos' && (int) $shift->outlet_id !== (int) $authUser['outlet_id']) {
            throw ApiError::make(403, 'FORBIDDEN', 'Akses ditolak untuk outlet ini.');
        }

        DB::transaction(function () use ($trx, $authUser, $reason) {
            $locked = Transaction::where('id', $trx->id)->lockForUpdate()->first();

            if ($locked->status !== 'paid') {
                throw ApiError::make(409, 'ALREADY_VOID', 'Transaksi tidak dalam status paid.');
            }

            $locked->update([
                'status' => 'void',
                'void_reason' => $reason,
                'voided_by' => $authUser['id'],
                'voided_at' => now(),
            ]);
        });

        $trx = $trx->fresh('items');
        $this->syncVoidOrdered($trx);

        return $trx->fresh('items');
    }

    public function resync(Transaction $trx): Transaction
    {
        if (! in_array($trx->status, ['paid', 'void'], true)) {
            throw ApiError::make(409, 'NOTHING_TO_RESYNC', 'Hanya transaksi paid/void yang bisa di-resync.');
        }

        if ($trx->finance_sync_status === 'synced') {
            throw ApiError::make(409, 'ALREADY_SYNCED', 'Transaksi sudah synced ke Finance.');
        }

        if ($trx->status === 'void') {
            $this->syncVoidOrdered($trx);
        } else {
            $this->syncSale($trx);
        }

        return $trx->fresh('items');
    }

    public function findFor(array $authUser, int $id): Transaction
    {
        $trx = Transaction::with('items')->find($id);

        if (! $trx) {
            throw ApiError::make(404, 'NOT_FOUND', 'Transaksi tidak ditemukan.');
        }

        $this->assertCanView($authUser, $trx);

        return $trx;
    }

    public function paginate(array $authUser, array $filters, int $perPage = 15)
    {
        $q = Transaction::query()->with('items')->orderByDesc('id');
        $this->applyScope($q, $authUser);

        foreach (['status', 'shift_id', 'payment_method', 'finance_sync_status'] as $f) {
            if (! empty($filters[$f])) {
                $q->where($f, $filters[$f]);
            }
        }

        if (($authUser['role'] ?? null) === 'superadmin' && ! empty($filters['outlet_id'])) {
            $q->where('outlet_id', $filters['outlet_id']);
        }

        if (! empty($filters['date_from'])) {
            $q->whereDate('business_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $q->whereDate('business_date', '<=', $filters['date_to']);
        }

        return $q->paginate($perPage);
    }

    private function applyScope($q, array $authUser): void
    {
        $role = $authUser['role'] ?? null;

        if ($role === 'kasir') {
            $q->where('cashier_id', $authUser['id']);
        } elseif ($role === 'supervisor_pos') {
            $q->where('outlet_id', $authUser['outlet_id']);
        }
    }

    private function assertCanView(array $authUser, Transaction $trx): void
    {
        $role = $authUser['role'] ?? null;

        if ($role === 'superadmin') {
            return;
        }

        if ($role === 'kasir' && (int) $trx->cashier_id === (int) $authUser['id']) {
            return;
        }

        if ($role === 'supervisor_pos' && (int) $trx->outlet_id === (int) $authUser['outlet_id']) {
            return;
        }

        throw ApiError::make(403, 'FORBIDDEN', 'Akses ditolak untuk transaksi ini.');
    }

    private function nextTrxNumber(Outlet $outlet, string $businessDate): string
    {
        $seq = TrxSequence::where('outlet_id', $outlet->id)
            ->where('business_date', $businessDate)
            ->lockForUpdate()
            ->first();

        if (! $seq) {
            $seq = TrxSequence::create([
                'outlet_id' => $outlet->id,
                'business_date' => $businessDate,
                'last_number' => 0,
            ]);
        }

        $seq->increment('last_number');
        $n = $seq->fresh()->last_number;
        $yyyymmdd = str_replace('-', '', $businessDate);

        return sprintf('TRX/%s/%s/%04d', $outlet->code, $yyyymmdd, $n);
    }

    private function syncSale(Transaction $trx): void
    {
        $trx->update(['finance_sync_status' => 'pending']);

        try {
            $result = $this->sync->postSale($trx->fresh());
        } catch (\Throwable $e) {
            $result = ['status' => 'failed', 'error' => substr($e->getMessage(), 0, 500)];
        }

        $this->markSync($trx, $result);
    }

    /** Void: kirim sale dulu (idempoten) lalu reversal agar Finance tidak 422. */
    private function syncVoidOrdered(Transaction $trx): void
    {
        $trx->update(['finance_sync_status' => 'pending']);

        try {
            $sale = $this->sync->postSale($trx->fresh());

            if (($sale['status'] ?? 'failed') !== 'synced') {
                $this->markSync($trx, $sale);

                return;
            }

            $result = $this->sync->postReversal($trx->fresh());
        } catch (\Throwable $e) {
            $result = ['status' => 'failed', 'error' => substr($e->getMessage(), 0, 500)];
        }

        $this->markSync($trx, $result);
    }

    private function markSync(Transaction $trx, array $result): void
    {
        if (($result['status'] ?? 'failed') === 'synced') {
            $trx->update(['finance_sync_status' => 'synced', 'finance_synced_at' => now(), 'finance_last_error' => null]);
        } else {
            $trx->update(['finance_sync_status' => 'failed', 'finance_last_error' => substr((string) ($result['error'] ?? 'unknown'), 0, 1000)]);
        }
    }
}
