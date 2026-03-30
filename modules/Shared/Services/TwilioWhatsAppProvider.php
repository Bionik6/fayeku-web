<?php

namespace Modules\Shared\Services;

use Illuminate\Support\Facades\Log;
use Modules\Shared\Interfaces\WhatsAppProviderInterface;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;

class TwilioWhatsAppProvider implements WhatsAppProviderInterface
{
    public function __construct(
        private Client $client,
        private string $from,
    ) {}

    public function send(string $phone, string $message): bool
    {
        $to = str_starts_with($phone, 'whatsapp:') ? $phone : 'whatsapp:'.$phone;

        try {
            $this->client->messages->create($to, [
                'from' => $this->from,
                'body' => $message,
            ]);

            return true;
        } catch (TwilioException $e) {
            Log::error('WhatsApp send failed', [
                'to' => $phone,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
