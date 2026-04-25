<?php

namespace App\Services\Shared;

use App\Interfaces\Shared\WhatsAppProviderInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppBusinessProvider implements WhatsAppProviderInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiVersion,
        private readonly string $phoneNumberId,
        private readonly string $accessToken,
        private readonly string $defaultLanguage,
    ) {}

    public function send(string $phone, string $message): bool
    {
        return $this->dispatch([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->normalizePhone($phone),
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $message,
            ],
        ]);
    }

    public function sendTemplate(
        string $phone,
        string $templateName,
        array $bodyParameters = [],
        ?string $urlButtonParameter = null,
        ?string $language = null,
    ): bool {
        $components = [];

        if ($bodyParameters !== []) {
            $components[] = [
                'type' => 'body',
                'parameters' => $this->buildBodyParameters($bodyParameters),
            ];
        }

        if ($urlButtonParameter !== null && $urlButtonParameter !== '') {
            $components[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => [
                    ['type' => 'text', 'text' => $urlButtonParameter],
                ],
            ];
        }

        return $this->dispatch([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->normalizePhone($phone),
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language ?? $this->defaultLanguage],
                'components' => $components,
            ],
        ]);
    }

    /**
     * @param  array<int|string, string>  $bodyParameters
     * @return array<int, array<string, string>>
     */
    private function buildBodyParameters(array $bodyParameters): array
    {
        $isNamed = ! array_is_list($bodyParameters);

        $parameters = [];

        foreach ($bodyParameters as $key => $value) {
            $param = ['type' => 'text', 'text' => (string) $value];

            if ($isNamed) {
                $param['parameter_name'] = (string) $key;
            }

            $parameters[] = $param;
        }

        return $parameters;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatch(array $payload): bool
    {
        $endpoint = sprintf('%s/%s/%s/messages', rtrim($this->baseUrl, '/'), $this->apiVersion, $this->phoneNumberId);

        try {
            Http::withToken($this->accessToken)
                ->acceptJson()
                ->asJson()
                ->timeout(15)
                ->post($endpoint, $payload)
                ->throw();

            return true;
        } catch (RequestException $e) {
            Log::error('WhatsApp Business send failed', [
                'to' => $payload['to'] ?? null,
                'type' => $payload['type'] ?? null,
                'status' => $e->response?->status(),
                'body' => $e->response?->body(),
            ]);

            return false;
        } catch (ConnectionException $e) {
            Log::error('WhatsApp Business send unreachable', [
                'to' => $payload['to'] ?? null,
                'type' => $payload['type'] ?? null,
                'reason' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function normalizePhone(string $phone): string
    {
        return ltrim(preg_replace('/[^0-9+]/', '', $phone) ?? '', '+');
    }
}
