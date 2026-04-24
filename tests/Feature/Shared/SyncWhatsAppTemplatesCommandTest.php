<?php

use App\Services\Shared\MetaTemplateFetcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => Cache::store('array')->clear());

function bindRealFetcher(): void
{
    app()->singleton(MetaTemplateFetcher::class, fn () => new MetaTemplateFetcher(
        baseUrl: 'https://graph.facebook.com',
        apiVersion: 'v21.0',
        businessAccountId: '12345',
        accessToken: 'tok',
        cacheMinutes: 60,
        cache: Cache::store('array'),
    ));
}

test('la commande réussit et liste les templates approuvés', function () {
    bindRealFetcher();

    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'data' => [
                [
                    'name' => 'fayeku_reminder_invoice_due_manual_cordial',
                    'language' => 'fr',
                    'status' => 'APPROVED',
                    'components' => [['type' => 'BODY', 'text' => 'Bonjour {{client_name}}']],
                ],
                [
                    'name' => 'fayeku_notification_quote_sent',
                    'language' => 'fr',
                    'status' => 'APPROVED',
                    'components' => [['type' => 'BODY', 'text' => 'Devis']],
                ],
            ],
        ], 200),
    ]);

    $this->artisan('whatsapp:templates:sync')
        ->expectsOutputToContain('2 template(s) approuvé(s) mis en cache.')
        ->expectsOutputToContain('fayeku_reminder_invoice_due_manual_cordial')
        ->expectsOutputToContain('fayeku_notification_quote_sent')
        ->assertExitCode(0);
});

test('la commande invalide le cache avant de refetch', function () {
    bindRealFetcher();

    Http::fake([
        'graph.facebook.com/*' => Http::sequence()
            ->push(['data' => [['name' => 't1', 'language' => 'fr', 'status' => 'APPROVED', 'components' => [['type' => 'BODY', 'text' => 'old']]]]], 200)
            ->push(['data' => [['name' => 't2', 'language' => 'fr', 'status' => 'APPROVED', 'components' => [['type' => 'BODY', 'text' => 'new']]]]], 200),
    ]);

    // Premier appel amorce le cache avec la première réponse.
    app(MetaTemplateFetcher::class)->allTemplates();

    // La commande force un refresh → deuxième réponse utilisée.
    $this->artisan('whatsapp:templates:sync')->assertExitCode(0);

    expect(app(MetaTemplateFetcher::class)->getBody('t2'))->toBe('new');
});

test('la commande retourne FAILURE et un warning quand aucun template n\'est approuvé', function () {
    bindRealFetcher();

    Http::fake([
        'graph.facebook.com/*' => Http::response(['data' => []], 200),
    ]);

    $this->artisan('whatsapp:templates:sync')
        ->expectsOutputToContain('Aucun template approuvé récupéré')
        ->assertExitCode(1);
});

test('la commande retourne FAILURE si credentials absents (aucun appel HTTP)', function () {
    app()->singleton(MetaTemplateFetcher::class, fn () => new MetaTemplateFetcher(
        baseUrl: 'https://graph.facebook.com',
        apiVersion: 'v21.0',
        businessAccountId: null,
        accessToken: null,
        cacheMinutes: 60,
        cache: Cache::store('array'),
    ));

    Http::fake();

    $this->artisan('whatsapp:templates:sync')->assertExitCode(1);
    Http::assertNothingSent();
});
