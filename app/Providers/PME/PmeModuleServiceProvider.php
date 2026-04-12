<?php

namespace App\Providers\PME;

use Illuminate\Support\ServiceProvider;
use App\Providers\PME\ClientsServiceProvider;
use App\Providers\PME\CollectionServiceProvider;
use App\Providers\PME\InvoicingServiceProvider;
use App\Providers\PME\TreasuryServiceProvider;

class PmeModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(InvoicingServiceProvider::class);
        $this->app->register(ClientsServiceProvider::class);
        $this->app->register(CollectionServiceProvider::class);
        $this->app->register(TreasuryServiceProvider::class);
    }
}
