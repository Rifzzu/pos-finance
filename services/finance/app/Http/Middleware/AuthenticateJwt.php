<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiError;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AuthenticateJwt
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (! $token) {
            throw ApiError::make(401, 'TOKEN_INVALID', 'Token tidak disertakan.');
        }

        $url = rtrim((string) env('AUTH_SERVICE_URL', 'http://auth:8001'), '/').'/auth/me';
        $timeout = (int) env('HTTP_TIMEOUT', 3);

        try {
            $res = Http::timeout($timeout)->acceptJson()->withToken($token)->get($url);
        } catch (ConnectionException $e) {
            throw ApiError::make(503, 'AUTH_UNAVAILABLE', 'Service auth tidak dapat dihubungi.');
        } catch (\Throwable $e) {
            throw ApiError::make(503, 'AUTH_UNAVAILABLE', 'Service auth tidak dapat dihubungi.');
        }

        if ($res->serverError()) {
            throw ApiError::make(503, 'AUTH_UNAVAILABLE', 'Service auth tidak dapat dihubungi.');
        }

        if (! $res->successful()) {
            $code = $res->json('code', 'TOKEN_INVALID');
            $message = $res->json('message', 'Token tidak valid.');
            throw ApiError::make($res->status(), $code, $message);
        }

        $request->attributes->set('authUser', $res->json('data'));

        return $next($request);
    }
}
