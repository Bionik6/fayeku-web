<?php

namespace App\Providers\Compta;

use Illuminate\Support\ServiceProvider;

class PartnershipServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadViewsFrom(resource_path('views/compta'), 'partnership');
        $this->loadRoutesFrom(base_path('routes/compta-web.php'));
    }
}
