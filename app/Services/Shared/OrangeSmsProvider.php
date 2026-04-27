<?php

namespace App\Services\Shared;

use App\Interfaces\Shared\SmsProviderInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OrangeSmsProvider implements SmsProviderInterface
{
    private const TOKEN_CACHE_KEY = 'orange_sms.access_token';

    /**
     * @var array{ok: bool, status: int|null, resourceURL: string|null, body: string|null}|null
     */
    private ?array $lastResult = null;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $senderAddress,
        private readonly ?string $senderName,
        private readonly CacheRepository $cache,
    ) {}

    public function send(string $phone, string $message): bool
    {
        $token = $this->accessToken();

        if ($token === null) {
            $this->lastResult = ['ok' => false, 'status' => null, 'resourceURL' => null, 'body' => 'token fetch failed (voir log)'];

            return false;
        }

        $sender = $this->formatAddress($this->senderAddress);
        $recipient = $this->formatAddress($phone);
        $endpoint = sprintf(
            '%s/smsmessaging/v1/outbound/%s/requests',
            rtrim($this->baseUrl, '/'),
            rawurlencode($sender),
        );

        $outbound = [
            'address' => $recipient,
            'senderAddress' => $sender,
            'outboundSMSTextMessage' => [
                'message' => $message,
            ],
        ];

        if ($this->senderName !== null && trim($this->senderName) !== '') {
            $outbound['senderName'] = $this->senderName;
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->timeout(15)
                ->post($endpoint, ['outboundSMSMessageRequest' => $outbound])
                ->throw();

            $this->lastResult = [
                'ok' => true,
                'status' => $response->status(),
                'resourceURL' => $response->json('outboundSMSMessageRequest.resourceURL'),
                'body' => $response->body(),
            ];

            Log::debug('Orange SMS send accepted', [
                'to' => $phone,
                'status' => $response->status(),
                'resourceURL' => $this->lastResult['resourceURL'],
            ]);

            return true;
        } catch (RequestException $e) {
            if ($e->response?->status() === 401) {
                $this->cache->forget(self::TOKEN_CACHE_KEY);
            }

            $this->lastResult = [
                'ok' => false,
                'status' => $e->response?->status(),
                'resourceURL' => null,
                'body' => $e->response?->body(),
            ];

            Log::error('Orange SMS send failed', [
                'to' => $phone,
                'status' => $e->response?->status(),
                'body' => $e->response?->body(),
            ]);

            return false;
        }
    }

    /**
     * Détails de la dernière tentative d'envoi (status HTTP, resourceURL Orange, corps brut).
     * Utile pour les commandes de diagnostic. `null` si aucun envoi n'a encore eu lieu.
     *
     * @return array{ok: bool, status: int|null, resourceURL: string|null, body: string|null}|null
     */
    public function lastResult(): ?array
    {
        return $this->lastResult;
    }

    private function accessToken(): ?string
    {
        return $this->cache->remember(
            self::TOKEN_CACHE_KEY,
            now()->addMinutes(50),
            fn () => $this->fetchAccessToken(),
        );
    }

    private function fetchAccessToken(): ?string
    {
        try {
            $response = Http::asForm()
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->acceptJson()
                ->timeout(15)
                ->post(rtrim($this->baseUrl, '/').'/oauth/v3/token', [
                    'grant_type' => 'client_credentials',
                ])
                ->throw()
                ->json();

            return is_array($response) ? ($response['access_token'] ?? null) : null;
        } catch (RequestException $e) {
            Log::error('Orange SMS token fetch failed', [
                'status' => $e->response?->status(),
                'body' => $e->response?->body(),
            ]);

            return null;
        }
    }

    private function formatAddress(string $phone): string
    {
        if (str_starts_with($phone, 'tel:')) {
            return $phone;
        }

        $digits = preg_replace('/[^0-9+]/', '', $phone) ?? '';

        if (! str_starts_with($digits, '+')) {
            $digits = '+'.$digits;
        }

        return 'tel:'.$digits;
    }
}
