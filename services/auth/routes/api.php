<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

$health = function () {
    try {
        DB::connection()->getPdo();
        $db = 'ok';
    } catch (Throwable $e) {
        $db = 'down';
    }

    return response()->json([
        'message' => 'ok',
        'data' => [
            'service' => 'auth',
            'db' => $db,
            'timezone' => config('app.timezone'),
            'time' => now()->toIso8601String(),
        ],
    ], $db === 'ok' ? 200 : 503);
};

Route::get('/health', $health);
Route::get('/auth/health', $health);

Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:60,1');
Route::post('/auth/refresh', [AuthController::class, 'refresh'])->middleware('throttle:60,1');
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('jwt');
Route::get('/auth/me', [AuthController::class, 'me'])->middleware('jwt');

Route::middleware(['jwt', 'role:superadmin'])->group(function () {
    Route::get('/auth/users', [UserController::class, 'index']);
    Route::post('/auth/users', [UserController::class, 'store']);
    Route::get('/auth/users/{id}', [UserController::class, 'show']);
    Route::match(['put', 'patch'], '/auth/users/{id}', [UserController::class, 'update']);
    Route::patch('/auth/users/{id}/status', [UserController::class, 'setStatus']);
});
