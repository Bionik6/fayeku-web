<?php

namespace App\Providers\PME;

use Illuminate\Support\ServiceProvider;

class InvoicingServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/pme-web.php'));
        $this->loadRoutesFrom(base_path('routes/pme-api.php'));
    }
}
