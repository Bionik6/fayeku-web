<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => $metaTitle ?? null])
        <meta name="description" content="{{ $metaDescription ?? config('marketing.site.description') }}" />
        <meta name="keywords" content="{{ $metaKeywords ?? '' }}" />
        <meta property="og:title" content="{{ $metaTitle ?? config('marketing.site.name') }}" />
        <meta property="og:description" content="{{ $metaDescription ?? config('marketing.site.description') }}" />
        <meta property="og:url" content="{{ $canonicalUrl ?? config('marketing.site.url') }}" />
        <meta property="og:site_name" content="{{ config('marketing.site.name') }}" />
        <meta property="og:locale" content="{{ config('marketing.site.locale') }}" />
        <meta property="og:type" content="website" />
        <meta property="og:image" content="{{ config('marketing.site.url') }}/og-image.svg" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content="{{ $metaTitle ?? config('marketing.site.name') }}" />
        <meta name="twitter:description" content="{{ $metaDescription ?? config('marketing.site.description') }}" />
        <meta name="twitter:image" content="{{ config('marketing.site.url') }}/og-image.svg" />
        <link rel="canonical" href="{{ $canonicalUrl ?? config('marketing.site.url') }}" />
        <link rel="stylesheet" href="/marketing-static.css" data-navigate-track>
    </head>
    <body class="marketing-site">
        <div class="flex min-h-screen flex-col">
            <x-marketing.navbar :navigation="$navigation ?? config('marketing.navigation')" />

            <main class="flex-1">
                {{ $slot }}
            </main>

            <x-marketing.footer
                :navigation="$navigation ?? config('marketing.navigation')"
                :legal-links="$legalLinks ?? config('marketing.legal_links')"
                :site="$site ?? config('marketing.site')"
            />
        </div>

        @livewireScripts
        @fluxScripts
    </body>
</html>
