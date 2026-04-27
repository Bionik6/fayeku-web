<?php

use App\Mail\Compta\AccountantActivationLinkMail;
use App\Mail\Compta\AccountantLeadReceivedMail;
use App\Mail\Compta\NewAccountantLeadAlertMail;
use App\Models\Compta\AccountantLead;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeRenderableLead(): AccountantLead
{
    return AccountantLead::create([
        'first_name' => 'Mamadou',
        'last_name' => 'Diallo',
        'firm' => 'Cabinet Diallo & Associés',
        'email' => 'mamadou@diallo-compta.sn',
        'country_code' => 'SN',
        'phone' => '+221774457635',
        'region' => 'Dakar',
        'portfolio_size' => '1 à 20 dossiers',
        'message' => 'Centraliser les factures de mes clients PME.',
    ]);
}

test('NewAccountantLeadAlertMail compiles and contains lead details', function () {
    $lead = makeRenderableLead();

    $html = (new NewAccountantLeadAlertMail($lead))->render();

    expect($html)
        ->toContain('Cabinet Diallo &amp; Associés')
        ->toContain('Mamadou Diallo')
        ->toContain('mamadou@diallo-compta.sn')
        ->toContain('+221774457635')
        ->toContain('Dakar')
        ->toContain('1 à 20 dossiers')
        ->toContain('Centraliser les factures');
});

test('NewAccountantLeadAlertMail subject is built from the lead firm', function () {
    $lead = makeRenderableLead();

    expect((new NewAccountantLeadAlertMail($lead))->envelope()->subject)
        ->toBe('Nouvelle demande cabinet: Cabinet Diallo & Associés');
});

test('AccountantLeadReceivedMail compiles and greets the lead by first name', function () {
    $lead = makeRenderableLead();

    $html = (new AccountantLeadReceivedMail($lead))->render();

    expect($html)
        ->toContain('Bonjour Mamadou')
        ->toContain('Cabinet Diallo &amp; Associés')
        ->toContain('sous 24 heures');
});

test('AccountantLeadReceivedMail has the expected subject', function () {
    $lead = makeRenderableLead();

    expect((new AccountantLeadReceivedMail($lead))->envelope()->subject)
        ->toBe('Fayeku Compta - Nous avons bien reçu votre demande');
});

test('AccountantActivationLinkMail compiles with a clickable activation link', function () {
    $lead = makeRenderableLead();
    $token = 'tok_'.bin2hex(random_bytes(8));

    $mail = new AccountantActivationLinkMail($lead, $token);
    $html = $mail->render();

    $expectedUrl = route('accountant.activation', $token);

    expect($html)
        ->toContain($expectedUrl)
        ->toContain('Mamadou')
        ->toContain('Cabinet Diallo &amp; Associés')
        ->toContain('7 jours');
});

test('AccountantActivationLinkMail has the expected subject', function () {
    $mail = new AccountantActivationLinkMail(makeRenderableLead(), 'token');

    expect($mail->envelope()->subject)
        ->toBe('Fayeku Compta - Activez votre accès');
});

test('mail header points to the configured public logo URL', function () {
    config()->set('mail.logo_url', 'https://fayeku.sn/apple-touch-icon.png');

    $html = (new NewAccountantLeadAlertMail(makeRenderableLead()))->render();

    expect($html)
        ->toContain('src="https://fayeku.sn/apple-touch-icon.png"')
        ->toContain('alt="Fayeku"');
});

test('mail header does not leak fragile image references', function () {
    // Garde-fou contre les approches qui ne marchent pas chez Gmail :
    //   - cid:* → l'image apparaît aussi en attachment scanné (UX cassée)
    //   - data:image/...;base64 → strippé par Gmail pour raisons de sécurité
    //   - localhost / 127.0.0.1 → le proxy d'image Google ne sait pas atteindre
    config()->set('mail.logo_url', 'https://fayeku.sn/apple-touch-icon.png');

    $html = (new NewAccountantLeadAlertMail(makeRenderableLead()))->render();

    expect($html)
        ->not->toContain('cid:fayeku-logo')
        ->not->toContain('data:image/png;base64,')
        ->not->toContain('src="http://localhost')
        ->not->toContain('src="http://127.0.0.1')
        ->not->toContain('laravel.com/img/notification-logo')
        ->not->toContain('Laravel Logo');
});

test('logo block disappears entirely when mail.logo_url is empty', function () {
    config()->set('mail.logo_url', '');

    $html = (new NewAccountantLeadAlertMail(makeRenderableLead()))->render();

    expect($html)->not->toContain('alt="Fayeku"');
    expect($html)->not->toContain('<img src="');
    // La marque texte reste affichée comme fallback.
    expect($html)->toContain('Fayeku');
});

test('no accountant mailable defines an attachments() method', function () {
    // Plus de pièce jointe inline (CID) — Gmail les affichait à tort en
    // attachment scanné en bas du mail. Le logo est servi via URL publique
    // (config('mail.logo_url')), pas via une pièce jointe.
    foreach ([NewAccountantLeadAlertMail::class, AccountantLeadReceivedMail::class, AccountantActivationLinkMail::class] as $class) {
        expect(method_exists($class, 'attachments'))->toBeFalse(
            "{$class} ne devrait pas définir attachments() (logo servi via URL publique).",
        );
    }
});

test('mail footer carries the Fayeku brand and tagline', function () {
    $html = (new AccountantLeadReceivedMail(makeRenderableLead()))->render();

    expect($html)
        ->toContain('Facturation, relances WhatsApp')
        ->toContain('PME du Sénégal');
});

test('mail uses the Fayeku primary green theme color', function () {
    $html = (new AccountantLeadReceivedMail(makeRenderableLead()))->render();

    // Le thème CSS est inliné par le renderer Markdown — on vérifie que la
    // couleur primary Fayeku (#024d4d) apparaît bien dans le HTML rendu.
    expect(strtolower($html))->toContain('#024d4d');
});

test('the activation button uses the Fayeku primary background', function () {
    $html = (new AccountantActivationLinkMail(makeRenderableLead(), 'tok'))->render();

    // Le button "Activer mon accès" doit utiliser la couleur primaire Fayeku.
    expect(strtolower($html))->toContain('#024d4d');
});

test('the activation button text renders in white on the dark background', function () {
    $html = (new AccountantActivationLinkMail(makeRenderableLead(), 'tok'))->render();

    // On extrait l'<a class="button ..."> rendu (CSS inliné par le pipeline
    // Markdown) et on vérifie que la règle `color` du button résout au blanc,
    // pas à l'accent vert (qui passait inaperçu sur fond #024d4d).
    preg_match('/<a[^>]*class="[^"]*button-primary[^"]*"[^>]*style="([^"]*)"/i', $html, $matches);
    expect($matches[1] ?? null)->not->toBeNull();

    $style = strtolower($matches[1]);
    expect($style)
        ->toContain('color: #ffffff')
        ->not->toContain('color: #10b75c');
});

test('config app name resolves to Fayeku, driving the From-name on outgoing mail', function () {
    expect(config('app.name'))->toBe('Fayeku');
    expect(config('mail.from.name'))->toBe('Fayeku');
});
