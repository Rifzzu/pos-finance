<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory;

    public const ROLES = ['superadmin', 'kasir', 'supervisor_pos', 'staff_finance', 'manager_finance'];

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'outlet_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'outlet_id' => 'integer',
        ];
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    public function refreshTokens()
    {
        return $this->hasMany(RefreshToken::class);
    }
}
