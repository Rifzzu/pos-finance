<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $debit = '0.00';
        $credit = '0.00';

        foreach ($this->whenLoaded('lines', $this->lines ?? collect()) as $line) {
            $debit = Money::add($debit, (string) $line->debit);
            $credit = Money::add($credit, (string) $line->credit);
        }

        return [
            'id' => $this->id,
            'idempotency_key' => $this->idempotency_key,
            'trx_number' => $this->trx_number,
            'entry_type' => $this->entry_type,
            'outlet_id' => $this->outlet_id,
            'outlet_code' => $this->outlet_code,
            'shift_id' => $this->shift_id,
            'business_date' => $this->business_date instanceof \DateTimeInterface
                ? $this->business_date->format('Y-m-d')
                : (string) $this->business_date,
            'payment_method' => $this->payment_method,
            'net_sales_amount' => (string) $this->net_sales_amount,
            'tax_amount' => (string) $this->tax_amount,
            'total_amount' => (string) $this->total_amount,
            'posted_at' => $this->posted_at,
            'journal_lines' => JournalLineResource::collection($this->whenLoaded('lines')),
            'total_debit' => $debit,
            'total_credit' => $credit,
            'balanced' => Money::cmp($debit, $credit) === 0,
        ];
    }
}
