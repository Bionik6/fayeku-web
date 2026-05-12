@php
    $siteName = (string) config('marketing.site.name');
    $siteUrl = rtrim((string) config('marketing.site.url'), '/');
    $siteDescription = (string) config('marketing.site.description');
    $siteLocale = (string) config('marketing.site.locale', 'fr_SN');

    $title = $metaTitle ?? $siteName;
    $description = $metaDescription ?? $siteDescription;
    $canonical = $canonicalUrl ?? $siteUrl;
    $ogImage = $siteUrl.'/og-image.png';
    $noindex = $noindex ?? false;
@endphp
<meta name="description" content="{{ $description }}" />
<meta name="robots" content="{{ $noindex ? 'noindex, nofollow' : 'index, follow, max-image-preview:large' }}" />
<link rel="canonical" href="{{ $canonical }}" />

<link rel="alternate" hreflang="fr-SN" href="{{ $canonical }}" />
<link rel="alternate" hreflang="fr" href="{{ $canonical }}" />
<link rel="alternate" hreflang="x-default" href="{{ $canonical }}" />

<meta property="og:title" content="{{ $title }}" />
<meta property="og:description" content="{{ $description }}" />
<meta property="og:url" content="{{ $canonical }}" />
<meta property="og:site_name" content="{{ $siteName }}" />
<meta property="og:locale" content="{{ $siteLocale }}" />
<meta property="og:type" content="website" />
<meta property="og:image" content="{{ $ogImage }}" />
<meta property="og:image:width" content="1200" />
<meta property="og:image:height" content="630" />
<meta property="og:image:alt" content="{{ $siteName }} — {{ $description }}" />

<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="{{ $title }}" />
<meta name="twitter:description" content="{{ $description }}" />
<meta name="twitter:image" content="{{ $ogImage }}" />

@includeWhen(filled(config('marketing.verification.google')), 'partials.verification-google')
@includeWhen(filled(config('marketing.verification.bing')), 'partials.verification-bing')

@include('partials.structured-data', [
    'pageType' => $pageType ?? null,
    'breadcrumbs' => $breadcrumbs ?? [],
    'faqItems' => $faqItems ?? [],
])

@if (filled(config('marketing.analytics.plausible_domain')))
    <script defer data-domain="{{ config('marketing.analytics.plausible_domain') }}" src="https://plausible.io/js/script.js"></script>
@endif

@if (filled(config('marketing.analytics.ga4_id')))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('marketing.analytics.ga4_id') }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', @json(config('marketing.analytics.ga4_id')), { anonymize_ip: true });
    </script>
@endif
