<?php

use App\Http\Controllers\InternalController;
use App\Http\Controllers\OutletController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\TransactionController;
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
            'service' => 'pos',
            'db' => $db,
            'timezone' => config('app.timezone'),
            'time' => now()->toIso8601String(),
        ],
    ], $db === 'ok' ? 200 : 503);
};

Route::get('/health', $health);

Route::middleware('auth.jwt')->group(function () {
    Route::get('/outlets', [OutletController::class, 'index']);

    Route::middleware('role:kasir,supervisor_pos,superadmin')->group(function () {
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/{id}', [ProductController::class, 'show']);
        Route::get('/transactions', [TransactionController::class, 'index']);
        Route::get('/transactions/{id}', [TransactionController::class, 'show']);
        Route::get('/transactions/{id}/receipt.pdf', [TransactionController::class, 'receipt']);
    });

    Route::middleware('role:supervisor_pos,superadmin')->group(function () {
        Route::post('/products', [ProductController::class, 'store']);
        Route::match(['put', 'patch'], '/products/{id}', [ProductController::class, 'update']);
        Route::patch('/products/{id}/status', [ProductController::class, 'setStatus']);
    });

    Route::middleware('role:kasir,superadmin')->group(function () {
        Route::post('/shifts/open', [ShiftController::class, 'open']);
        Route::get('/shifts/current', [ShiftController::class, 'current']);
        Route::post('/shifts/{id}/close', [ShiftController::class, 'close']);
    });

    Route::middleware('role:kasir,supervisor_pos,superadmin')->group(function () {
        Route::get('/shifts/{id}/summary', [ShiftController::class, 'summary']);
    });

    Route::middleware('role:kasir,superadmin')->group(function () {
        Route::post('/transactions', [TransactionController::class, 'store']);
        Route::post('/transactions/{id}/pay', [TransactionController::class, 'pay']);
    });

    Route::middleware('role:supervisor_pos,superadmin')->group(function () {
        Route::post('/transactions/{id}/void', [TransactionController::class, 'void']);
    });

    Route::middleware('role:kasir,supervisor_pos,superadmin')->group(function () {
        Route::post('/transactions/{id}/resync-finance', [TransactionController::class, 'resync']);
    });
});

Route::middleware('service.key')->group(function () {
    Route::get('/internal/eod-summary', [InternalController::class, 'eodSummary']);
});
