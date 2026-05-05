<?php

use App\Enums\PME\InvoiceStatus;
use App\Models\PME\Invoice;
use App\Models\PME\ProposalDocument;
use App\Services\PME\DocumentLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-05-03 10:00:00');
});

test('invoice lifecycle derives overdue from a sent unpaid invoice past due', function () {
    $invoice = Invoice::factory()
        ->sent()
        ->create([
            'sent_at' => now()->subDays(20),
            'due_at' => now()->subDays(12),
            'total' => 236_000,
            'amount_paid' => 0,
        ]);

    $lifecycle = app(DocumentLifecycleService::class)->forInvoice($invoice);

    expect($lifecycle['title'])->toBe('État : Envoyée + en retard — alerte temporelle')
        ->and($lifecycle['badges'][0]['label'])->toBe('Envoyée')
        ->and($lifecycle['badges'][1]['label'])->toBe('En retard · J+12')
        ->and($lifecycle['steps'][0]['state'])->toBe('danger');
});

test('invoice lifecycle maps partial and paid states with payment amounts', function () {
    $partial = Invoice::factory()
        ->create([
            'status' => InvoiceStatus::PartiallyPaid,
            'sent_at' => now()->subDays(5),
            'due_at' => now()->addDays(25),
            'total' => 236_000,
            'amount_paid' => 120_000,
        ]);

    $paid = Invoice::factory()
        ->paid()
        ->create([
            'sent_at' => now()->subDays(10),
            'paid_at' => now()->subDay(),
        ]);

    $service = app(DocumentLifecycleService::class);

    expect($service->forInvoice($partial)['title'])->toBe('État : Partiellement payée — paiement en cours')
        ->and($service->forInvoice($partial)['steps'][1]['detail'])->toBe('120 000 / 236 000 reçus')
        ->and($service->forInvoice($paid)['title'])->toBe('État : Payée — encaissement complet');
});

test('quote lifecycle treats a linked invoice as the terminal factured state', function () {
    $quote = ProposalDocument::factory()
        ->quote()
        ->accepted()
        ->create([
            'sent_at' => now()->subDays(12),
            'accepted_at' => now()->subDays(5),
        ]);

    Invoice::factory()->create([
        'proposal_document_id' => $quote->id,
        'reference' => 'FYK-FAC-DS0701',
    ]);

    $lifecycle = app(DocumentLifecycleService::class)->forQuote($quote->fresh('invoice'));

    expect($lifecycle['title'])->toBe('État : Facturé — facture créée')
        ->and($lifecycle['steps'][2]['label'])->toBe('Facturé')
        ->and($lifecycle['steps'][2]['detail'])->toBe('FYK-FAC-DS0701');
});

test('quote lifecycle derives expired from sent documents past validity', function () {
    $quote = ProposalDocument::factory()
        ->quote()
        ->sent()
        ->create([
            'sent_at' => now()->subDays(40),
            'valid_until' => now()->subDays(10),
        ]);

    $lifecycle = app(DocumentLifecycleService::class)->forQuote($quote);

    expect($lifecycle['title'])->toBe('État : Expiré — durée de validité dépassée')
        ->and($lifecycle['badges'][0]['label'])->toBe('Expiré')
        ->and($lifecycle['steps'][1]['state'])->toBe('warning');
});

test('proforma lifecycle maps purchase order and conversion states', function () {
    $proforma = ProposalDocument::factory()
        ->proforma()
        ->poReceived()
        ->create([
            'sent_at' => now()->subDays(8),
            'po_reference' => 'BC-2026/0142',
            'po_received_at' => now()->subDays(2),
        ]);

    $poLifecycle = app(DocumentLifecycleService::class)->forProforma($proforma);

    Invoice::factory()->create([
        'proposal_document_id' => $proforma->id,
        'reference' => 'FYK-FAC-DS0801',
    ]);

    $convertedLifecycle = app(DocumentLifecycleService::class)->forProforma($proforma->fresh('invoice'));

    expect($poLifecycle['title'])->toBe('État : BC reçu — prêt à facturer')
        ->and($poLifecycle['steps'][1]['detail'])->toContain('BC-2026/0142')
        ->and($convertedLifecycle['title'])->toBe('État : Facturée — facture créée')
        ->and($convertedLifecycle['steps'][2]['detail'])->toBe('FYK-FAC-DS0801');
});

test('proforma lifecycle derives expired when no purchase order was received', function () {
    $proforma = ProposalDocument::factory()
        ->proforma()
        ->sent()
        ->create([
            'sent_at' => now()->subDays(35),
            'valid_until' => now()->subDays(4),
        ]);

    $lifecycle = app(DocumentLifecycleService::class)->forProforma($proforma);

    expect($lifecycle['title'])->toBe('État : Expirée — validité dépassée')
        ->and($lifecycle['badges'][0]['label'])->toBe('Expirée')
        ->and($lifecycle['steps'][1]['state'])->toBe('warning');
});
