<?php

namespace App\Services\Shared;

use App\Interfaces\Shared\WhatsAppProviderInterface;
use Illuminate\Support\Facades\Log;

class FakeWhatsAppProvider implements WhatsAppProviderInterface
{
    public function send(string $phone, string $message): bool
    {
        Log::info('WhatsApp envoyé (fake)', ['phone' => $phone, 'message' => $message]);

        return true;
    }

    public function sendTemplate(
        string $phone,
        string $templateName,
        array $bodyParameters = [],
        ?string $urlButtonParameter = null,
        ?string $language = null,
    ): bool {
        Log::info('WhatsApp template envoyé (fake)', [
            'phone' => $phone,
            'template' => $templateName,
            'language' => $language,
            'body' => $bodyParameters,
            'button_url' => $urlButtonParameter,
        ]);

        return true;
    }
}
