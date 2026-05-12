@props([
    'metaTitle' => null,
    'metaDescription' => null,
    'canonicalUrl' => null,
    'pageType' => null,
    'breadcrumbs' => [],
    'faqItems' => [],
    'noindex' => false,
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
        <div class="flex min-h-screen flex-col">
            <main class="flex-1">
                {{ $slot }}
            </main>
        </div>

        @livewireScripts
        @fluxScripts
    </body>
</html>
