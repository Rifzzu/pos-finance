<?php

namespace App\Services;

use App\Exceptions\ApiError;
use App\Models\EodReconciliation;
use App\Models\Posting;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class ReconciliationService
{
    public function __construct(private PosClient $pos) {}

    /** @return array{reconciliation: EodReconciliation, unsynced_count: int} */
    public function run(array $authUser, int $outletId, string $date): array
    {
        return DB::transaction(function () use ($authUser, $outletId, $date) {
            $row = EodReconciliation::where('outlet_id', $outletId)
                ->where('business_date', $date)
                ->lockForUpdate()
                ->first();

            if ($row && in_array($row->status, ['matched', 'resolved'], true)) {
                throw ApiError::make(409, 'ALREADY_RECONCILED', 'Rekonsiliasi sudah final.');
            }

            $summary = $this->pos->eodSummary($outletId, $date);

            if (($summary['shift_count'] ?? 0) === 0) {
                throw ApiError::make(422, 'NO_SHIFTS', 'Tidak ada shift pada tanggal tersebut.');
            }

            if (! ($summary['all_shifts_closed'] ?? false)) {
                throw ApiError::make(409, 'SHIFTS_STILL_OPEN', 'Masih ada shift yang terbuka.');
            }

            $fin = $this->ledgerTotals($outletId, $date);
            $posTotals = $summary['totals'];
            $reasons = [];

            foreach (['cash', 'debit', 'qris'] as $method) {
                $diff = Money::sub((string) $posTotals[$method], (string) $fin['totals'][$method]);

                if (Money::cmp($diff, '0.00') !== 0) {
                    $reasons[] = [
                        'type' => 'AMOUNT_DIFF',
                        'method' => $method,
                        'pos' => (string) $posTotals[$method],
                        'finance' => (string) $fin['totals'][$method],
                    ];
                }
            }

            $posCount = (int) $summary['trx_count'];

            if ($posCount !== $fin['trx_count']) {
                $reasons[] = ['type' => 'COUNT_DIFF', 'pos' => $posCount, 'finance' => $fin['trx_count']];
            }

            $variance = Money::normalize($summary['cash_variance'] ?? '0.00');

            if (Money::cmp($variance, '0.00') !== 0) {
                $reasons[] = ['type' => 'CASH_VARIANCE', 'amount' => $variance];
            }

            $status = empty($reasons) ? 'matched' : 'mismatch';

            if (! $row) {
                $row = new EodReconciliation([
                    'outlet_id' => $outletId,
                    'business_date' => $date,
                ]);
            }

            $row->fill([
                'outlet_code' => $this->outletCode($outletId, $date),
                'status' => $status,
                'pos_cash' => $posTotals['cash'],
                'pos_debit' => $posTotals['debit'],
                'pos_qris' => $posTotals['qris'],
                'fin_cash' => $fin['totals']['cash'],
                'fin_debit' => $fin['totals']['debit'],
                'fin_qris' => $fin['totals']['qris'],
                'pos_trx_count' => $posCount,
                'fin_trx_count' => $fin['trx_count'],
                'cash_variance' => $variance,
                'mismatch_reasons' => empty($reasons) ? null : $reasons,
                'run_by' => $authUser['id'],
                'run_at' => now(),
            ]);
            $row->save();

            return ['reconciliation' => $row->fresh(), 'unsynced_count' => (int) ($summary['unsynced_count'] ?? 0)];
        });
    }

    public function resolve(array $authUser, EodReconciliation $row, string $note): EodReconciliation
    {
        if ($row->status !== 'mismatch') {
            throw ApiError::make(409, 'NOT_MISMATCH', 'Hanya status mismatch yang bisa di-resolve.');
        }

        $row->update([
            'status' => 'resolved',
            'resolved_by' => $authUser['id'],
            'resolved_at' => now(),
            'resolution_note' => $note,
        ]);

        return $row->fresh();
    }

    private function ledgerTotals(int $outletId, string $date): array
    {
        $rows = Posting::selectRaw('payment_method, entry_type, COUNT(*) c, COALESCE(SUM(total_amount),0) t')
            ->where('outlet_id', $outletId)
            ->whereDate('business_date', $date)
            ->groupBy('payment_method', 'entry_type')
            ->get();

        $totals = ['cash' => '0.00', 'debit' => '0.00', 'qris' => '0.00'];
        $count = 0;

        foreach ($rows as $r) {
            $sign = $r->entry_type === 'sale' ? 1 : -1;

            if (isset($totals[$r->payment_method])) {
                $totals[$r->payment_method] = $sign === 1
                    ? Money::add($totals[$r->payment_method], (string) $r->t)
                    : Money::sub($totals[$r->payment_method], (string) $r->t);
            }

            $count += $sign * (int) $r->c;
        }

        return ['totals' => $totals, 'trx_count' => $count];
    }

    private function outletCode(int $outletId, string $date): ?string
    {
        return Posting::where('outlet_id', $outletId)
            ->whereDate('business_date', $date)
            ->orderByDesc('id')
            ->value('outlet_code');
    }
}
