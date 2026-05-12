<?php

declare(strict_types=1);

it('serves the sitemap.xml with the correct content type', function () {
    $response = $this->get('/sitemap.xml');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/xml');
});

it('includes the main marketing routes in the sitemap', function () {
    $response = $this->get('/sitemap.xml');

    $body = $response->getContent();

    expect($body)
        ->toContain('https://www.fayeku.sn</loc>')
        ->toContain('https://www.fayeku.sn/entreprises')
        ->toContain('https://www.fayeku.sn/accountants')
        ->toContain('https://www.fayeku.sn/pricing')
        ->toContain('https://www.fayeku.sn/conformite')
        ->toContain('https://www.fayeku.sn/contact');
});

it('includes the SEO landing pages in the sitemap', function () {
    $response = $this->get('/sitemap.xml');

    $body = $response->getContent();

    foreach (array_keys((array) config('marketing-seo.landings', [])) as $slug) {
        expect($body)->toContain('https://www.fayeku.sn/'.$slug);
    }
});

it('produces valid XML', function () {
    $response = $this->get('/sitemap.xml');

    libxml_use_internal_errors(true);
    $loaded = simplexml_load_string($response->getContent());

    expect($loaded)->not->toBeFalse();
});

it('robots.txt references the sitemap', function () {
    $robotsPath = public_path('robots.txt');

    expect(file_exists($robotsPath))->toBeTrue();
    expect(file_get_contents($robotsPath))->toContain('Sitemap: https://www.fayeku.sn/sitemap.xml');
});
