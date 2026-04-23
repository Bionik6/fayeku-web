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

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $senderAddress,
        private readonly string $senderName,
        private readonly CacheRepository $cache,
    ) {}

    public function send(string $phone, string $message): bool
    {
        $token = $this->accessToken();

        if ($token === null) {
            return false;
        }

        $sender = $this->formatAddress($this->senderAddress);
        $recipient = $this->formatAddress($phone);
        $endpoint = sprintf(
            '%s/smsmessaging/v1/outbound/%s/requests',
            rtrim($this->baseUrl, '/'),
            rawurlencode($sender),
        );

        try {
            Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->timeout(15)
                ->post($endpoint, [
                    'outboundSMSMessageRequest' => [
                        'address' => $recipient,
                        'senderAddress' => $sender,
                        'senderName' => $this->senderName,
                        'outboundSMSTextMessage' => [
                            'message' => $message,
                        ],
                    ],
                ])
                ->throw();

            return true;
        } catch (RequestException $e) {
            if ($e->response?->status() === 401) {
                $this->cache->forget(self::TOKEN_CACHE_KEY);
            }

            Log::error('Orange SMS send failed', [
                'to' => $phone,
                'status' => $e->response?->status(),
                'body' => $e->response?->body(),
            ]);

            return false;
        }
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
