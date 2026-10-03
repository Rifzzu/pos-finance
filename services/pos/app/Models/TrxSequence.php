<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrxSequence extends Model
{
    protected $fillable = [
        'outlet_id',
        'business_date',
        'last_number',
    ];
}
