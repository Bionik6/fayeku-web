<?php

namespace Modules\Shared\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Shared\Services\OtpService;
use Modules\Shared\Services\QuotaService;

class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OtpService::class);
        $this->app->singleton(QuotaService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->mergeConfigFrom(__DIR__.'/../config/fayeku.php', 'fayeku');
    }
}
