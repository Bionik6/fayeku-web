@props([
    'metaTitle' => null,
    'metaDescription' => null,
    'canonicalUrl' => null,
    'pageType' => null,
    'breadcrumbs' => [],
    'faqItems' => [],
    'noindex' => false,
    'navigation' => null,
    'legalLinks' => null,
    'site' => null,
])
<!DOCTYPE html>
<html lang="fr">
    <head>
        @include('partials.head', ['title' => $metaTitle ?? null])
        @include('partials.seo-meta', [
            'metaTitle' => $metaTitle ?? null,
            'metaDescription' => $metaDescription ?? null,
            'canonicalUrl' => $canonicalUrl ?? null,
            'pageType' => $pageType ?? null,
            'breadcrumbs' => $breadcrumbs ?? [],
            'faqItems' => $faqItems ?? [],
            'noindex' => $noindex ?? false,
        ])
    </head>
    <body class="marketing-site">
        <x-marketing.navbar :navigation="$navigation ?? config('marketing.navigation')" />

        <main class="pt-[68px]">
            {{ $slot }}
        </main>

        <x-marketing.footer
            :navigation="$navigation ?? config('marketing.navigation')"
            :legal-links="$legalLinks ?? config('marketing.legal_links')"
            :site="$site ?? config('marketing.site')"
        />

        @livewireScripts
        @fluxScripts
    </body>
</html>
