<?php

use App\Enums\PME\InvoiceStatus;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makeCompanyForPreview(): Company
{
    return Company::factory()->create(['type' => 'sme', 'name' => 'Acme Corp']);
}

function makeClientForPreview(Company $company, array $overrides = []): Client
{
    return Client::factory()->create(array_merge([
        'company_id' => $company->id,
        'name' => 'Client Test',
        'email' => null,
        'phone' => null,
    ], $overrides));
}

function makeInvoiceForPreview(Client $client): Invoice
{
    return Invoice::unguarded(fn () => Invoice::create([
        'company_id' => $client->company_id,
        'client_id' => $client->id,
        'reference' => 'FYK-FAC-TEST01',
        'status' => InvoiceStatus::Overdue->value,
        'issued_at' => now()->subDays(30),
        'due_at' => now()->subDays(10),
        'subtotal' => 200_000,
        'tax_amount' => 0,
        'total' => 200_000,
        'amount_paid' => 0,
    ]));
}

function renderPreviewSlideover(Invoice $invoice, Company $company, string $previewChannel = 'whatsapp'): string
{
    $invoice->load('client');
    $message = [
        'greeting' => 'Bonjour Client Test,',
        'body' => 'Votre facture est en attente.',
        'closing' => 'Cordialement,',
    ];

    return Blade::render(
        '<x-collection.reminder-preview-slideover
            :invoice="$invoice"
            :message="$message"
            :company="$company"
            preview-invoice-id="{{ $invoice->id }}"
            :preview-channel="$previewChannel"
        />',
        compact('invoice', 'message', 'company', 'previewChannel'),
    );
}

// ─── Canal Email ─────────────────────────────────────────────────────────────

it('affiche le canal Email quand le client a une adresse email', function () {
    $company = makeCompanyForPreview();
    $client = makeClientForPreview($company, ['email' => 'contact@example.com', 'phone' => null]);
    $invoice = makeInvoiceForPreview($client);

    $html = renderPreviewSlideover($invoice, $company);

    expect($html)
        ->toContain('Email')
        ->toContain('Canal d');
});

it("n'affiche pas le canal Email quand le client n'a pas d'email", function () {
    $company = makeCompanyForPreview();
    $client = makeClientForPreview($company, ['email' => null, 'phone' => '+221771112233']);
    $invoice = makeInvoiceForPreview($client);

    $html = renderPreviewSlideover($invoice, $company);

    expect($html)->not->toContain('>Email<');
});

// ─── Canal WhatsApp ──────────────────────────────────────────────────────────

it('affiche le canal WhatsApp quand le client a un numéro de téléphone', function () {
    $company = makeCompanyForPreview();
    $client = makeClientForPreview($company, ['email' => null, 'phone' => '+221771112233']);
    $invoice = makeInvoiceForPreview($client);

    $html = renderPreviewSlideover($invoice, $company);

    expect($html)->toContain('WhatsApp');
});

it("n'affiche pas de canal téléphone quand le client n'a pas de numéro", function () {
    $company = makeCompanyForPreview();
    $client = makeClientForPreview($company, ['email' => 'contact@example.com', 'phone' => null]);
    $invoice = makeInvoiceForPreview($client);

    $html = renderPreviewSlideover($invoice, $company);

    expect($html)->not->toContain('WhatsApp');
});

it('n\'expose jamais le canal SMS (non supporté pour les relances)', function () {
    $company = makeCompanyForPreview();
    $client = makeClientForPreview($company, [
        'email' => 'contact@example.com',
        'phone' => '+221771112233',
    ]);
    $invoice = makeInvoiceForPreview($client);

    $html = renderPreviewSlideover($invoice, $company);

    expect($html)->not->toContain('>SMS<');
});

// ─── Client avec email et téléphone ──────────────────────────────────────────

it('affiche les deux canaux WhatsApp et Email quand le client a les deux', function () {
    $company = makeCompanyForPreview();
    $client = makeClientForPreview($company, [
        'email' => 'contact@example.com',
        'phone' => '+221771112233',
    ]);
    $invoice = makeInvoiceForPreview($client);

    $html = renderPreviewSlideover($invoice, $company);

    expect($html)
        ->toContain('Email')
        ->toContain('WhatsApp');
});

// ─── Aucun canal disponible ───────────────────────────────────────────────────

it("n'affiche pas la section canal quand le client n'a ni email ni téléphone", function () {
    $company = makeCompanyForPreview();
    $client = makeClientForPreview($company, ['email' => null, 'phone' => null]);
    $invoice = makeInvoiceForPreview($client);

    $html = renderPreviewSlideover($invoice, $company);

    expect($html)->not->toContain('Canal d');
});

// ─── Canal sélectionné ───────────────────────────────────────────────────────

it('applique la classe active sur le canal sélectionné', function () {
    $company = makeCompanyForPreview();
    $client = makeClientForPreview($company, [
        'email' => 'contact@example.com',
        'phone' => '+221771112233',
    ]);
    $invoice = makeInvoiceForPreview($client);

    $html = renderPreviewSlideover($invoice, $company, previewChannel: 'email');

    expect($html)->toContain('border-primary bg-primary/5 text-primary');
});

// ─── Contenu de base ─────────────────────────────────────────────────────────

it("affiche le message d'aperçu et le bouton d'envoi", function () {
    $company = makeCompanyForPreview();
    $client = makeClientForPreview($company, ['phone' => '+221771112233']);
    $invoice = makeInvoiceForPreview($client);

    $html = renderPreviewSlideover($invoice, $company);

    expect($html)
        ->toContain('Aperçu de la relance')
        ->toContain('Envoyer maintenant')
        ->toContain('Bonjour Client Test,')
        ->toContain('FYK-FAC-TEST01');
});

// ─── Rendu WhatsApp : gras / italique ────────────────────────────────────────

it('rend *texte* en <strong> et _texte_ en <em> comme WhatsApp', function () {
    $company = makeCompanyForPreview();
    $client = makeClientForPreview($company, ['phone' => '+221771112233']);
    $invoice = makeInvoiceForPreview($client);
    $invoice->load('client');

    $message = "*Rappel — facture en attente*\n\nBonjour,\n\n_Si le paiement a déjà été effectué, merci de ne pas tenir compte de ce message._";

    $html = Blade::render(
        '<x-collection.reminder-preview-slideover
            :invoice="$invoice"
            :message="$message"
            :company="$company"
            preview-invoice-id="{{ $invoice->id }}"
        />',
        compact('invoice', 'message', 'company'),
    );

    expect($html)
        ->toContain('<strong>Rappel — facture en attente</strong>')
        ->toContain('<em>Si le paiement a déjà été effectué, merci de ne pas tenir compte de ce message.</em>');
});

// ─── Plus de toggle "Joindre PDF" ────────────────────────────────────────────

it('ne montre plus le toggle "Joindre PDF"', function () {
    $company = makeCompanyForPreview();
    $client = makeClientForPreview($company, ['phone' => '+221771112233']);
    $invoice = makeInvoiceForPreview($client);

    $html = renderPreviewSlideover($invoice, $company);

    expect($html)->not->toContain('Joindre PDF');
});

// ─── previewChannel : propriété Livewire ─────────────────────────────────────

test('collection: previewChannel est initialisé à whatsapp par défaut', function () {
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme']);
    $company->users()->attach($user->id, ['role' => 'owner']);

    Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->assertSet('previewChannel', 'whatsapp');
});

test('collection: previewChannel peut être changé via $set', function () {
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme']);
    $company->users()->attach($user->id, ['role' => 'owner']);

    Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->set('previewChannel', 'email')
        ->assertSet('previewChannel', 'email');
});
