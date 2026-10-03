<?php

use App\Exceptions\ApiError;
use App\Http\Middleware\JwtAuthenticate;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'jwt' => JwtAuthenticate::class,
            'role' => RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ApiError $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->apiCode,
                'errors' => $e->apiErrors,
            ], $e->getCode());
        });

        $exceptions->render(function (ValidationException $e) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'code' => 'VALIDATION_ERROR',
                'errors' => $e->errors(),
            ], 422);
        });

        $exceptions->render(function (ThrottleRequestsException $e) {
            return response()->json([
                'message' => 'Terlalu banyak percobaan, coba lagi nanti.',
                'code' => 'RATE_LIMITED',
                'errors' => null,
            ], 429);
        });

        $exceptions->render(function (NotFoundHttpException $e) {
            return response()->json([
                'message' => 'Tidak ditemukan.',
                'code' => 'NOT_FOUND',
                'errors' => null,
            ], 404);
        });

        $exceptions->render(function (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Data tidak ditemukan.',
                'code' => 'NOT_FOUND',
                'errors' => null,
            ], 404);
        });
    })->create();
