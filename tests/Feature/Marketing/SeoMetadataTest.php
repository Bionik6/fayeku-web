<?php

declare(strict_types=1);

dataset('marketing_routes', [
    'home' => ['/'],
    'enterprises' => ['/entreprises'],
    'accountants' => ['/accountants'],
    'pricing' => ['/pricing'],
    'compliance' => ['/conformite'],
    'contact' => ['/contact'],
    'legal' => ['/mentions-legales'],
    'privacy' => ['/confidentialite'],
]);

it('serves marketing pages with required SEO tags', function (string $path) {
    $response = $this->get($path);

    $response->assertOk();
    $response->assertSee('<title>', false);
    $response->assertSee('name="description"', false);
    $response->assertSee('rel="canonical"', false);
    $response->assertSee('property="og:title"', false);
    $response->assertSee('property="og:image"', false);
    $response->assertSee('og-image.png', false);
    $response->assertSee('application/ld+json', false);
})->with('marketing_routes');

it('does not emit a noindex meta tag on indexable marketing pages', function (string $path) {
    $response = $this->get($path);

    expect($response->getContent())
        ->not->toContain('content="noindex');
})->with('marketing_routes');

it('emits noindex on the accountant join lead form', function () {
    $response = $this->get('/accountant/join');

    $response->assertOk();
    expect($response->getContent())->toContain('noindex');
});

it('has a unique H1 per indexable marketing page', function (string $path) {
    $response = $this->get($path);

    $response->assertOk();
    $count = substr_count($response->getContent(), '<h1');

    expect($count)->toBe(1, "Page {$path} should have exactly one <h1>, found {$count}");
})->with([
    'enterprises' => ['/entreprises'],
    'accountants' => ['/accountants'],
    'pricing' => ['/pricing'],
    'compliance' => ['/conformite'],
    'contact' => ['/contact'],
]);

it('renders Organization JSON-LD on home', function () {
    $response = $this->get('/');

    $response->assertOk();
    $content = $response->getContent();

    expect($content)->toContain('"@type":"Organization"');
    expect($content)->toContain('"@type":"WebSite"');
});

it('renders FAQPage JSON-LD on pricing', function () {
    $response = $this->get('/pricing');

    $response->assertOk();
    expect($response->getContent())->toContain('"@type":"FAQPage"');
});

it('renders Product JSON-LD on pricing', function () {
    $response = $this->get('/pricing');

    $response->assertOk();
    expect($response->getContent())->toContain('"@type":"Product"');
});

it('renders LocalBusiness JSON-LD on contact', function () {
    $response = $this->get('/contact');

    $response->assertOk();
    expect($response->getContent())->toContain('"@type":"LocalBusiness"');
});

it('renders BreadcrumbList JSON-LD on inner pages', function () {
    $response = $this->get('/entreprises');

    $response->assertOk();
    expect($response->getContent())->toContain('"@type":"BreadcrumbList"');
});
