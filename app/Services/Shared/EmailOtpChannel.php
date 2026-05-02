<?php

namespace App\Services\Shared;

use App\Interfaces\Shared\OtpChannelInterface;
use App\Mail\Auth\OtpCodeMail;
use Illuminate\Contracts\Mail\Mailer;

class EmailOtpChannel implements OtpChannelInterface
{
    public function __construct(private readonly Mailer $mailer) {}

    public function send(string $identifier, string $code): bool
    {
        $minutes = (int) config('fayeku.otp_expiry_minutes', 10);

        $this->mailer->to($identifier)->send(new OtpCodeMail(
            code: $code,
            expiresInMinutes: $minutes,
        ));

        return true;
    }
}
