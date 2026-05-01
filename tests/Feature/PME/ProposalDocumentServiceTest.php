<?php

use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\ProposalDocumentStatus;
use App\Enums\PME\ProposalDocumentType;
use App\Events\PME\ProposalDocumentConverted;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Models\PME\ProposalDocument;
use App\Services\PME\InvoiceService;
use App\Services\PME\ProposalDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

dataset('document_types', [
    'quote' => [ProposalDocumentType::Quote, 'FYK-DEV-'],
    'proforma' => [ProposalDocumentType::Proforma, 'FYK-PRO-'],
]);

// ─── Reference generation ────────────────────────────────────────────────────

test('generateReference returns the type-specific prefix', function (ProposalDocumentType $type, string $prefix) {
    $company = Company::factory()->create(['type' => 'sme']);
    $service = new ProposalDocumentService;

    $reference = $service->generateReference($company, $type);

    expect($reference)->toStartWith($prefix)
        ->and($reference)->toMatch('/^FYK-(DEV|PRO)-[A-Z0-9]{6}$/');
})->with('document_types');

test('generateReference returns unique references', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $service = new ProposalDocumentService;

    $refs = [];
    for ($i = 0; $i < 20; $i++) {
        $refs[] = $service->generateReference($company, ProposalDocumentType::Quote);
    }

    expect(array_unique($refs))->toHaveCount(20);
});

// ─── Totals calculation ─────────────────────────────────────────────────────

test('calculateLineTotal computes quantity times unit price', function () {
    expect((new ProposalDocumentService)->calculateLineTotal([
        'quantity' => 5,
        'unit_price' => 10_000,
    ]))->toBe(50_000);
});

test('calculateTotals sums lines with global tax rate', function () {
    $totals = (new ProposalDocumentService)->calculateTotals([
        ['quantity' => 2, 'unit_price' => 50_000],
        ['quantity' => 1, 'unit_price' => 30_000],
    ], 18);

    expect($totals['subtotal'])->toBe(130_000)
        ->and($totals['tax_amount'])->toBe(23_400)
        ->and($totals['total'])->toBe(153_400);
});

test('calculateTotals applies percent discount before tax', function () {
    $totals = (new ProposalDocumentService)->calculateTotals(
        [['quantity' => 10, 'unit_price' => 10_000], ['quantity' => 5, 'unit_price' => 20_000]],
        taxRate: 18, discount: 10, discountType: 'percent'
    );

    expect($totals['subtotal'])->toBe(200_000)
        ->and($totals['discount_amount'])->toBe(20_000)
        ->and($totals['discounted_subtotal'])->toBe(180_000)
        ->and($totals['tax_amount'])->toBe(32_400)
        ->and($totals['total'])->toBe(212_400);
});

test('calculateTotals fixed discount cannot exceed subtotal', function () {
    $totals = (new ProposalDocumentService)->calculateTotals(
        [['quantity' => 1, 'unit_price' => 50_000]],
        taxRate: 18, discount: 999_999, discountType: 'fixed'
    );

    expect($totals['discount_amount'])->toBe(50_000)
        ->and($totals['total'])->toBe(0);
});

test('calculateTotals handles empty lines', function () {
    $totals = (new ProposalDocumentService)->calculateTotals([]);

    expect($totals['subtotal'])->toBe(0)
        ->and($totals['total'])->toBe(0);
});

// ─── create() ───────────────────────────────────────────────────────────────

test('create stores a document with lines for both types', function (ProposalDocumentType $type, string $prefix) {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new ProposalDocumentService;

    $document = $service->create($company, $type, [
        'client_id' => $client->id,
        'reference' => $prefix.'TEST01',
        'currency' => 'XOF',
        'tax_rate' => 18,
        'discount' => 0,
        'discount_type' => 'percent',
        'issued_at' => now()->format('Y-m-d'),
        'valid_until' => now()->addDays(30)->format('Y-m-d'),
        'notes' => null,
    ], [
        ['description' => 'Service A', 'quantity' => 2, 'unit_price' => 50_000],
        ['description' => 'Service B', 'quantity' => 1, 'unit_price' => 30_000],
    ]);

    expect($document)->toBeInstanceOf(ProposalDocument::class)
        ->and($document->type)->toBe($type)
        ->and($document->status)->toBe(ProposalDocumentStatus::Draft)
        ->and($document->subtotal)->toBe(130_000)
        ->and($document->tax_amount)->toBe(23_400)
        ->and($document->total)->toBe(153_400)
        ->and($document->lines)->toHaveCount(2);
})->with('document_types');

test('create stores proforma-specific fields when type is proforma', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);

    $document = (new ProposalDocumentService)->create($company, ProposalDocumentType::Proforma, [
        'client_id' => $client->id,
        'reference' => 'FYK-PRO-TEST01',
        'currency' => 'XOF',
        'tax_rate' => 0,
        'discount' => 0,
        'issued_at' => now()->format('Y-m-d'),
        'valid_until' => now()->addDays(30)->format('Y-m-d'),
        'dossier_reference' => 'AO-2026-01',
        'payment_terms' => 'À 30 jours',
        'delivery_terms' => 'Sous 2 semaines',
    ], [['description' => 'Service', 'quantity' => 1, 'unit_price' => 100_000]]);

    expect($document->dossier_reference)->toBe('AO-2026-01')
        ->and($document->payment_terms)->toBe('À 30 jours')
        ->and($document->delivery_terms)->toBe('Sous 2 semaines');
});

test('create ignores proforma-specific fields when type is quote', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);

    $document = (new ProposalDocumentService)->create($company, ProposalDocumentType::Quote, [
        'client_id' => $client->id,
        'reference' => 'FYK-DEV-TEST01',
        'currency' => 'XOF',
        'tax_rate' => 0,
        'discount' => 0,
        'issued_at' => now()->format('Y-m-d'),
        'valid_until' => now()->addDays(30)->format('Y-m-d'),
        'dossier_reference' => 'should-be-ignored',
        'payment_terms' => 'should-be-ignored',
    ], [['description' => 'Service', 'quantity' => 1, 'unit_price' => 100_000]]);

    expect($document->dossier_reference)->toBeNull()
        ->and($document->payment_terms)->toBeNull()
        ->and($document->delivery_terms)->toBeNull();
});

// ─── update() ───────────────────────────────────────────────────────────────

test('update replaces lines and recomputes totals', function () {
    $document = ProposalDocument::factory()->withLines(2)->create();
    $service = new ProposalDocumentService;

    $service->update($document, [
        'client_id' => $document->client_id,
        'currency' => 'XOF',
        'tax_rate' => 0,
        'discount' => 0,
        'issued_at' => now()->format('Y-m-d'),
        'valid_until' => now()->addDays(30)->format('Y-m-d'),
    ], [['description' => 'New', 'quantity' => 1, 'unit_price' => 42_000]]);

    expect($document->fresh()->lines)->toHaveCount(1)
        ->and($document->fresh()->subtotal)->toBe(42_000)
        ->and($document->fresh()->total)->toBe(42_000);
});

// ─── State transitions ──────────────────────────────────────────────────────

test('markAsSent transitions to Sent for both types', function (ProposalDocumentType $type) {
    $document = ProposalDocument::factory()->state(['type' => $type])->create();

    (new ProposalDocumentService)->markAsSent($document);

    expect($document->fresh()->status)->toBe(ProposalDocumentStatus::Sent);
})->with('document_types');

test('markAsAccepted is allowed for quotes', function () {
    $document = ProposalDocument::factory()->quote()->sent()->create();

    (new ProposalDocumentService)->markAsAccepted($document);

    expect($document->fresh()->status)->toBe(ProposalDocumentStatus::Accepted);
});

test('markAsAccepted throws DomainException for proformas', function () {
    $document = ProposalDocument::factory()->proforma()->sent()->create();

    (new ProposalDocumentService)->markAsAccepted($document);
})->throws(DomainException::class);

test('markAsPoReceived is allowed for proformas and stores PO data', function () {
    $document = ProposalDocument::factory()->proforma()->sent()->create();

    (new ProposalDocumentService)->markAsPoReceived($document, [
        'reference' => 'BC-2026-001',
        'received_at' => '2026-04-15',
        'notes' => 'BC reçu par email',
    ]);

    $fresh = $document->fresh();
    expect($fresh->status)->toBe(ProposalDocumentStatus::PoReceived)
        ->and($fresh->po_reference)->toBe('BC-2026-001')
        ->and($fresh->po_received_at?->toDateString())->toBe('2026-04-15')
        ->and($fresh->po_notes)->toBe('BC reçu par email');
});

test('markAsPoReceived throws DomainException for quotes', function () {
    $document = ProposalDocument::factory()->quote()->sent()->create();

    (new ProposalDocumentService)->markAsPoReceived($document);
})->throws(DomainException::class);

test('markAsDeclined is allowed for both types', function (ProposalDocumentType $type) {
    $document = ProposalDocument::factory()->state(['type' => $type])->create();

    (new ProposalDocumentService)->markAsDeclined($document);

    expect($document->fresh()->status)->toBe(ProposalDocumentStatus::Declined);
})->with('document_types');

// ─── canEdit ────────────────────────────────────────────────────────────────

test('canEdit allows Draft and Sent', function () {
    $service = new ProposalDocumentService;
    $draft = ProposalDocument::factory()->create();
    $sent = ProposalDocument::factory()->sent()->create();
    $declined = ProposalDocument::factory()->declined()->create();

    expect($service->canEdit($draft))->toBeTrue()
        ->and($service->canEdit($sent))->toBeTrue()
        ->and($service->canEdit($declined))->toBeFalse();
});

// ─── convertToInvoice ───────────────────────────────────────────────────────

test('convertToInvoice creates an invoice linked via proposal_document_id', function (ProposalDocumentType $type) {
    Event::fake([ProposalDocumentConverted::class]);

    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $document = ProposalDocument::factory()
        ->state(['type' => $type, 'company_id' => $company->id, 'client_id' => $client->id])
        ->sent()
        ->withLines(2)
        ->create();

    $invoice = (new ProposalDocumentService)->convertToInvoice($document, $company);

    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->proposal_document_id)->toBe($document->id)
        ->and($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->total)->toBe($document->total)
        ->and($invoice->lines)->toHaveCount(2)
        ->and($invoice->reference)->toStartWith('FYK-FAC-');

    Event::assertDispatched(ProposalDocumentConverted::class);
})->with('document_types');

test('convertToInvoice transitions a sent quote to Accepted', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $quote = ProposalDocument::factory()
        ->quote()
        ->forCompany($company)
        ->withClient($client)
        ->sent()
        ->withLines(1)
        ->create();

    (new ProposalDocumentService)->convertToInvoice($quote, $company);

    expect($quote->fresh()->status)->toBe(ProposalDocumentStatus::Accepted);
});

test('convertToInvoice transitions a proforma to Converted', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $proforma = ProposalDocument::factory()
        ->proforma()
        ->forCompany($company)
        ->withClient($client)
        ->poReceived()
        ->withLines(1)
        ->create(['payment_terms' => 'Net 60']);

    $invoice = (new ProposalDocumentService)->convertToInvoice($proforma, $company);

    expect($proforma->fresh()->status)->toBe(ProposalDocumentStatus::Converted)
        ->and($invoice->payment_terms)->toBe('Net 60');
});

test('convertToInvoice does not copy payment_terms for quotes', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $quote = ProposalDocument::factory()
        ->quote()
        ->forCompany($company)
        ->withClient($client)
        ->sent()
        ->withLines(1)
        ->create();

    $invoice = (new ProposalDocumentService)->convertToInvoice($quote, $company);

    expect($invoice->payment_terms)->toBeNull();
});

test('convertToInvoice aborts 409 if already converted', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $document = ProposalDocument::factory()
        ->forCompany($company)
        ->withClient($client)
        ->sent()
        ->withLines(1)
        ->create();

    $service = new ProposalDocumentService;
    $service->convertToInvoice($document, $company);

    $service->convertToInvoice($document->fresh(), $company);
})->throws(HttpException::class);

test('proposal totals match invoice service totals for the same inputs', function () {
    $proposal = new ProposalDocumentService;
    $invoice = new InvoiceService;

    $lines = [
        ['quantity' => 3, 'unit_price' => 25_000],
        ['quantity' => 1, 'unit_price' => 15_000],
    ];

    $a = $proposal->calculateTotals($lines, 18, 10, 'percent');
    $b = $invoice->calculateInvoiceTotals($lines, 18, 10, 'percent');

    expect($a['subtotal'])->toBe($b['subtotal'])
        ->and($a['discount_amount'])->toBe($b['discount_amount'])
        ->and($a['tax_amount'])->toBe($b['tax_amount'])
        ->and($a['total'])->toBe($b['total']);
});
