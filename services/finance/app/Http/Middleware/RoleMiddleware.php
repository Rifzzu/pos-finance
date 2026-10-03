<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiError;
use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->attributes->get('authUser');

        if (! is_array($user) || ! in_array($user['role'] ?? null, $roles, true)) {
            throw ApiError::make(403, 'FORBIDDEN', 'Akses ditolak untuk role ini.');
        }

        return $next($request);
    }
}
