<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalLine extends Model
{
    protected $fillable = [
        'posting_id',
        'account_code',
        'debit',
        'credit',
    ];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
        ];
    }

    public function posting()
    {
        return $this->belongsTo(Posting::class);
    }
}
