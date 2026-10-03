<?php

namespace App\Services;

use App\Exceptions\ApiError;
use App\Models\RefreshToken;
use App\Models\RevokedAccessToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthService
{
    public function login(string $email, string $password, ?string $userAgent, ?string $ip): array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ApiError::make(401, 'INVALID_CREDENTIALS', 'Email atau password salah.');
        }

        if (! $user->is_active) {
            throw ApiError::make(403, 'ACCOUNT_DISABLED', 'Akun nonaktif.');
        }

        return $this->issuePair($user, $userAgent, $ip);
    }

    public function refresh(string $opaque, ?string $userAgent, ?string $ip): array
    {
        $row = RefreshToken::where('token_hash', hash('sha256', $opaque))->lockForUpdate()->first();

        if (! $row || ! $row->isUsable()) {
            throw ApiError::make(401, 'REFRESH_INVALID', 'Refresh token tidak valid atau kedaluwarsa.');
        }

        $user = $row->user;

        if (! $user || ! $user->is_active) {
            throw ApiError::make(403, 'ACCOUNT_DISABLED', 'Akun nonaktif.');
        }

        return DB::transaction(function () use ($row, $user, $userAgent, $ip) {
            $row->update(['revoked_at' => now()]);

            return $this->issuePair($user, $userAgent, $ip);
        });
    }

    public function logout(User $user, string $jti, Carbon $exp, ?string $opaque): void
    {
        RevokedAccessToken::firstOrCreate(
            ['jti' => $jti],
            ['expires_at' => $exp]
        );

        if ($opaque) {
            RefreshToken::where('token_hash', hash('sha256', $opaque))
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        }
    }

    public function issuePair(User $user, ?string $userAgent, ?string $ip): array
    {
        $jti = (string) Str::uuid();
        $access = JWTAuth::claims([
            'role' => $user->role,
            'outlet_id' => $user->outlet_id,
            'jti' => $jti,
        ])->fromUser($user);

        $opaque = bin2hex(random_bytes(64));
        $ttl = (int) env('JWT_REFRESH_TTL', 10080);

        RefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $opaque),
            'expires_at' => now()->addMinutes($ttl),
            'user_agent' => $userAgent ? substr($userAgent, 0, 255) : null,
            'ip' => $ip,
        ]);

        return [
            'access_token' => $access,
            'refresh_token' => $opaque,
            'expires_in' => (int) config('jwt.ttl', 15) * 60,
            'user' => $user->fresh(),
        ];
    }

    public function revokeAllRefreshTokens(User $user): void
    {
        RefreshToken::where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }
}
