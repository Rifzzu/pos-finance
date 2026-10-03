<?php

namespace App\Providers;

use App\Services\Finance\FinanceSyncService;
use App\Services\Finance\HttpFinanceSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FinanceSyncService::class, HttpFinanceSyncService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        date_default_timezone_set(config('app.timezone', 'Asia/Jakarta'));
        try {
            DB::statement("SET time_zone = '+07:00'");
        } catch (\Throwable) {
        }
    }
}
