<?php

namespace App\Services\Shared;

use App\Interfaces\Shared\SmsProviderInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LafricamobileSmsProvider implements SmsProviderInterface
{
    /**
     * @var array{ok: bool, status: int|null, body: string|null}|null
     */
    private ?array $lastResult = null;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $accountId,
        private readonly string $password,
        private readonly string $sender,
        private readonly ?string $callbackUrl = null,
    ) {}

    public function send(string $phone, string $message): bool
    {
        $payload = [
            'accountid' => $this->accountId,
            'password' => $this->password,
            'sender' => $this->sender,
            'to' => $this->formatRecipient($phone),
            'text' => $message,
        ];

        if ($this->callbackUrl !== null && trim($this->callbackUrl) !== '') {
            $payload['ret_url'] = $this->callbackUrl;
        }

        $endpoint = rtrim($this->baseUrl, '/');

        try {
            $response = Http::asJson()
                ->acceptJson()
                ->timeout(15)
                ->post($endpoint, $payload)
                ->throw();

            $this->lastResult = [
                'ok' => true,
                'status' => $response->status(),
                'body' => $response->body(),
            ];

            Log::debug('Lafricamobile SMS send accepted', [
                'to' => $phone,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return true;
        } catch (RequestException $e) {
            $this->lastResult = [
                'ok' => false,
                'status' => $e->response?->status(),
                'body' => $e->response?->body(),
            ];

            Log::error('Lafricamobile SMS send failed', [
                'to' => $phone,
                'status' => $e->response?->status(),
                'body' => $e->response?->body(),
            ]);

            return false;
        }
    }

    /**
     * Détails de la dernière tentative d'envoi (status HTTP, corps brut renvoyé par LAM).
     * Utile pour les commandes de diagnostic. `null` si aucun envoi n'a encore eu lieu.
     *
     * @return array{ok: bool, status: int|null, body: string|null}|null
     */
    public function lastResult(): ?array
    {
        return $this->lastResult;
    }

    private function formatRecipient(string $phone): string
    {
        $digits = preg_replace('/[^0-9+]/', '', $phone) ?? '';

        if (! str_starts_with($digits, '+')) {
            $digits = '+'.$digits;
        }

        return $digits;
    }
}
