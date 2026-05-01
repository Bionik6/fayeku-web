<?php

use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\ProposalDocumentStatus;
use App\Enums\PME\ProposalDocumentType;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Models\PME\ProposalDocument;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * VERROU — convention des URLs publiques courtes pour les PDFs.
 *
 *  Devis    → /d/{public_code}/pdf
 *  Facture  → /f/{public_code}/pdf
 *  Proforma → /p/{public_code}/pdf
 *
 * Ces URLs sont incluses telles quelles dans les SMS, WhatsApp et emails.
 * Elles doivent rester courtes pour tenir dans 160 caractères et lisibles
 * par un client sur son téléphone.
 */
function bootstrapPublicPdfDocs(): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme', 'name' => 'PME Test']);
    $company->users()->attach($user->id, ['role' => 'owner']);
    $client = Client::factory()->create(['company_id' => $company->id]);

    $quote = ProposalDocument::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'type' => ProposalDocumentType::Quote,
        'reference' => 'FYK-DEV-PUB01',
        'currency' => 'XOF',
        'status' => ProposalDocumentStatus::Sent,
        'issued_at' => now(),
        'valid_until' => now()->addDays(30),
        'subtotal' => 100_000, 'tax_amount' => 0, 'total' => 100_000,
    ]);

    $proforma = ProposalDocument::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'type' => ProposalDocumentType::Proforma,
        'reference' => 'FYK-PRO-PUB01',
        'currency' => 'XOF',
        'status' => ProposalDocumentStatus::Sent,
        'issued_at' => now(),
        'valid_until' => now()->addDays(30),
        'subtotal' => 100_000, 'tax_amount' => 0, 'total' => 100_000,
    ]);

    $invoice = Invoice::unguarded(fn () => Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'FYK-FAC-PUB01',
        'currency' => 'XOF',
        'status' => InvoiceStatus::Sent->value,
        'issued_at' => now(),
        'due_at' => now()->addDays(30),
        'subtotal' => 100_000, 'tax_amount' => 0, 'total' => 100_000,
        'amount_paid' => 0,
    ]));

    return compact('user', 'company', 'client', 'quote', 'proforma', 'invoice');
}

// ─── Convention des chemins ──────────────────────────────────────────────────

test("la route nommée 'pme.quotes.pdf' génère /d/{public_code}/pdf", function () {
    ['quote' => $q] = bootstrapPublicPdfDocs();

    $url = route('pme.quotes.pdf', $q);

    expect($url)->toContain('/d/'.$q->public_code.'/pdf')
        ->and($url)->not->toContain('/quotes/')
        ->and($url)->not->toContain('/pme/');
});

test("la route nommée 'pme.proformas.pdf' génère /p/{public_code}/pdf", function () {
    ['proforma' => $p] = bootstrapPublicPdfDocs();

    $url = route('pme.proformas.pdf', $p);

    expect($url)->toContain('/p/'.$p->public_code.'/pdf')
        ->and($url)->not->toContain('/proformas/'.$p->public_code)
        ->and($url)->not->toContain('/pme/');
});

test("la route nommée 'pme.invoices.pdf' génère /f/{public_code}/pdf", function () {
    ['invoice' => $i] = bootstrapPublicPdfDocs();

    $url = route('pme.invoices.pdf', $i);

    expect($url)->toContain('/f/'.$i->public_code.'/pdf')
        ->and($url)->not->toContain('/invoices/')
        ->and($url)->not->toContain('/pme/');
});

// ─── Accès direct via les nouvelles URLs ─────────────────────────────────────

test('GET /d/{public_code}/pdf répond 200 + Content-Type PDF (sans auth)', function () {
    ['quote' => $q] = bootstrapPublicPdfDocs();

    $response = $this->get('/d/'.$q->public_code.'/pdf');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

test('GET /p/{public_code}/pdf répond 200 + Content-Type PDF (sans auth)', function () {
    ['proforma' => $p] = bootstrapPublicPdfDocs();

    $response = $this->get('/p/'.$p->public_code.'/pdf');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

test('GET /f/{public_code}/pdf répond 200 + Content-Type PDF (sans auth)', function () {
    ['invoice' => $i] = bootstrapPublicPdfDocs();

    $response = $this->get('/f/'.$i->public_code.'/pdf');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

// ─── Anciennes URLs ne répondent plus (anti-régression) ──────────────────────

test('GET /quotes/{public_code}/pdf (ancienne route) renvoie 404', function () {
    ['quote' => $q] = bootstrapPublicPdfDocs();

    $this->get('/quotes/'.$q->public_code.'/pdf')->assertNotFound();
});

test('GET /proformas/{public_code}/pdf (ancienne route) renvoie 404', function () {
    ['proforma' => $p] = bootstrapPublicPdfDocs();

    $this->get('/proformas/'.$p->public_code.'/pdf')->assertNotFound();
});

test('GET /invoices/{public_code}/pdf (ancienne route) renvoie 404', function () {
    ['invoice' => $i] = bootstrapPublicPdfDocs();

    $this->get('/invoices/'.$i->public_code.'/pdf')->assertNotFound();
});

// ─── Templates d'envoi : les messages contiennent les nouvelles URLs ─────────

test("le template d'envoi devis utilise la nouvelle URL courte /d/...", function () {
    ['user' => $user, 'quote' => $q] = bootstrapPublicPdfDocs();

    $component = Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $q])
        ->call('openSendModal');

    $message = $component->get('sendMessage');
    $expectedUrl = route('pme.quotes.pdf', $q->public_code);

    expect($message)
        ->toContain('/d/'.$q->public_code.'/pdf')
        ->toContain($expectedUrl)
        ->not->toContain('/quotes/'.$q->public_code);
});

test("le template d'envoi proforma utilise la nouvelle URL courte /p/...", function () {
    ['user' => $user, 'proforma' => $p] = bootstrapPublicPdfDocs();

    $component = Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $p])
        ->call('openSendModal');

    $message = $component->get('sendMessage');
    $expectedUrl = route('pme.proformas.pdf', $p->public_code);

    expect($message)
        ->toContain('/p/'.$p->public_code.'/pdf')
        ->toContain($expectedUrl)
        ->not->toContain('/proformas/'.$p->public_code);
});

test("le template d'envoi facture utilise la nouvelle URL courte /f/...", function () {
    ['user' => $user, 'invoice' => $i] = bootstrapPublicPdfDocs();

    $component = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $i])
        ->call('openSendModal');

    $message = $component->get('sendMessage');
    $expectedUrl = route('pme.invoices.pdf', $i->public_code);

    expect($message)
        ->toContain('/f/'.$i->public_code.'/pdf')
        ->toContain($expectedUrl)
        ->not->toContain('/invoices/'.$i->public_code);
});

// ─── Pas de collision avec les routes authentifiées (/pme/quotes, etc.) ──────

test('chaque route PDF publique commence bien par le préfixe court attendu', function () {
    $expected = [
        'pme.quotes.pdf' => 'd/',
        'pme.proformas.pdf' => 'p/',
        'pme.invoices.pdf' => 'f/',
    ];

    foreach ($expected as $name => $prefix) {
        $route = Route::getRoutes()->getByName($name);

        expect($route)->not->toBeNull("La route nommée {$name} doit exister")
            ->and($route->uri())->toStartWith($prefix);
    }
});
