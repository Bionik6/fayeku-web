<?php

namespace Modules\PME\Collection\Providers;

use Illuminate\Support\ServiceProvider;

class CollectionServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'collection');
    }
}
