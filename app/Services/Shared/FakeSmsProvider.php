<?php

namespace App\Services\Shared;

use Illuminate\Support\Facades\Log;
use App\Interfaces\Shared\SmsProviderInterface;

class FakeSmsProvider implements SmsProviderInterface
{
    public function send(string $phone, string $message): bool
    {
        Log::info('SMS envoyé', ['phone' => $phone, 'message' => $message]);

        return true;
    }
}
