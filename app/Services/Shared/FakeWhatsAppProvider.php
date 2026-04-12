<?php

namespace App\Services\Shared;

use Illuminate\Support\Facades\Log;
use App\Interfaces\Shared\WhatsAppProviderInterface;

class FakeWhatsAppProvider implements WhatsAppProviderInterface
{
    public function send(string $phone, string $message): bool
    {
        Log::info('WhatsApp envoyé (fake)', ['phone' => $phone, 'message' => $message]);

        return true;
    }
}
