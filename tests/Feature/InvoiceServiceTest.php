<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\Company;
use Modules\PME\Clients\Models\Client;
use Modules\PME\Invoicing\Enums\InvoiceStatus;
use Modules\PME\Invoicing\Models\Invoice;
use Modules\PME\Invoicing\Services\InvoiceService;

uses(RefreshDatabase::class);

// ─── Reference generation ────────────────────────────────────────────────────

test('generateReference returns FYK-FAC-XXXXXX format', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $service = new InvoiceService;

    $ref = $service->generateReference($company);

    expect($ref)->toMatch('/^FYK-FAC-[A-Z0-9]{6}$/');
});

test('generateReference returns unique references', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $service = new InvoiceService;

    $refs = [];
    for ($i = 0; $i < 20; $i++) {
        $refs[] = $service->generateReference($company);
    }

    expect(array_unique($refs))->toHaveCount(20);
});

// ─── Line total calculation ──────────────────────────────────────────────────

test('calculateLineTotal computes quantity times unit price', function () {
    $service = new InvoiceService;

    $total = $service->calculateLineTotal([
        'quantity' => 5,
        'unit_price' => 10_000,
    ]);

    expect($total)->toBe(50_000);
});

test('calculateLineTotal handles single item', function () {
    $service = new InvoiceService;

    $total = $service->calculateLineTotal([
        'quantity' => 1,
        'unit_price' => 50_000,
    ]);

    expect($total)->toBe(50_000);
});

// ─── Invoice totals calculation ──────────────────────────────────────────────

test('calculateInvoiceTotals sums lines with global tax rate', function () {
    $service = new InvoiceService;

    $totals = $service->calculateInvoiceTotals([
        ['quantity' => 2, 'unit_price' => 50_000],
        ['quantity' => 1, 'unit_price' => 30_000],
    ], 18);

    expect($totals['subtotal'])->toBe(130_000)
        ->and($totals['discount_amount'])->toBe(0)
        ->and($totals['discounted_subtotal'])->toBe(130_000)
        ->and($totals['tax_amount'])->toBe(23_400)
        ->and($totals['total'])->toBe(153_400);
});

test('calculateInvoiceTotals applies global discount before tax', function () {
    $service = new InvoiceService;

    // Subtotal: 200 000, Discount 10%: 20 000, Discounted: 180 000, Tax 18%: 32 400
    $totals = $service->calculateInvoiceTotals([
        ['quantity' => 10, 'unit_price' => 10_000],
        ['quantity' => 5, 'unit_price' => 20_000],
    ], taxRate: 18, discount: 10);

    expect($totals['subtotal'])->toBe(200_000)
        ->and($totals['discount_amount'])->toBe(20_000)
        ->and($totals['discounted_subtotal'])->toBe(180_000)
        ->and($totals['tax_amount'])->toBe(32_400)
        ->and($totals['total'])->toBe(212_400);
});

test('calculateInvoiceTotals with discount and no tax', function () {
    $service = new InvoiceService;

    // Subtotal: 100 000, Discount 15%: 15 000, Total: 85 000
    $totals = $service->calculateInvoiceTotals([
        ['quantity' => 1, 'unit_price' => 100_000],
    ], taxRate: 0, discount: 15);

    expect($totals['subtotal'])->toBe(100_000)
        ->and($totals['discount_amount'])->toBe(15_000)
        ->and($totals['discounted_subtotal'])->toBe(85_000)
        ->and($totals['tax_amount'])->toBe(0)
        ->and($totals['total'])->toBe(85_000);
});

test('calculateInvoiceTotals with 100% discount results in zero total', function () {
    $service = new InvoiceService;

    $totals = $service->calculateInvoiceTotals([
        ['quantity' => 2, 'unit_price' => 50_000],
    ], taxRate: 18, discount: 100);

    expect($totals['subtotal'])->toBe(100_000)
        ->and($totals['discount_amount'])->toBe(100_000)
        ->and($totals['total'])->toBe(0);
});

test('calculateInvoiceTotals handles empty lines', function () {
    $service = new InvoiceService;

    $totals = $service->calculateInvoiceTotals([]);

    expect($totals['subtotal'])->toBe(0)
        ->and($totals['discount_amount'])->toBe(0)
        ->and($totals['tax_amount'])->toBe(0)
        ->and($totals['total'])->toBe(0);
});

// ─── Create invoice ──────────────────────────────────────────────────────────

test('create stores invoice with lines in transaction', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new InvoiceService;

    $invoice = $service->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-FAC-TEST01',
        'currency' => 'XOF',
        'tax_rate' => 18,
        'discount' => 0,
        'issued_at' => now()->format('Y-m-d'),
        'due_at' => now()->addDays(30)->format('Y-m-d'),
        'notes' => null,
        'payment_terms' => null,
        'payment_instructions' => null,
    ], [
        ['description' => 'Service A', 'quantity' => 2, 'unit_price' => 50_000],
        ['description' => 'Service B', 'quantity' => 1, 'unit_price' => 30_000],
    ]);

    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->subtotal)->toBe(130_000)
        ->and($invoice->tax_amount)->toBe(23_400)
        ->and($invoice->total)->toBe(153_400)
        ->and($invoice->discount)->toBe(0)
        ->and($invoice->lines)->toHaveCount(2);
});

test('create stores invoice with global discount', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new InvoiceService;

    $invoice = $service->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-FAC-DISC01',
        'currency' => 'XOF',
        'tax_rate' => 18,
        'discount' => 10,
        'issued_at' => now()->format('Y-m-d'),
        'due_at' => now()->addDays(30)->format('Y-m-d'),
    ], [
        ['description' => 'Service A', 'quantity' => 2, 'unit_price' => 50_000],
    ]);

    // Subtotal: 100 000, Discount 10%: 10 000, Discounted: 90 000, Tax 18%: 16 200
    expect($invoice->subtotal)->toBe(100_000)
        ->and($invoice->discount)->toBe(10)
        ->and($invoice->tax_amount)->toBe(16_200)
        ->and($invoice->total)->toBe(106_200);
});

// ─── Update invoice ──────────────────────────────────────────────────────────

test('update replaces lines and recalculates totals', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new InvoiceService;

    $invoice = $service->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-FAC-UPD001',
        'currency' => 'XOF',
        'tax_rate' => 0,
        'discount' => 0,
        'issued_at' => now()->format('Y-m-d'),
        'due_at' => now()->addDays(30)->format('Y-m-d'),
    ], [
        ['description' => 'Old line', 'quantity' => 1, 'unit_price' => 10_000],
    ]);

    $updated = $service->update($invoice, [
        'client_id' => $client->id,
        'currency' => 'XOF',
        'tax_rate' => 18,
        'discount' => 5,
        'issued_at' => now()->format('Y-m-d'),
        'due_at' => now()->addDays(15)->format('Y-m-d'),
    ], [
        ['description' => 'New line', 'quantity' => 3, 'unit_price' => 25_000],
    ]);

    // Subtotal: 75 000, Discount 5%: 3 750, Discounted: 71 250, Tax 18%: 12 825
    expect($updated->lines)->toHaveCount(1)
        ->and($updated->lines->first()->description)->toBe('New line')
        ->and($updated->subtotal)->toBe(75_000)
        ->and($updated->discount)->toBe(5)
        ->and($updated->tax_amount)->toBe(12_825)
        ->and($updated->total)->toBe(84_075);
});

// ─── Mark as sent ────────────────────────────────────────────────────────────

test('markAsSent changes status to Sent', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $invoice = Invoice::factory()->forCompany($company)->draft()->create();
    $service = new InvoiceService;

    $result = $service->markAsSent($invoice);

    expect($result->status)->toBe(InvoiceStatus::Sent);
});

// ─── canEdit ─────────────────────────────────────────────────────────────────

test('canEdit returns true for draft and sent invoices', function () {
    $service = new InvoiceService;
    $company = Company::factory()->create(['type' => 'sme']);

    $draft = Invoice::factory()->forCompany($company)->draft()->create();
    $sent = Invoice::factory()->forCompany($company)->sent()->create();

    expect($service->canEdit($draft))->toBeTrue()
        ->and($service->canEdit($sent))->toBeTrue();
});

test('canEdit returns false for paid invoices', function () {
    $service = new InvoiceService;
    $company = Company::factory()->create(['type' => 'sme']);

    $paid = Invoice::factory()->forCompany($company)->paid()->create();

    expect($service->canEdit($paid))->toBeFalse();
});
