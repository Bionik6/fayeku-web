<?php

/**
 * Vérifie que le paramètre dynamique du bouton URL WhatsApp utilise
 * le bon préfixe de chemin selon le type de document :
 *
 *   Facture  → f/{code}/pdf
 *   Devis    → d/{code}/pdf
 *   Proforma → p/{code}/pdf
 *
 * Le provider WhatsApp reçoit ce paramètre et le concatène à la base URL
 * configurée dans le template Meta (ex. https://app.fayeku.com/).
 * Un mauvais préfixe produit un lien cassé dans le message WhatsApp.
 */

use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\PaymentMethod;
use App\Enums\PME\ProposalDocumentStatus;
use App\Enums\PME\ProposalDocumentType;
use App\Interfaces\Shared\WhatsAppProviderInterface;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Models\PME\Payment;
use App\Models\PME\ProposalDocument;
use App\Services\PME\WhatsAppNotificationService;
use App\Services\PME\WhatsAppReminderService;
use App\Services\Shared\WhatsAppTemplateCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makeButtonUrlCompany(array $overrides = []): Company
{
    return Company::factory()->create(array_merge([
        'type' => 'sme',
        'name' => 'Test Co',
        'plan' => 'essentiel',
    ], $overrides));
}

function makeButtonUrlClient(Company $company): Client
{
    return Client::factory()->create([
        'company_id' => $company->id,
        'phone' => '+221771112233',
    ]);
}

function makeButtonUrlInvoice(Company $company, Client $client, array $overrides = []): Invoice
{
    return Invoice::unguarded(fn () => Invoice::create(array_merge([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'FAC-BTN-'.fake()->unique()->numerify('###'),
        'currency' => 'XOF',
        'status' => InvoiceStatus::Sent->value,
        'issued_at' => now(),
        'due_at' => now()->addDays(15),
        'subtotal' => 100_000,
        'tax_amount' => 0,
        'total' => 100_000,
        'amount_paid' => 0,
    ], $overrides)));
}

/**
 * Configure le mock pour capturer le urlButtonParameter dans $captured['url'].
 * Le tableau $captured doit être passé par référence dans le closure du test.
 */
function setupButtonCapture(MockInterface $m, array &$captured): void
{
    $m->shouldReceive('sendTemplate')
        ->withArgs(function ($phone, $template, $vars, ?string $urlButton) use (&$captured) {
            $captured['url'] = $urlButton;

            return true;
        })
        ->andReturnTrue();
}

// ─── Factures (préfixe f/) ────────────────────────────────────────────────────

test('sendInvoiceCreated passe f/{code}/pdf comme paramètre bouton WhatsApp', function () {
    $company = makeButtonUrlCompany();
    $client = makeButtonUrlClient($company);
    $invoice = makeButtonUrlInvoice($company, $client, ['due_at' => now()->addDays(15)]);

    $captured = ['url' => null];

    $this->mock(WhatsAppProviderInterface::class, function (MockInterface $m) use (&$captured) {
        setupButtonCapture($m, $captured);
    });

    app(WhatsAppNotificationService::class)->sendInvoiceCreated($invoice, $company);

    expect($captured['url'])->toBe('f/'.$invoice->public_code.'/pdf');
});

test('sendInvoicePartiallyPaid passe f/{code}/pdf comme paramètre bouton WhatsApp', function () {
    $company = makeButtonUrlCompany();
    $client = makeButtonUrlClient($company);
    $invoice = makeButtonUrlInvoice($company, $client, [
        'status' => InvoiceStatus::PartiallyPaid->value,
        'amount_paid' => 40_000,
    ]);

    $payment = Payment::unguarded(fn () => Payment::create([
        'invoice_id' => $invoice->id,
        'amount' => 40_000,
        'paid_at' => now(),
        'method' => PaymentMethod::Cash->value,
    ]));

    $captured = ['url' => null];

    $this->mock(WhatsAppProviderInterface::class, function (MockInterface $m) use (&$captured) {
        setupButtonCapture($m, $captured);
    });

    app(WhatsAppNotificationService::class)->sendInvoicePartiallyPaid($invoice, $payment, $company);

    expect($captured['url'])->toBe('f/'.$invoice->public_code.'/pdf');
});

test('sendInvoicePaidFull passe f/{code}/pdf comme paramètre bouton WhatsApp', function () {
    $company = makeButtonUrlCompany();
    $client = makeButtonUrlClient($company);
    $invoice = makeButtonUrlInvoice($company, $client, [
        'status' => InvoiceStatus::Paid->value,
        'amount_paid' => 100_000,
    ]);

    $payment = Payment::unguarded(fn () => Payment::create([
        'invoice_id' => $invoice->id,
        'amount' => 100_000,
        'paid_at' => now(),
        'method' => PaymentMethod::Cash->value,
    ]));

    $captured = ['url' => null];

    $this->mock(WhatsAppProviderInterface::class, function (MockInterface $m) use (&$captured) {
        setupButtonCapture($m, $captured);
    });

    app(WhatsAppNotificationService::class)->sendInvoicePaidFull($invoice, $payment, $company);

    expect($captured['url'])->toBe('f/'.$invoice->public_code.'/pdf');
});

// ─── Relances (préfixe f/) ────────────────────────────────────────────────────

test('WhatsAppReminderService passe f/{code}/pdf comme paramètre bouton pour les relances manuelles', function () {
    $company = makeButtonUrlCompany();
    $client = makeButtonUrlClient($company);
    $invoice = makeButtonUrlInvoice($company, $client, [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(5),
    ]);

    $captured = ['url' => null];

    $mock = $this->mock(WhatsAppProviderInterface::class, function (MockInterface $m) use (&$captured) {
        setupButtonCapture($m, $captured);
    });

    $service = new WhatsAppReminderService($mock, app(WhatsAppTemplateCatalog::class));
    $service->send($invoice, null, null, 'reminder_manual_cordial');

    expect($captured['url'])->toBe('f/'.$invoice->public_code.'/pdf');
});

test('WhatsAppReminderService passe f/{code}/pdf pour les relances auto (dayOffset)', function () {
    $company = makeButtonUrlCompany();
    $client = makeButtonUrlClient($company);
    $invoice = makeButtonUrlInvoice($company, $client, [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(7),
    ]);

    $captured = ['url' => null];

    $mock = $this->mock(WhatsAppProviderInterface::class, function (MockInterface $m) use (&$captured) {
        setupButtonCapture($m, $captured);
    });

    $service = new WhatsAppReminderService($mock, app(WhatsAppTemplateCatalog::class));
    $service->send($invoice, null, 7);

    expect($captured['url'])->toBe('f/'.$invoice->public_code.'/pdf');
});

// ─── Devis (préfixe d/) ───────────────────────────────────────────────────────

test('sendProposalSent passe d/{code}/pdf pour un devis', function () {
    $company = makeButtonUrlCompany();
    $client = makeButtonUrlClient($company);

    $quote = ProposalDocument::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'type' => ProposalDocumentType::Quote,
        'reference' => 'DEV-BTN-001',
        'status' => ProposalDocumentStatus::Sent,
        'issued_at' => now(),
        'valid_until' => now()->addDays(30),
        'subtotal' => 200_000,
        'tax_amount' => 0,
        'total' => 200_000,
        'currency' => 'XOF',
    ]);

    $captured = ['url' => null];

    $this->mock(WhatsAppProviderInterface::class, function (MockInterface $m) use (&$captured) {
        setupButtonCapture($m, $captured);
    });

    app(WhatsAppNotificationService::class)->sendProposalSent($quote, $company);

    expect($captured['url'])->toBe('d/'.$quote->public_code.'/pdf');
});

// ─── Proformas (préfixe p/) ───────────────────────────────────────────────────

test('sendProposalSent passe p/{code}/pdf pour une proforma', function () {
    $company = makeButtonUrlCompany();
    $client = makeButtonUrlClient($company);

    $proforma = ProposalDocument::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'type' => ProposalDocumentType::Proforma,
        'reference' => 'PRO-BTN-001',
        'status' => ProposalDocumentStatus::Sent,
        'issued_at' => now(),
        'valid_until' => now()->addDays(30),
        'subtotal' => 300_000,
        'tax_amount' => 0,
        'total' => 300_000,
        'currency' => 'XOF',
    ]);

    $captured = ['url' => null];

    $this->mock(WhatsAppProviderInterface::class, function (MockInterface $m) use (&$captured) {
        setupButtonCapture($m, $captured);
    });

    app(WhatsAppNotificationService::class)->sendProposalSent($proforma, $company);

    expect($captured['url'])->toBe('p/'.$proforma->public_code.'/pdf');
});
