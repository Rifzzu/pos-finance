<?php

namespace App\Services\Finance;

use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class HttpFinanceSyncService implements FinanceSyncService
{
    public function postSale(Transaction $trx): array
    {
        return $this->send($this->payload($trx, 'sale'));
    }

    public function postReversal(Transaction $trx): array
    {
        return $this->send($this->payload($trx, 'reversal'));
    }

    private function payload(Transaction $trx, string $type): array
    {
        $bizDate = $trx->business_date instanceof \DateTimeInterface
            ? $trx->business_date->format('Y-m-d')
            : substr((string) $trx->business_date, 0, 10);

        return [
            'idempotency_key' => "{$trx->trx_number}:{$type}",
            'entry_type' => $type,
            'trx_number' => $trx->trx_number,
            'outlet_id' => $trx->outlet_id,
            'outlet_code' => $trx->outlet_code,
            'shift_id' => $trx->shift_id,
            'business_date' => $bizDate,
            'payment_method' => $trx->payment_method,
            'net_sales_amount' => Money::sub((string) $trx->subtotal, (string) $trx->discount_amount),
            'tax_amount' => (string) $trx->tax_amount,
            'total_amount' => (string) $trx->grand_total,
            'occurred_at' => ($type === 'reversal' ? $trx->voided_at : $trx->paid_at)?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }

    private function send(array $payload): array
    {
        $url = rtrim((string) env('FINANCE_SERVICE_URL', 'http://finance:8003'), '/').'/internal/postings';
        $timeout = (int) env('HTTP_TIMEOUT', 3);

        try {
            $res = Http::timeout($timeout)->connectTimeout(2)->acceptJson()
                ->withHeader('X-Service-Key', (string) env('INTERNAL_API_KEY', ''))
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            return ['status' => 'failed', 'error' => 'Finance tidak dapat dihubungi (timeout/koneksi).'];
        } catch (\Throwable $e) {
            return ['status' => 'failed', 'error' => substr('Finance error: '.$e->getMessage(), 0, 500)];
        }

        if (in_array($res->status(), [200, 201], true)) {
            return ['status' => 'synced', 'error' => null];
        }

        $msg = $res->json('message', "Finance menolak (HTTP {$res->status()}).");

        return ['status' => 'failed', 'error' => substr((string) $msg, 0, 500)];
    }
}
