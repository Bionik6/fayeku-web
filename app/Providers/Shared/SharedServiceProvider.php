<?php

namespace App\Providers\Shared;

use App\Interfaces\Shared\SmsProviderInterface;
use App\Interfaces\Shared\WhatsAppProviderInterface;
use App\Services\Shared\FakeSmsProvider;
use App\Services\Shared\FakeWhatsAppProvider;
use App\Services\Shared\OrangeSmsProvider;
use App\Services\Shared\OtpService;
use App\Services\Shared\QuotaService;
use App\Services\Shared\WhatsAppBusinessProvider;
use App\Services\Shared\WhatsAppTemplateCatalog;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\ServiceProvider;

class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OtpService::class);
        $this->app->singleton(QuotaService::class);
        $this->app->singleton(WhatsAppTemplateCatalog::class);

        $this->app->bind(SmsProviderInterface::class, function ($app) {
            $clientId = config('services.orange_sms.client_id');
            $clientSecret = config('services.orange_sms.client_secret');
            $senderAddress = config('services.orange_sms.sender_address');

            if ($app->environment('testing') || empty($clientId) || empty($clientSecret) || empty($senderAddress)) {
                return new FakeSmsProvider;
            }

            return new OrangeSmsProvider(
                baseUrl: config('services.orange_sms.base_url'),
                clientId: $clientId,
                clientSecret: $clientSecret,
                senderAddress: $senderAddress,
                senderName: config('services.orange_sms.sender_name'),
                cache: $app->make(CacheRepository::class),
            );
        });

        $this->app->bind(WhatsAppProviderInterface::class, function ($app) {
            $phoneNumberId = config('services.whatsapp.phone_number_id');
            $accessToken = config('services.whatsapp.access_token');

            if ($app->environment('testing') || empty($phoneNumberId) || empty($accessToken)) {
                return new FakeWhatsAppProvider;
            }

            return new WhatsAppBusinessProvider(
                baseUrl: config('services.whatsapp.base_url'),
                apiVersion: config('services.whatsapp.api_version'),
                phoneNumberId: $phoneNumberId,
                accessToken: $accessToken,
                defaultLanguage: config('services.whatsapp.default_language'),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
