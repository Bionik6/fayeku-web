<?php

use App\Enums\Compta\LeadSource;
use App\Mail\Compta\AccountantLeadReceivedMail;
use App\Mail\Compta\NewAccountantLeadAlertMail;
use App\Models\Compta\AccountantLead;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Mime\Email;

uses(RefreshDatabase::class);

function validPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Mamadou',
        'last_name' => 'Diallo',
        'firm' => 'Cabinet Diallo & Associés',
        'email' => 'mamadou@diallo-compta.sn',
        'country_code' => 'SN',
        'phone' => '771234567',
        'region' => 'Dakar',
        'portfolio_size' => '1 à 20 dossiers',
        'message' => 'Centraliser les factures de mes clients PME.',
    ], $overrides);
}

function postJoin(array $payload): TestResponse
{
    return test()->post(route('marketing.accountants.join.store'), $payload);
}

test('GET /accountant/join renders the form', function () {
    $this->get(route('marketing.accountants.join'))
        ->assertOk()
        ->assertSee('Vous êtes un cabinet d\'expertise comptable', false)
        ->assertSee('Prénom')
        ->assertSee('Email')
        ->assertSee('attendez-vous de Fayeku');
});

test('POST with valid data redirects with success message', function () {
    Mail::fake();

    postJoin(validPayload())
        ->assertRedirect(route('marketing.accountants.join'))
        ->assertSessionHas('success');
});

test('POST persists a cabinet lead with status new', function () {
    Mail::fake();

    postJoin(validPayload());

    $lead = AccountantLead::sole();
    expect($lead->firm)->toBe('Cabinet Diallo & Associés');
    expect($lead->email)->toBe('mamadou@diallo-compta.sn');
    expect($lead->status)->toBe('new');
    expect($lead->source)->toBe(LeadSource::Organic);
});

test('POST notifies team admins and acknowledges the cabinet', function () {
    Mail::fake();
    config()->set('fayeku.admin_emails', ['ops@fayeku.test', 'sales@fayeku.test']);

    postJoin(validPayload());

    Mail::assertSent(NewAccountantLeadAlertMail::class, fn ($mail) => $mail->hasTo('ops@fayeku.test'));
    Mail::assertSent(NewAccountantLeadAlertMail::class, fn ($mail) => $mail->hasTo('sales@fayeku.test'));
    Mail::assertSent(
        AccountantLeadReceivedMail::class,
        fn ($mail) => $mail->hasTo('mamadou@diallo-compta.sn'),
    );
});

test('validation failure does not persist a lead', function () {
    Mail::fake();

    postJoin(validPayload(['email' => '']));

    expect(AccountantLead::count())->toBe(0);
    Mail::assertNothingSent();
});

test('phone is stored normalized as country prefix + digits', function () {
    Mail::fake();

    postJoin(validPayload(['phone' => '77 445 76 35', 'country_code' => 'SN']));

    expect(AccountantLead::sole()->phone)->toBe('+221774457635');
});

test('phone with leading zero is normalized without it', function () {
    Mail::fake();

    postJoin(validPayload(['phone' => '0774457635', 'country_code' => 'SN']));

    expect(AccountantLead::sole()->phone)->toBe('+221774457635');
});

test('phone already in international form stays canonical', function () {
    Mail::fake();

    postJoin(validPayload(['phone' => '+221 77 445 76 35', 'country_code' => 'SN']));

    expect(AccountantLead::sole()->phone)->toBe('+221774457635');
});

test('Ivorian phone is normalized with +225 prefix', function () {
    Mail::fake();

    postJoin(validPayload(['phone' => '0102030405', 'country_code' => 'CI']));

    // Le 0 de tête local est supprimé (convention AuthService::normalizePhone).
    expect(AccountantLead::sole()->phone)->toBe('+225102030405');
});

test('mails are sent synchronously, not queued', function () {
    Mail::fake();
    config()->set('fayeku.admin_emails', ['ops@fayeku.test']);

    postJoin(validPayload());

    Mail::assertNotQueued(NewAccountantLeadAlertMail::class);
    Mail::assertNotQueued(AccountantLeadReceivedMail::class);
    Mail::assertSent(NewAccountantLeadAlertMail::class);
    Mail::assertSent(AccountantLeadReceivedMail::class);
});

test('after success, the form page renders an auto-dismissing toast', function () {
    Mail::fake();

    postJoin(validPayload());

    // Second POST avec email + phone distincts pour ne pas heurter les règles
    // d'unicité — l'objectif est seulement de re-render la page avec le flash.
    test()->followingRedirects()
        ->post(route('marketing.accountants.join.store'), validPayload([
            'email' => 'second@cabinet.sn',
            'phone' => '780000001',
        ]))
        ->assertOk()
        ->assertSee('data-testid="lead-toast"', false)
        ->assertSee('setTimeout(() => show = false, 8000)', false)
        ->assertSee('Votre demande a bien été reçue', false);
});

test('without success flash, the toast is not rendered', function () {
    test()->get(route('marketing.accountants.join'))
        ->assertOk()
        ->assertDontSee('data-testid="lead-toast"', false);
});

test('POST is rejected when an existing user already owns the phone', function () {
    Mail::fake();
    User::factory()->create([
        'phone' => '+221774457635',
        'email' => 'someone-else@cabinet.sn',
    ]);

    postJoin(validPayload(['phone' => '774457635', 'country_code' => 'SN']))
        ->assertSessionHasErrors(['phone']);

    expect(AccountantLead::count())->toBe(0);
    Mail::assertNothingSent();
});

test('POST is rejected when phone match happens after normalization', function () {
    Mail::fake();
    User::factory()->create(['phone' => '+221774457635']);

    // Saisi avec espaces et un 0 de tête local — doit normaliser et matcher.
    postJoin(validPayload(['phone' => '0 77 445 76 35', 'country_code' => 'SN']))
        ->assertSessionHasErrors(['phone']);

    expect(AccountantLead::count())->toBe(0);
});

test('POST is rejected when an existing user already owns the email', function () {
    Mail::fake();
    User::factory()->create([
        'phone' => '+221770000000',
        'email' => 'taken@cabinet.sn',
    ]);

    postJoin(validPayload(['email' => 'taken@cabinet.sn']))
        ->assertSessionHasErrors(['email']);

    expect(AccountantLead::count())->toBe(0);
    Mail::assertNothingSent();
});

test('email uniqueness is case-insensitive', function () {
    Mail::fake();
    User::factory()->create([
        'phone' => '+221770000000',
        'email' => 'taken@cabinet.sn',
    ]);

    postJoin(validPayload(['email' => 'TAKEN@Cabinet.SN']))
        ->assertSessionHasErrors(['email']);

    expect(AccountantLead::count())->toBe(0);
});

test('a different user with no email/phone overlap does not block the lead', function () {
    Mail::fake();
    User::factory()->create([
        'phone' => '+221770000000',
        'email' => 'other@cabinet.sn',
    ]);

    postJoin(validPayload())
        ->assertRedirect(route('marketing.accountants.join'))
        ->assertSessionHas('success');

    expect(AccountantLead::count())->toBe(1);
});

test('email is stored lowercase (normalized in prepareForValidation)', function () {
    Mail::fake();

    postJoin(validPayload(['email' => '  Mamadou@DIALLO-Compta.SN  ']));

    expect(AccountantLead::sole()->email)->toBe('mamadou@diallo-compta.sn');
});

test('POST is rejected when an active lead already owns the email', function () {
    Mail::fake();
    AccountantLead::create(validPayload([
        'email' => 'taken@cabinet.sn',
        'phone' => '+221770000000',
    ]) + ['source' => 'organic']);

    postJoin(validPayload(['email' => 'taken@cabinet.sn']))
        ->assertSessionHasErrors(['email']);

    expect(AccountantLead::count())->toBe(1);
    Mail::assertNothingSent();
});

test('POST is rejected when an active lead already owns the phone', function () {
    Mail::fake();
    AccountantLead::create(validPayload([
        'email' => 'someone@cabinet.sn',
        'phone' => '+221774457635',
    ]) + ['source' => 'organic']);

    postJoin(validPayload(['phone' => '774457635', 'country_code' => 'SN']))
        ->assertSessionHasErrors(['phone']);

    expect(AccountantLead::count())->toBe(1);
});

test('a rejected lead does not block a fresh candidature with the same email', function () {
    Mail::fake();
    AccountantLead::create(validPayload([
        'email' => 'comeback@cabinet.sn',
        'phone' => '+221779999999',
    ]) + ['source' => 'organic', 'status' => 'rejected', 'rejected_at' => now()]);

    postJoin(validPayload([
        'email' => 'comeback@cabinet.sn',
        'phone' => '770000001',
        'country_code' => 'SN',
    ]))
        ->assertRedirect(route('marketing.accountants.join'))
        ->assertSessionHas('success');

    expect(AccountantLead::count())->toBe(2);
});

test('a rejected lead does not block a fresh candidature with the same phone', function () {
    Mail::fake();
    AccountantLead::create(validPayload([
        'email' => 'old@cabinet.sn',
        'phone' => '+221774457635',
    ]) + ['source' => 'organic', 'status' => 'rejected', 'rejected_at' => now()]);

    postJoin(validPayload([
        'email' => 'fresh@cabinet.sn',
        'phone' => '774457635',
        'country_code' => 'SN',
    ]))
        ->assertRedirect(route('marketing.accountants.join'))
        ->assertSessionHas('success');

    expect(AccountantLead::count())->toBe(2);
});

test('lead-self uniqueness on email is case-insensitive', function () {
    Mail::fake();
    AccountantLead::create(validPayload([
        'email' => 'taken@cabinet.sn',
        'phone' => '+221770000000',
    ]) + ['source' => 'organic']);

    postJoin(validPayload(['email' => 'TAKEN@Cabinet.SN']))
        ->assertSessionHasErrors(['email']);

    expect(AccountantLead::count())->toBe(1);
});

test('an activated lead with no users record (edge case) still blocks resubmission', function () {
    // Garde-fou : si pour une raison quelconque l'activator a échoué entre la
    // création du lead 'activated' et la création du user, on doit quand même
    // bloquer la nouvelle soumission.
    Mail::fake();
    AccountantLead::create(validPayload([
        'email' => 'activated@cabinet.sn',
        'phone' => '+221770000000',
    ]) + ['source' => 'organic', 'status' => 'activated', 'activated_at' => now()]);

    postJoin(validPayload(['email' => 'activated@cabinet.sn']))
        ->assertSessionHasErrors(['email']);

    expect(AccountantLead::count())->toBe(1);
});

test('end-to-end: POST renders and dispatches all 3 mailables without throwing', function () {
    // Pas de Mail::fake() : on laisse le driver `array` (configuré dans
    // phpunit.xml) pousser les mails dans le transport, ce qui exerce le
    // rendu Markdown réel et casse si le namespace `mail::` n'est pas résolu.
    config()->set('fayeku.admin_emails', ['ops@fayeku.test', 'sales@fayeku.test']);

    $response = postJoin(validPayload());

    $response->assertRedirect(route('marketing.accountants.join'))
        ->assertSessionHas('success');

    $sent = app('mailer')->getSymfonyTransport()->messages();
    expect($sent)->toHaveCount(3);

    $recipients = collect($sent)
        ->map(fn ($m) => $m->getEnvelope()->getRecipients()[0]->getAddress())
        ->all();

    expect($recipients)->toContain('ops@fayeku.test');
    expect($recipients)->toContain('sales@fayeku.test');
    expect($recipients)->toContain('mamadou@diallo-compta.sn');

    // Le From-name doit être branded Fayeku (driven by APP_NAME).
    foreach ($sent as $message) {
        $from = $message->getEnvelope()->getSender();
        expect($from->getName())->toBe('Fayeku');
    }

    // Aucun mail ne doit transporter le logo en pièce jointe : Gmail
    // afficherait l'image inline ET en attachment scanné en bas du mail. Le
    // logo doit être servi via URL publique (config('mail.logo_url')).
    foreach ($sent as $message) {
        $email = $message->getOriginalMessage();
        expect($email)->toBeInstanceOf(Email::class);

        $rawHeader = $email->toString();
        expect($rawHeader)
            ->not->toContain('Content-Type: image/png')
            ->not->toContain('Content-Disposition: inline')
            ->not->toContain('Content-Disposition: attachment')
            ->not->toContain('multipart/related');

        // Le HTML rendu référence bien le logo via URL absolue, jamais via cid:.
        $html = (string) $email->getHtmlBody();
        expect($html)
            ->toContain('src="'.config('mail.logo_url').'"')
            ->not->toContain('cid:');
    }
});

test('POST without first_name fails validation', function () {
    postJoin(validPayload(['first_name' => '']))
        ->assertSessionHasErrors(['first_name']);
});

test('POST without last_name fails validation', function () {
    postJoin(validPayload(['last_name' => '']))
        ->assertSessionHasErrors(['last_name']);
});

test('POST without firm fails validation', function () {
    postJoin(validPayload(['firm' => '']))
        ->assertSessionHasErrors(['firm']);
});

test('POST without email fails validation', function () {
    postJoin(validPayload(['email' => '']))
        ->assertSessionHasErrors(['email']);
});

test('POST with invalid email fails validation', function () {
    postJoin(validPayload(['email' => 'not-an-email']))
        ->assertSessionHasErrors(['email']);
});

test('POST without phone fails validation', function () {
    postJoin(validPayload(['phone' => '']))
        ->assertSessionHasErrors(['phone']);
});

test('POST without region fails validation', function () {
    postJoin(validPayload(['region' => '']))
        ->assertSessionHasErrors(['region']);
});

test('POST with invalid region fails validation', function () {
    postJoin(validPayload(['region' => 'Paris']))
        ->assertSessionHasErrors(['region']);
});

test('POST without portfolio_size fails validation', function () {
    postJoin(validPayload(['portfolio_size' => '']))
        ->assertSessionHasErrors(['portfolio_size']);
});

test('POST with invalid portfolio_size fails validation', function () {
    postJoin(validPayload(['portfolio_size' => '999 dossiers']))
        ->assertSessionHasErrors(['portfolio_size']);
});

test('POST without message fails validation', function () {
    postJoin(validPayload(['message' => '']))
        ->assertSessionHasErrors(['message']);
});

test('POST with message too short fails validation', function () {
    postJoin(validPayload(['message' => 'Court']))
        ->assertSessionHasErrors(['message']);
});
