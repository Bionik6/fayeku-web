<?php

namespace Modules\Compta\Portfolio\Providers;

use Illuminate\Support\ServiceProvider;

class PortfolioServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'portfolio');
    }
}
