<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    protected $fillable = [
        'outlet_id',
        'cashier_id',
        'business_date',
        'opened_at',
        'closed_at',
        'opening_cash',
        'expected_cash',
        'actual_cash',
        'cash_variance',
        'status',
        'active_guard',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date:Y-m-d',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_cash' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'actual_cash' => 'decimal:2',
            'cash_variance' => 'decimal:2',
        ];
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
