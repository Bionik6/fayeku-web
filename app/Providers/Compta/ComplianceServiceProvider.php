<?php

namespace App\Providers\Compta;

use Illuminate\Support\ServiceProvider;
use App\Services\Compta\ComplianceService;
use App\Services\Compta\DGIDConnector;
use App\Services\Compta\FNEFiscalConnector;

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
        //
    }
}
