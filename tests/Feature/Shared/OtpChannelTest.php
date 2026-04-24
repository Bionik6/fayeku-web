<?php

use App\Interfaces\Shared\OtpChannelInterface;
use App\Interfaces\Shared\SmsProviderInterface;
use App\Interfaces\Shared\WhatsAppProviderInterface;
use App\Services\Shared\SmsOtpChannel;
use App\Services\Shared\WhatsAppOtpChannel;
use App\Services\Shared\WhatsAppTemplateCatalog;

test('WhatsAppOtpChannel envoie le template AUTHENTICATION en positionnel avec bouton copy-code', function () {
    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldReceive('sendTemplate')
        ->once()
        ->withArgs(function (...$args) {
            [$phone, $templateName, $bodyParameters] = $args;
            $urlButtonParameter = $args[3] ?? null;

            return $phone === '+221771234567'
                && $templateName === 'fayeku_otp_verification'
                && $bodyParameters === ['123456']
                && $urlButtonParameter === '123456';
        })
        ->andReturnTrue();

    $channel = new WhatsAppOtpChannel($provider, app(WhatsAppTemplateCatalog::class));

    expect($channel->send('+221771234567', '123456'))->toBeTrue();
});

test('SmsOtpChannel envoie un SMS avec code et expiration', function () {
    config(['fayeku.otp_expiry_minutes' => 10]);

    $sms = Mockery::mock(SmsProviderInterface::class);
    $sms->shouldReceive('send')
        ->once()
        ->with('+221771234567', 'Votre code Fayeku : 123456. Expire dans 10 min.')
        ->andReturnTrue();

    $channel = new SmsOtpChannel($sms);

    expect($channel->send('+221771234567', '123456'))->toBeTrue();
});

test('le binding OtpChannelInterface resout WhatsApp par defaut', function () {
    config(['fayeku.otp_channel' => 'whatsapp']);

    expect(app(OtpChannelInterface::class))->toBeInstanceOf(WhatsAppOtpChannel::class);
});

test('le binding OtpChannelInterface bascule vers SMS quand la config le demande', function () {
    config(['fayeku.otp_channel' => 'sms']);

    expect(app(OtpChannelInterface::class))->toBeInstanceOf(SmsOtpChannel::class);
});
