<?php

namespace App\Providers\Compta;

use Illuminate\Support\ServiceProvider;
use App\Providers\Compta\ComplianceServiceProvider;
use App\Providers\Compta\ExportServiceProvider;
use App\Providers\Compta\PartnershipServiceProvider;
use App\Providers\Compta\PortfolioServiceProvider;

class ComptaModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(PortfolioServiceProvider::class);
        $this->app->register(ExportServiceProvider::class);
        $this->app->register(PartnershipServiceProvider::class);
        $this->app->register(ComplianceServiceProvider::class);
    }
}
