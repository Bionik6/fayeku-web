<?php

namespace App\Providers\Shared;

use Illuminate\Support\ServiceProvider;
use App\Interfaces\Shared\SmsProviderInterface;
use App\Interfaces\Shared\WhatsAppProviderInterface;
use App\Services\Shared\FakeSmsProvider;
use App\Services\Shared\FakeWhatsAppProvider;
use App\Services\Shared\OtpService;
use App\Services\Shared\QuotaService;
use App\Services\Shared\TwilioWhatsAppProvider;
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
        //
    }
}
