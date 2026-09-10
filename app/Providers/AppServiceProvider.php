<?php

namespace App\Providers;

use App\IoT\Registry\ConnectorManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ConnectorManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 访客扫码接口限流：每个 IP 每分钟 10 次，防止刷接口
        RateLimiter::for('visitor_access', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
