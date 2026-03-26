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
        'discount' => 0,
    ]);

    expect($total)->toBe(50_000);
});

test('calculateLineTotal applies discount percentage', function () {
    $service = new InvoiceService;

    $total = $service->calculateLineTotal([
        'quantity' => 10,
        'unit_price' => 10_000,
        'discount' => 10,
    ]);

    expect($total)->toBe(90_000);
});

test('calculateLineTotal handles zero discount', function () {
    $service = new InvoiceService;

    $total = $service->calculateLineTotal([
        'quantity' => 1,
        'unit_price' => 50_000,
        'discount' => 0,
    ]);

    expect($total)->toBe(50_000);
});

// ─── Invoice totals calculation ──────────────────────────────────────────────

test('calculateInvoiceTotals sums lines with global tax rate', function () {
    $service = new InvoiceService;

    $totals = $service->calculateInvoiceTotals([
        ['quantity' => 2, 'unit_price' => 50_000, 'discount' => 0],
        ['quantity' => 1, 'unit_price' => 30_000, 'discount' => 0],
    ], 18);

    expect($totals['subtotal'])->toBe(130_000)
        ->and($totals['tax_amount'])->toBe(23_400)
        ->and($totals['total'])->toBe(153_400);
});

test('calculateInvoiceTotals handles discounts with global tax', function () {
    $service = new InvoiceService;

    $totals = $service->calculateInvoiceTotals([
        ['quantity' => 10, 'unit_price' => 10_000, 'discount' => 10],
        ['quantity' => 5, 'unit_price' => 20_000, 'discount' => 0],
    ], 18);

    // Line 1: 10 * 10000 = 100000, -10% = 90000
    // Line 2: 5 * 20000 = 100000
    // Subtotal: 190000, Tax 18%: 34200
    expect($totals['subtotal'])->toBe(190_000)
        ->and($totals['tax_amount'])->toBe(34_200)
        ->and($totals['total'])->toBe(224_200);
});

test('calculateInvoiceTotals handles empty lines', function () {
    $service = new InvoiceService;

    $totals = $service->calculateInvoiceTotals([]);

    expect($totals['subtotal'])->toBe(0)
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
        'subject' => 'Test invoice',
        'currency' => 'XOF',
        'tax_rate' => 18,
        'issued_at' => now()->format('Y-m-d'),
        'due_at' => now()->addDays(30)->format('Y-m-d'),
        'notes' => null,
        'payment_terms' => null,
        'payment_instructions' => null,
    ], [
        ['description' => 'Service A', 'quantity' => 2, 'unit_price' => 50_000, 'discount' => 0],
        ['description' => 'Service B', 'quantity' => 1, 'unit_price' => 30_000, 'discount' => 0],
    ]);

    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->subtotal)->toBe(130_000)
        ->and($invoice->tax_amount)->toBe(23_400)
        ->and($invoice->total)->toBe(153_400)
        ->and($invoice->lines)->toHaveCount(2);
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
        'issued_at' => now()->format('Y-m-d'),
        'due_at' => now()->addDays(30)->format('Y-m-d'),
    ], [
        ['description' => 'Old line', 'quantity' => 1, 'unit_price' => 10_000, 'discount' => 0],
    ]);

    $updated = $service->update($invoice, [
        'client_id' => $client->id,
        'currency' => 'XOF',
        'tax_rate' => 18,
        'issued_at' => now()->format('Y-m-d'),
        'due_at' => now()->addDays(15)->format('Y-m-d'),
    ], [
        ['description' => 'New line', 'quantity' => 3, 'unit_price' => 25_000, 'discount' => 0],
    ]);

    expect($updated->lines)->toHaveCount(1)
        ->and($updated->lines->first()->description)->toBe('New line')
        ->and($updated->subtotal)->toBe(75_000)
        ->and($updated->tax_amount)->toBe(13_500)
        ->and($updated->total)->toBe(88_500);
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
