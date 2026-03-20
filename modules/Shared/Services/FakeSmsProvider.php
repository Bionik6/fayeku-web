<?php

namespace Modules\Shared\Services;

use Illuminate\Support\Facades\Log;
use Modules\Shared\Interfaces\SmsProviderInterface;

class FakeSmsProvider implements SmsProviderInterface
{
    public function send(string $phone, string $message): bool
    {
        Log::info('SMS envoyé', ['phone' => $phone, 'message' => $message]);

        return true;
    }
}
