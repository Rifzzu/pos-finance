<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiError;
use App\Models\RevokedAccessToken;
use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class JwtAuthenticate
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $payload = JWTAuth::parseToken()->getPayload();
            $user = JWTAuth::parseToken()->authenticate();
        } catch (TokenExpiredException $e) {
            throw ApiError::make(401, 'TOKEN_EXPIRED', 'Token kedaluwarsa.');
        } catch (TokenInvalidException $e) {
            throw ApiError::make(401, 'TOKEN_INVALID', 'Token tidak valid.');
        } catch (JWTException $e) {
            throw ApiError::make(401, 'TOKEN_INVALID', 'Token tidak valid.');
        }

        if (! $user) {
            throw ApiError::make(401, 'TOKEN_INVALID', 'Token tidak valid.');
        }

        $jti = $payload->get('jti');

        if ($jti && RevokedAccessToken::where('jti', $jti)->exists()) {
            throw ApiError::make(401, 'TOKEN_REVOKED', 'Token sudah dicabut.');
        }

        if (! $user->is_active) {
            throw ApiError::make(403, 'ACCOUNT_DISABLED', 'Akun nonaktif.');
        }

        $request->attributes->set('authUser', $user->fresh());
        $request->attributes->set('tokenJti', $jti);
        $request->attributes->set('tokenExp', $payload->get('exp'));

        return $next($request);
    }
}
