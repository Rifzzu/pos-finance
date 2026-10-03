<?php

namespace App\Services;

use App\Exceptions\ApiError;
use App\Models\Posting;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class PostingService
{
    public const DEBIT_ACCOUNTS = ['cash' => '1101', 'debit' => '1102', 'qris' => '1103'];

    /** @return array{posting: Posting, created: bool} */
    public function record(array $data): array
    {
        $net = Money::normalize($data['net_sales_amount']);
        $tax = Money::normalize($data['tax_amount']);
        $total = Money::normalize($data['total_amount']);

        if (Money::cmp($total, Money::add($net, $tax)) !== 0) {
            throw ApiError::make(422, 'TOTAL_MISMATCH', 'total_amount harus sama dengan net_sales_amount + tax_amount.');
        }

        $existing = Posting::where('idempotency_key', $data['idempotency_key'])->first();

        if ($existing) {
            return ['posting' => $existing->load('lines'), 'created' => false];
        }

        try {
            $posting = DB::transaction(function () use ($data, $net, $tax, $total) {
                $double = Posting::where('idempotency_key', $data['idempotency_key'])
                    ->lockForUpdate()
                    ->first();

                if ($double) {
                    return $double;
                }

                if ($data['entry_type'] === 'reversal') {
                    return $this->createReversal($data);
                }

                return $this->createSale($data, $net, $tax, $total);
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                $existing = Posting::where('idempotency_key', $data['idempotency_key'])->firstOrFail();

                return ['posting' => $existing->load('lines'), 'created' => false];
            }

            throw $e;
        }

        return ['posting' => $posting->load('lines'), 'created' => $posting->wasRecentlyCreated];
    }

    private function createSale(array $data, string $net, string $tax, string $total): Posting
    {
        $debitAccount = self::DEBIT_ACCOUNTS[$data['payment_method']] ?? null;

        if (! $debitAccount) {
            throw ApiError::make(422, 'PAYMENT_METHOD_INVALID', 'payment_method tidak dikenal.');
        }

        $posting = Posting::create([
            'idempotency_key' => $data['idempotency_key'],
            'trx_number' => $data['trx_number'],
            'entry_type' => 'sale',
            'outlet_id' => $data['outlet_id'],
            'outlet_code' => $data['outlet_code'],
            'shift_id' => $data['shift_id'],
            'business_date' => $data['business_date'],
            'payment_method' => $data['payment_method'],
            'net_sales_amount' => $net,
            'tax_amount' => $tax,
            'total_amount' => $total,
            'posted_at' => $data['occurred_at'] ?? now(),
        ]);

        $this->writeLines($posting, [
            ['account_code' => $debitAccount, 'debit' => $total, 'credit' => '0.00'],
            ['account_code' => '4101', 'debit' => '0.00', 'credit' => $net],
            ['account_code' => '2101', 'debit' => '0.00', 'credit' => $tax],
        ]);

        return $posting;
    }

    private function createReversal(array $data): Posting
    {
        $sale = Posting::where('trx_number', $data['trx_number'])
            ->where('entry_type', 'sale')
            ->first();

        if (! $sale) {
            throw ApiError::make(422, 'REVERSAL_WITHOUT_SALE', 'Reversal tanpa sale terkait.');
        }

        $posting = Posting::create([
            'idempotency_key' => $data['idempotency_key'],
            'trx_number' => $data['trx_number'],
            'entry_type' => 'reversal',
            'outlet_id' => $sale->outlet_id,
            'outlet_code' => $sale->outlet_code,
            'shift_id' => $sale->shift_id,
            'business_date' => $sale->business_date->format('Y-m-d'),
            'payment_method' => $sale->payment_method,
            'net_sales_amount' => (string) $sale->net_sales_amount,
            'tax_amount' => (string) $sale->tax_amount,
            'total_amount' => (string) $sale->total_amount,
            'posted_at' => $data['occurred_at'] ?? now(),
        ]);

        // Sisi debit/kredit dibalik dari sale, nominal sama persis.
        foreach ($sale->load('lines')->lines as $line) {
            $posting->lines()->create([
                'account_code' => $line->account_code,
                'debit' => (string) $line->credit,
                'credit' => (string) $line->debit,
            ]);
        }

        $this->assertBalanced($posting);

        return $posting;
    }

    private function writeLines(Posting $posting, array $lines): void
    {
        foreach ($lines as $line) {
            $posting->lines()->create($line);
        }

        $this->assertBalanced($posting);
    }

    private function assertBalanced(Posting $posting): void
    {
        $debit = '0.00';
        $credit = '0.00';

        foreach ($posting->lines()->get() as $line) {
            $debit = Money::add($debit, (string) $line->debit);
            $credit = Money::add($credit, (string) $line->credit);
        }

        if (Money::cmp($debit, $credit) !== 0) {
            throw new \RuntimeException("Jurnal posting {$posting->id} tidak seimbang.");
        }
    }

    /** Ledger bersih: sale dikurangi reversal, per metode. */
    public function dailySales(int $outletId, string $date): array
    {
        $rows = Posting::selectRaw('payment_method, entry_type, COUNT(*) c, COALESCE(SUM(total_amount),0) t, COALESCE(SUM(tax_amount),0) x, COALESCE(SUM(net_sales_amount),0) n')
            ->where('outlet_id', $outletId)
            ->whereDate('business_date', $date)
            ->groupBy('payment_method', 'entry_type')
            ->get();

        $totals = ['cash' => '0.00', 'debit' => '0.00', 'qris' => '0.00'];
        $count = 0;
        $net = '0.00';
        $taxSum = '0.00';

        foreach ($rows as $r) {
            $sign = $r->entry_type === 'sale' ? 1 : -1;

            if (isset($totals[$r->payment_method])) {
                $totals[$r->payment_method] = $sign === 1
                    ? Money::add($totals[$r->payment_method], (string) $r->t)
                    : Money::sub($totals[$r->payment_method], (string) $r->t);
            }

            $count += $sign * (int) $r->c;
            $net = $sign === 1 ? Money::add($net, (string) $r->n) : Money::sub($net, (string) $r->n);
            $taxSum = $sign === 1 ? Money::add($taxSum, (string) $r->x) : Money::sub($taxSum, (string) $r->x);
        }

        return [
            'outlet_id' => $outletId,
            'business_date' => $date,
            'totals' => $totals,
            'trx_count' => $count,
            'net_sales' => $net,
            'tax_total' => $taxSum,
        ];
    }
}
