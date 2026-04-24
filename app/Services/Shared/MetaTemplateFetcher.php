<?php

namespace App\Services\Shared;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Récupère les bodies des templates WhatsApp approuvés depuis Meta Graph API
 * et les garde en cache 24 h (configurable). C'est la *source de vérité* pour
 * le contenu des aperçus et des emails fallback — Meta reste la seule source
 * pour ce qui part réellement sur WhatsApp.
 *
 * Endpoint :
 *   GET /{api_version}/{business_account_id}/message_templates
 *       ?fields=name,language,status,components&limit=100
 */
class MetaTemplateFetcher
{
    private const CACHE_KEY = 'meta_whatsapp_templates';

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiVersion,
        private readonly ?string $businessAccountId,
        private readonly ?string $accessToken,
        private readonly int $cacheMinutes,
        private readonly CacheRepository $cache,
    ) {}

    /**
     * Retourne le corps (BODY component) d'un template donné, ou null si introuvable.
     */
    public function getBody(string $templateName, string $language = 'fr'): ?string
    {
        foreach ($this->allTemplates() as $tpl) {
            if (($tpl['name'] ?? null) !== $templateName) {
                continue;
            }
            if (($tpl['language'] ?? null) !== $language) {
                continue;
            }
            foreach ($tpl['components'] ?? [] as $component) {
                if (($component['type'] ?? '') === 'BODY') {
                    return $component['text'] ?? null;
                }
            }
        }

        return null;
    }

    /**
     * Invalide le cache pour forcer un refetch au prochain appel.
     */
    public function refresh(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }

    /**
     * Liste des templates approuvés pour ce WABA (mise en cache).
     *
     * @return array<int, array<string, mixed>>
     */
    public function allTemplates(): array
    {
        return $this->cache->remember(
            self::CACHE_KEY,
            now()->addMinutes($this->cacheMinutes),
            fn () => $this->fetchFromMeta(),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchFromMeta(): array
    {
        if (empty($this->businessAccountId) || empty($this->accessToken)) {
            return [];
        }

        $endpoint = sprintf(
            '%s/%s/%s/message_templates',
            rtrim($this->baseUrl, '/'),
            $this->apiVersion,
            $this->businessAccountId,
        );

        try {
            $response = Http::withToken($this->accessToken)
                ->acceptJson()
                ->timeout(20)
                ->get($endpoint, [
                    'fields' => 'name,language,status,components',
                    'limit' => 100,
                ])
                ->throw()
                ->json();

            $data = is_array($response['data'] ?? null) ? $response['data'] : [];

            return array_values(array_filter(
                $data,
                fn ($t) => is_array($t) && ($t['status'] ?? null) === 'APPROVED',
            ));
        } catch (RequestException $e) {
            Log::warning('Meta templates fetch failed', [
                'status' => $e->response?->status(),
                'body' => $e->response?->body(),
            ]);

            return [];
        } catch (Throwable $e) {
            Log::warning('Meta templates fetch failed', ['error' => $e->getMessage()]);

            return [];
        }
    }
}
