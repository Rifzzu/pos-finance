<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EodReconciliation extends Model
{
    protected $fillable = [
        'outlet_id',
        'outlet_code',
        'business_date',
        'status',
        'pos_cash',
        'pos_debit',
        'pos_qris',
        'fin_cash',
        'fin_debit',
        'fin_qris',
        'pos_trx_count',
        'fin_trx_count',
        'cash_variance',
        'mismatch_reasons',
        'run_by',
        'run_at',
        'resolved_by',
        'resolved_at',
        'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date:Y-m-d',
            'run_at' => 'datetime',
            'resolved_at' => 'datetime',
            'pos_cash' => 'decimal:2',
            'pos_debit' => 'decimal:2',
            'pos_qris' => 'decimal:2',
            'fin_cash' => 'decimal:2',
            'fin_debit' => 'decimal:2',
            'fin_qris' => 'decimal:2',
            'cash_variance' => 'decimal:2',
            'mismatch_reasons' => 'array',
        ];
    }
}
