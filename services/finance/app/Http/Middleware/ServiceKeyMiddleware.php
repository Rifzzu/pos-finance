<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiError;
use Closure;
use Illuminate\Http\Request;

class ServiceKeyMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $given = (string) $request->header('X-Service-Key', '');
        $expected = (string) env('INTERNAL_API_KEY', '');

        if ($expected === '' || ! hash_equals($expected, $given)) {
            throw ApiError::make(403, 'SERVICE_KEY_INVALID', 'Service key tidak valid.');
        }

        return $next($request);
    }
}
