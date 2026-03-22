<?php

namespace Modules\Compta\Compliance\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Compta\Compliance\Services\ComplianceService;
use Modules\Compta\Compliance\Services\DGIDConnector;
use Modules\Compta\Compliance\Services\FNEFiscalConnector;

class ComplianceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ComplianceService::class, fn () => new ComplianceService([
            new FNEFiscalConnector,
            new DGIDConnector,
        ]));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
