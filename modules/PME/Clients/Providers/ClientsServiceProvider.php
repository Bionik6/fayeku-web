<?php

namespace Modules\PME\Clients\Providers;

use Illuminate\Support\ServiceProvider;

class ClientsServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'clients');
    }
}
