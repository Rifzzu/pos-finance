<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trx_number' => $this->trx_number,
            'outlet_id' => $this->outlet_id,
            'outlet_code' => $this->outlet_code,
            'shift_id' => $this->shift_id,
            'cashier_id' => $this->cashier_id,
            'business_date' => $this->business_date instanceof \DateTimeInterface
                ? $this->business_date->format('Y-m-d')
                : (string) $this->business_date,
            'status' => $this->status,
            'subtotal' => (string) $this->subtotal,
            'discount_amount' => (string) $this->discount_amount,
            'tax_amount' => (string) $this->tax_amount,
            'grand_total' => (string) $this->grand_total,
            'payment_method' => $this->payment_method,
            'paid_amount' => $this->paid_amount !== null ? (string) $this->paid_amount : null,
            'change_amount' => $this->change_amount !== null ? (string) $this->change_amount : null,
            'paid_at' => $this->paid_at,
            'void_reason' => $this->void_reason,
            'voided_by' => $this->voided_by,
            'voided_at' => $this->voided_at,
            'finance_sync_status' => $this->finance_sync_status,
            'finance_synced_at' => $this->finance_synced_at,
            'finance_last_error' => $this->finance_last_error,
            'items' => TransactionItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
