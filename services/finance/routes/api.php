<?php

use App\Http\Controllers\InternalPostingController;
use App\Http\Controllers\PostingController;
use App\Http\Controllers\ReconciliationController;
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
            'service' => 'finance',
            'db' => $db,
            'timezone' => config('app.timezone'),
            'time' => now()->toIso8601String(),
        ],
    ], $db === 'ok' ? 200 : 503);
};

Route::get('/health', $health);

Route::middleware('service.key')->group(function () {
    Route::post('/internal/postings', [InternalPostingController::class, 'store']);
});

Route::middleware(['auth.jwt', 'role:staff_finance,manager_finance,superadmin'])->group(function () {
    Route::get('/postings', [PostingController::class, 'index']);
    Route::get('/postings/{id}', [PostingController::class, 'show']);
    Route::get('/reports/daily-sales', [PostingController::class, 'dailySales']);
    Route::post('/reconciliations/run', [ReconciliationController::class, 'run']);
    Route::get('/reconciliations', [ReconciliationController::class, 'index']);
    Route::get('/reconciliations/overview', [ReconciliationController::class, 'overview']);
    Route::get('/reconciliations/{id}', [ReconciliationController::class, 'show']);
});

Route::middleware(['auth.jwt', 'role:manager_finance,superadmin'])->group(function () {
    Route::patch('/reconciliations/{id}/resolve', [ReconciliationController::class, 'resolve']);
});
