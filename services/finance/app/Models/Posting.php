<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Posting extends Model
{
    protected $fillable = [
        'idempotency_key',
        'trx_number',
        'entry_type',
        'outlet_id',
        'outlet_code',
        'shift_id',
        'business_date',
        'payment_method',
        'net_sales_amount',
        'tax_amount',
        'total_amount',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date:Y-m-d',
            'posted_at' => 'datetime',
            'net_sales_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function lines()
    {
        return $this->hasMany(JournalLine::class);
    }
}
