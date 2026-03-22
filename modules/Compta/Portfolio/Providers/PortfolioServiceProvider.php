<?php

namespace Modules\Compta\Portfolio\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Compta\Portfolio\Services\AlertService;
use Modules\Compta\Portfolio\Services\PortfolioService;

class PortfolioServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(PortfolioService::class);
        $this->app->scoped(AlertService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'portfolio');
    }
}
