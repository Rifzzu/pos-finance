<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'outlet_id' => $this->outlet_id,
            'outlet_code' => $this->outlet?->code,
            'cashier_id' => $this->cashier_id,
            'business_date' => $this->business_date instanceof \DateTimeInterface
                ? $this->business_date->format('Y-m-d')
                : (string) $this->business_date,
            'opened_at' => $this->opened_at,
            'closed_at' => $this->closed_at,
            'opening_cash' => (string) $this->opening_cash,
            'expected_cash' => $this->expected_cash !== null ? (string) $this->expected_cash : null,
            'actual_cash' => $this->actual_cash !== null ? (string) $this->actual_cash : null,
            'cash_variance' => $this->cash_variance !== null ? (string) $this->cash_variance : null,
            'status' => $this->status,
        ];
    }
}
