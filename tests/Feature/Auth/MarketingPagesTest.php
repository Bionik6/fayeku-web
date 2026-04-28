<?php

dataset('marketing pages', [
    ['/', 'Facturez proprement. Contrôlez votre trésorerie.'],
    ['/entreprises', 'Une facturation simple. Une trésorerie plus claire.'],
    ['/accountants', 'Fayeku Compta: le cockpit de vos PME clientes'],
    ['/accountant/join', 'Vous êtes un cabinet d\'expertise comptable'],
    ['/pricing', 'Profitez de 2 mois d’essai sur chaque plan'],
    ['/conformite', 'Conformité fiscale: Fayeku vous prépare à l’avance.'],
    ['/contact', 'Parlons de votre mise en place Fayeku'],
    ['/mentions-legales', 'Mentions légales'],
    ['/confidentialite', 'Politique de confidentialité'],
]);

test('marketing pages can be rendered', function (string $uri, string $text) {
    $this->get($uri)
        ->assertOk()
        ->assertSee('Fayeku')
        ->assertSee($text, false);
})->with('marketing pages');

test('login and register pages reuse the Fayeku branding', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Fayeku')
        ->assertSee('Espace sécurisé');

    $this->get(route('password.request'))
        ->assertOk()
        ->assertSee('Fayeku')
        ->assertSee('Espace sécurisé');

    $this->get(route('sme.auth.register'))
        ->assertOk()
        ->assertSee('Fayeku')
        ->assertSee('Espace sécurisé');
});
