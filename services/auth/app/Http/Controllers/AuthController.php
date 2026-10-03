<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RefreshRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private AuthService $auth) {}

    public function login(LoginRequest $request)
    {
        $pair = $this->auth->login(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->userAgent(),
            $request->ip()
        );

        return response()->json([
            'message' => 'Login berhasil.',
            'data' => [
                'access_token' => $pair['access_token'],
                'refresh_token' => $pair['refresh_token'],
                'expires_in' => $pair['expires_in'],
                'user' => new UserResource($pair['user']),
            ],
        ]);
    }

    public function refresh(RefreshRequest $request)
    {
        $pair = $this->auth->refresh(
            $request->string('refresh_token')->toString(),
            $request->userAgent(),
            $request->ip()
        );

        return response()->json([
            'message' => 'Token diperbarui.',
            'data' => [
                'access_token' => $pair['access_token'],
                'refresh_token' => $pair['refresh_token'],
                'expires_in' => $pair['expires_in'],
                'user' => new UserResource($pair['user']),
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $this->auth->logout(
            $request->attributes->get('authUser'),
            $request->attributes->get('tokenJti'),
            Carbon::createFromTimestamp($request->attributes->get('tokenExp')),
            $request->input('refresh_token')
        );

        return response()->json([
            'message' => 'Logout berhasil.',
            'data' => null,
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'message' => 'Profil pengguna.',
            'data' => new UserResource($request->attributes->get('authUser')),
        ]);
    }
}
