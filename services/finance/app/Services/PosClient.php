<?php

namespace App\Services;

use App\Exceptions\ApiError;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class PosClient
{
    /** Ringkasan EOD dari POS. 503 bila POS tak dapat dihubungi. */
    public function eodSummary(int $outletId, string $date): array
    {
        $url = rtrim((string) env('POS_SERVICE_URL', 'http://pos:8002'), '/').'/internal/eod-summary';

        try {
            $res = Http::timeout((int) env('HTTP_TIMEOUT', 3))->acceptJson()
                ->withHeader('X-Service-Key', (string) env('INTERNAL_API_KEY', ''))
                ->get($url, ['outlet_id' => $outletId, 'business_date' => $date]);
        } catch (ConnectionException $e) {
            throw ApiError::make(503, 'POS_UNAVAILABLE', 'Service POS tidak dapat dihubungi.');
        } catch (\Throwable $e) {
            throw ApiError::make(503, 'POS_UNAVAILABLE', 'Service POS tidak dapat dihubungi.');
        }

        if ($res->serverError()) {
            throw ApiError::make(503, 'POS_UNAVAILABLE', 'Service POS tidak dapat dihubungi.');
        }

        if (! $res->successful()) {
            throw ApiError::make(
                $res->status(),
                $res->json('code', 'POS_ERROR'),
                $res->json('message', 'POS menolak permintaan.')
            );
        }

        return $res->json();
    }
}
