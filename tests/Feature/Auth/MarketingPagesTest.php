<?php

use App\Mail\Marketing\ContactReceivedMail;
use App\Mail\Marketing\NewContactAlertMail;
use Illuminate\Support\Facades\Mail;

dataset('marketing pages', [
    ['/', 'Récupérez votre argent plus vite'],
    ['/entreprises', 'Facturez, suivez, encaissez'],
    ['/accountants', 'Fayeku Compta'],
    ['/accountant/join', 'Vous êtes un cabinet'],
    ['/pricing', 'Découvrez les offres'],
    ['/conformite', 'Préparé pour la'],
    ['/contact', 'Parlons de votre'],
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

    $this->get(route('register'))
        ->assertOk()
        ->assertSee('Fayeku')
        ->assertSee('Créer votre compte');
});

test('contact form validation rejects empty submissions', function () {
    Mail::fake();

    $this->from('/contact')
        ->post('/contact', [])
        ->assertRedirect('/contact')
        ->assertSessionHasErrors(['full_name', 'email', 'profile', 'message']);

    Mail::assertNothingSent();
});

test('contact form sends mails and redirects on success', function () {
    Mail::fake();
    config(['fayeku.admin_emails' => ['admin@fayeku.sn']]);

    $this->post('/contact', [
        'full_name' => 'Awa Diop',
        'company' => 'Atelier Numérique SARL',
        'email' => 'AWA@example.sn',
        'phone' => '+221 77 123 45 67',
        'profile' => 'pme',
        'message' => 'Nous voulons tester la facturation et les relances WhatsApp pour notre PME.',
    ])
        ->assertRedirect(route('marketing.contact'))
        ->assertSessionHas('success');

    Mail::assertSent(NewContactAlertMail::class, fn ($mail) => $mail->hasTo('admin@fayeku.sn'));
    Mail::assertSent(ContactReceivedMail::class, fn ($mail) => $mail->hasTo('awa@example.sn'));
});
