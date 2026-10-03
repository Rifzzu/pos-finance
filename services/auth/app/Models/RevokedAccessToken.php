<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevokedAccessToken extends Model
{
    protected $fillable = [
        'jti',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }
}
