<?php

namespace Modules\Compta\Partnership\Providers;

use Illuminate\Support\ServiceProvider;

class PartnershipServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'partnership');
    }
}
