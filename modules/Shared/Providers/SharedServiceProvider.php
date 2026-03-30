<?php

namespace Modules\Shared\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Shared\Interfaces\SmsProviderInterface;
use Modules\Shared\Interfaces\WhatsAppProviderInterface;
use Modules\Shared\Services\FakeSmsProvider;
use Modules\Shared\Services\FakeWhatsAppProvider;
use Modules\Shared\Services\OtpService;
use Modules\Shared\Services\QuotaService;
use Modules\Shared\Services\TwilioWhatsAppProvider;
use Twilio\Rest\Client as TwilioClient;

class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SmsProviderInterface::class, FakeSmsProvider::class);
        $this->app->singleton(OtpService::class);
        $this->app->singleton(QuotaService::class);

        $this->app->bind(WhatsAppProviderInterface::class, function ($app) {
            $sid = config('services.twilio.sid');
            $token = config('services.twilio.auth_token');

            if (empty($sid) || empty($token) || $app->environment('testing')) {
                return new FakeWhatsAppProvider;
            }

            return new TwilioWhatsAppProvider(
                new TwilioClient($sid, $token),
                config('services.twilio.whatsapp_from'),
            );
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->mergeConfigFrom(__DIR__.'/../config/fayeku.php', 'fayeku');
    }
}
