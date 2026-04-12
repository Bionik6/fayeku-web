<?php

namespace App\Providers\Compta;

use Illuminate\Support\ServiceProvider;
use App\Services\Compta\AlertService;
use App\Services\Compta\PortfolioService;

class PortfolioServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(PortfolioService::class);
        $this->app->scoped(AlertService::class);
    }

    public function boot(): void
    {
        //
    }
}
