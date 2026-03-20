<?php

namespace Modules\PME\Treasury\Providers;

use Illuminate\Support\ServiceProvider;

class TreasuryServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'treasury');
    }
}
