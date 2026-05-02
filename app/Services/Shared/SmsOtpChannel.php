<?php

namespace App\Services\Shared;

use App\Interfaces\Shared\OtpChannelInterface;
use App\Interfaces\Shared\SmsProviderInterface;

class SmsOtpChannel implements OtpChannelInterface
{
    public function __construct(private readonly SmsProviderInterface $sms) {}

    public function send(string $identifier, string $code): bool
    {
        $minutes = (int) config('fayeku.otp_expiry_minutes', 10);

        return $this->sms->send(
            $identifier,
            "Votre code Fayeku : {$code}. Expire dans {$minutes} min."
        );
    }
}
