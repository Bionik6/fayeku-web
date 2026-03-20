<?php

namespace Modules\PME\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\PME\Clients\Providers\ClientsServiceProvider;
use Modules\PME\Collection\Providers\CollectionServiceProvider;
use Modules\PME\Invoicing\Providers\InvoicingServiceProvider;
use Modules\PME\Treasury\Providers\TreasuryServiceProvider;

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
