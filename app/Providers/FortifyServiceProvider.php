<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Fortify::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = mb_strtolower((string) $request->input('email', '')).'|'.$request->ip();

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('magic-link', function (Request $request) {
            $throttleKey = mb_strtolower((string) $request->input('email', '')).'|'.$request->ip();

            return Limit::perMinutes(15, 3)->by($throttleKey);
        });
    }
}
