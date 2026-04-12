<?php

namespace App\Providers\Auth;

use Illuminate\Support\ServiceProvider;

class AuthModuleServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/auth-web.php'));
        $this->loadRoutesFrom(base_path('routes/auth-api.php'));
    }
}
