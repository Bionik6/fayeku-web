<?php

namespace App\Http\Controllers;

use App\Models\Marketing\Article;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $baseUrl = rtrim((string) config('marketing.site.url'), '/');
        $today = now()->toDateString();

        $urls = $this->marketingUrls($baseUrl, $today);

        if (class_exists(Article::class)) {
            foreach (Article::query()
                ->where('is_published', true)
                ->orderByDesc('published_at')
                ->get(['slug', 'updated_at', 'published_at']) as $article) {
                $urls[] = [
                    'loc' => $baseUrl.'/blog/'.$article->slug,
                    'lastmod' => optional($article->updated_at ?? $article->published_at)->toDateString() ?? $today,
                    'changefreq' => 'monthly',
                    'priority' => '0.6',
                ];
            }
        }

        $xml = view('sitemap', ['urls' => $urls])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * @return array<int, array{loc: string, lastmod: string, changefreq: string, priority: string}>
     */
    private function marketingUrls(string $baseUrl, string $today): array
    {
        $paths = [
            ['/', '1.0', 'weekly'],
            ['/entreprises', '0.9', 'monthly'],
            ['/accountants', '0.9', 'monthly'],
            ['/pricing', '0.9', 'monthly'],
            ['/conformite', '0.8', 'monthly'],
            ['/contact', '0.7', 'monthly'],
            ['/logiciel-facturation-senegal', '0.9', 'monthly'],
            ['/logiciel-cabinet-comptable-senegal', '0.9', 'monthly'],
            ['/facturation-electronique-dgid', '0.8', 'monthly'],
            ['/logiciel-devis-facture-senegal', '0.8', 'monthly'],
            ['/relance-facture-impayee-whatsapp', '0.8', 'monthly'],
            ['/alternative-sage-senegal', '0.7', 'monthly'],
            ['/blog', '0.8', 'weekly'],
            ['/mentions-legales', '0.3', 'yearly'],
            ['/confidentialite', '0.3', 'yearly'],
        ];

        $registered = collect(Route::getRoutes())
            ->filter(fn ($r) => in_array('GET', $r->methods(), true))
            ->map(fn ($r) => '/'.ltrim($r->uri(), '/'))
            ->all();

        $urls = [];
        foreach ($paths as [$path, $priority, $changefreq]) {
            if ($path !== '/' && ! in_array($path, $registered, true) && ! in_array(ltrim($path, '/'), $registered, true)) {
                continue;
            }
            $urls[] = [
                'loc' => $baseUrl.($path === '/' ? '' : $path),
                'lastmod' => $today,
                'changefreq' => $changefreq,
                'priority' => $priority,
            ];
        }

        return $urls;
    }
}
