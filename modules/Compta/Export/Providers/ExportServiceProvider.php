<?php

namespace Modules\Compta\Export\Providers;

use Illuminate\Support\ServiceProvider;

class ExportServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'export');
    }
}
