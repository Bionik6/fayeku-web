@php
    $siteUrl = rtrim((string) config('marketing.site.url'), '/');
    $siteName = (string) config('marketing.site.name');
    $siteDescription = (string) config('marketing.site.description');
    $logoUrl = $siteUrl.'/logo.png';
    $social = (array) config('marketing.site.social', []);
    $phone = (string) config('marketing.site.contact.phone', '');
    $email = (string) config('marketing.site.contact.email', '');

    $pageType ??= null;
    $breadcrumbs ??= [];
    $faqItems ??= [];

    $graph = [];

    $graph[] = [
        '@type' => 'Organization',
        '@id' => $siteUrl.'#organization',
        'name' => $siteName,
        'url' => $siteUrl,
        'logo' => $logoUrl,
        'description' => $siteDescription,
        'sameAs' => array_values(array_filter([
            $social['linkedin'] ?? null,
            $social['x'] ?? null,
            $social['whatsapp'] ?? null,
        ])),
        'contactPoint' => [
            '@type' => 'ContactPoint',
            'telephone' => $phone,
            'email' => $email,
            'contactType' => 'sales',
            'areaServed' => ['SN'],
            'availableLanguage' => ['French'],
        ],
        'address' => [
            '@type' => 'PostalAddress',
            'addressLocality' => 'Dakar',
            'addressCountry' => 'SN',
        ],
    ];

    $graph[] = [
        '@type' => 'WebSite',
        '@id' => $siteUrl.'#website',
        'url' => $siteUrl,
        'name' => $siteName,
        'description' => $siteDescription,
        'publisher' => ['@id' => $siteUrl.'#organization'],
        'inLanguage' => 'fr-SN',
    ];

    if (in_array($pageType, ['home', 'enterprises', 'accountants', 'seo-software'], true)) {
        $graph[] = [
            '@type' => 'SoftwareApplication',
            '@id' => $siteUrl.'#software',
            'name' => $siteName,
            'operatingSystem' => 'Web',
            'applicationCategory' => 'BusinessApplication',
            'description' => $siteDescription,
            'url' => $siteUrl,
            'inLanguage' => 'fr-SN',
            'offers' => [
                '@type' => 'Offer',
                'price' => '10000',
                'priceCurrency' => 'XOF',
                'priceValidUntil' => now()->addYear()->toDateString(),
                'availability' => 'https://schema.org/InStock',
                'url' => $siteUrl.'/pricing',
            ],
            'publisher' => ['@id' => $siteUrl.'#organization'],
        ];
    }

    if ($pageType === 'pricing') {
        foreach ((array) config('marketing.pricing_plans', []) as $plan) {
            $priceMatch = [];
            preg_match('/(\d[\d\s]*)/u', (string) ($plan['price'] ?? ''), $priceMatch);
            $price = isset($priceMatch[1]) ? (int) preg_replace('/\D/', '', $priceMatch[1]) : null;
            $graph[] = array_filter([
                '@type' => 'Product',
                'name' => $siteName.' '.($plan['name'] ?? ''),
                'description' => $plan['promise'] ?? null,
                'brand' => ['@type' => 'Brand', 'name' => $siteName],
                'offers' => array_filter([
                    '@type' => 'Offer',
                    'price' => $price,
                    'priceCurrency' => 'XOF',
                    'availability' => 'https://schema.org/InStock',
                    'url' => $siteUrl.'/pricing',
                ]),
            ]);
        }
    }

    if (! empty($faqItems)) {
        $graph[] = [
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn ($item) => [
                '@type' => 'Question',
                'name' => $item['question'] ?? '',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $item['answer'] ?? '',
                ],
            ], $faqItems),
        ];
    }

    if (! empty($breadcrumbs)) {
        $graph[] = [
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_values(array_map(fn ($crumb, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $crumb['name'] ?? '',
                'item' => $crumb['url'] ?? null,
            ], $breadcrumbs, array_keys($breadcrumbs))),
        ];
    }

    if ($pageType === 'contact') {
        $graph[] = [
            '@type' => 'LocalBusiness',
            '@id' => $siteUrl.'#localbusiness',
            'name' => $siteName,
            'url' => $siteUrl,
            'telephone' => $phone,
            'email' => $email,
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => 'Dakar',
                'addressCountry' => 'SN',
            ],
            'priceRange' => 'XOF 10 000 – 70 000+',
        ];
    }

    $jsonLd = [
        '@context' => 'https://schema.org',
        '@graph' => $graph,
    ];
@endphp
<script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
