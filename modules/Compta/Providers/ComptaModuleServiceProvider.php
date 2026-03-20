<?php

namespace Modules\Compta\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Compta\Compliance\Providers\ComplianceServiceProvider;
use Modules\Compta\Export\Providers\ExportServiceProvider;
use Modules\Compta\Partnership\Providers\PartnershipServiceProvider;
use Modules\Compta\Portfolio\Providers\PortfolioServiceProvider;

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
