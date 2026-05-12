<?php

declare(strict_types=1);

dataset('seo_landing_slugs', [
    'logiciel-facturation-senegal',
    'logiciel-cabinet-comptable-senegal',
    'facturation-electronique-dgid',
    'logiciel-devis-facture-senegal',
    'relance-facture-impayee-whatsapp',
    'alternative-sage-senegal',
]);

it('serves each SEO landing page with the keyword in the H1', function (string $slug) {
    $landing = config('marketing-seo.landings.'.$slug);

    $response = $this->get('/'.$slug);

    $response->assertOk();
    $response->assertSee($landing['h1'], false);
    $response->assertSee($landing['meta_title'], false);
})->with('seo_landing_slugs');

it('renders FAQPage JSON-LD on each SEO landing page', function (string $slug) {
    $response = $this->get('/'.$slug);

    $response->assertOk();
    expect($response->getContent())->toContain('"@type":"FAQPage"');
})->with('seo_landing_slugs');

it('returns 404 for unknown SEO slugs handled by the controller', function () {
    $this->get('/landing-inexistante-xyz')->assertNotFound();
});
