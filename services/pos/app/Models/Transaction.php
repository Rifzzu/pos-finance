<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'trx_number',
        'outlet_id',
        'outlet_code',
        'shift_id',
        'cashier_id',
        'business_date',
        'status',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'grand_total',
        'payment_method',
        'paid_amount',
        'change_amount',
        'paid_at',
        'void_reason',
        'voided_by',
        'voided_at',
        'finance_sync_status',
        'finance_synced_at',
        'finance_last_error',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date:Y-m-d',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
            'finance_synced_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
        ];
    }

    public function items()
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }
}
