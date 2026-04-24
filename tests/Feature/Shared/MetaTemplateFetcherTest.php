<?php

use App\Services\Shared\MetaTemplateFetcher;
use App\Services\Shared\WhatsAppTemplateCatalog;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function makeFetcher(): MetaTemplateFetcher
{
    return new MetaTemplateFetcher(
        baseUrl: 'https://graph.facebook.com',
        apiVersion: 'v21.0',
        businessAccountId: '998877665544',
        accessToken: 'test-token',
        cacheMinutes: 60 * 24,
        cache: Cache::store('array'),
    );
}

beforeEach(fn () => Cache::store('array')->clear());

test('getBody récupère le body depuis Graph API et le cache', function () {
    Http::fake([
        'graph.facebook.com/*/message_templates*' => Http::response([
            'data' => [
                [
                    'name' => 'fayeku_reminder_invoice_due_manual_cordial',
                    'language' => 'fr',
                    'status' => 'APPROVED',
                    'components' => [
                        ['type' => 'HEADER', 'format' => 'TEXT', 'text' => 'Rappel'],
                        ['type' => 'BODY', 'text' => 'Bonjour {{client_name}}, facture {{invoice_number}}.'],
                        ['type' => 'FOOTER', 'text' => 'Ne pas répondre'],
                    ],
                ],
                [
                    'name' => 'template_rejete',
                    'language' => 'fr',
                    'status' => 'REJECTED',
                    'components' => [['type' => 'BODY', 'text' => 'Rejected']],
                ],
            ],
        ], 200),
    ]);

    $fetcher = makeFetcher();

    $body = $fetcher->getBody('fayeku_reminder_invoice_due_manual_cordial');

    expect($body)->toBe('Bonjour {{client_name}}, facture {{invoice_number}}.');

    // Deuxième appel : doit servir depuis le cache sans re-fetch.
    $fetcher->getBody('fayeku_reminder_invoice_due_manual_cordial');

    Http::assertSentCount(1);

    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), '/v21.0/998877665544/message_templates')
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request['fields'] === 'name,language,status,components'
            && (int) $request['limit'] === 100;
    });
});

test('getBody ignore les templates non APPROVED', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'data' => [
                [
                    'name' => 'pending_template',
                    'language' => 'fr',
                    'status' => 'PENDING',
                    'components' => [['type' => 'BODY', 'text' => 'Pending']],
                ],
            ],
        ], 200),
    ]);

    expect(makeFetcher()->getBody('pending_template'))->toBeNull();
});

test('getBody renvoie null si la requete Meta echoue', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['error' => 'oops'], 500),
    ]);

    expect(makeFetcher()->getBody('anything'))->toBeNull();
});

test('refresh() invalide le cache', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::sequence()
            ->push(['data' => [['name' => 't', 'language' => 'fr', 'status' => 'APPROVED', 'components' => [['type' => 'BODY', 'text' => 'v1']]]]], 200)
            ->push(['data' => [['name' => 't', 'language' => 'fr', 'status' => 'APPROVED', 'components' => [['type' => 'BODY', 'text' => 'v2']]]]], 200),
    ]);

    $fetcher = makeFetcher();

    expect($fetcher->getBody('t'))->toBe('v1');

    $fetcher->refresh();

    expect($fetcher->getBody('t'))->toBe('v2');
    Http::assertSentCount(2);
});

test('getBody renvoie [] si credentials absents (pas d\'appel HTTP)', function () {
    Http::fake();

    $fetcher = new MetaTemplateFetcher(
        baseUrl: 'https://graph.facebook.com',
        apiVersion: 'v21.0',
        businessAccountId: null,
        accessToken: null,
        cacheMinutes: 60,
        cache: Cache::store('array'),
    );

    expect($fetcher->getBody('whatever'))->toBeNull();
    Http::assertNothingSent();
});

// ─── Intégration avec WhatsAppTemplateCatalog ──────────────────────────────

test('WhatsAppTemplateCatalog::render utilise le body Meta quand disponible', function () {
    Cache::store('array')->clear();

    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'data' => [[
                'name' => 'fayeku_reminder_invoice_due_manual_cordial',
                'language' => 'fr',
                'status' => 'APPROVED',
                'components' => [['type' => 'BODY', 'text' => 'META VERSION — {{client_name}}']],
            ]],
        ], 200),
    ]);

    $fetcher = makeFetcher();
    $catalog = new WhatsAppTemplateCatalog($fetcher);

    $rendered = $catalog->render('reminder_manual_cordial', ['client_name' => 'Dakar Pharma']);

    expect($rendered)->toBe('META VERSION — Dakar Pharma');
});

test('WhatsAppTemplateCatalog::render fallback sur le body local si Meta indisponible', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['error' => 'down'], 503),
    ]);

    $catalog = new WhatsAppTemplateCatalog(makeFetcher());

    // Le body local utilise la syntaxe { } (simple brace).
    $rendered = $catalog->render('reminder_manual_cordial', [
        'client_name' => 'Dakar Pharma',
        'company_name' => 'Sow BTP',
        'invoice_number' => 'FAC-001',
        'invoice_amount' => '250 000 FCFA',
        'due_date' => '15 avril 2026',
        'sender_signature' => "L'équipe Sow BTP",
    ]);

    expect($rendered)
        ->toContain('Dakar Pharma')
        ->toContain('FAC-001')
        ->toContain("L'équipe Sow BTP");
});
