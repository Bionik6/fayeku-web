<?php

namespace Modules\Shared\Services;

use Illuminate\Support\Facades\Log;
use Modules\Shared\Interfaces\WhatsAppProviderInterface;

class FakeWhatsAppProvider implements WhatsAppProviderInterface
{
    public function send(string $phone, string $message): bool
    {
        Log::info('WhatsApp envoyé (fake)', ['phone' => $phone, 'message' => $message]);

        return true;
    }
}
