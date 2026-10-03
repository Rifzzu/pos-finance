<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReconciliationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'outlet_id' => $this->outlet_id,
            'outlet_code' => $this->outlet_code,
            'business_date' => $this->business_date instanceof \DateTimeInterface
                ? $this->business_date->format('Y-m-d')
                : (string) $this->business_date,
            'status' => $this->status,
            'pos_cash' => (string) $this->pos_cash,
            'pos_debit' => (string) $this->pos_debit,
            'pos_qris' => (string) $this->pos_qris,
            'fin_cash' => (string) $this->fin_cash,
            'fin_debit' => (string) $this->fin_debit,
            'fin_qris' => (string) $this->fin_qris,
            'pos_trx_count' => $this->pos_trx_count,
            'fin_trx_count' => $this->fin_trx_count,
            'cash_variance' => (string) $this->cash_variance,
            'mismatch_reasons' => $this->mismatch_reasons,
            'run_by' => $this->run_by,
            'run_at' => $this->run_at,
            'resolved_by' => $this->resolved_by,
            'resolved_at' => $this->resolved_at,
            'resolution_note' => $this->resolution_note,
        ];
    }
}
