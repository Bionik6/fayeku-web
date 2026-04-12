<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\QuoteStatus;
use App\Events\PME\QuoteAccepted;
use App\Models\PME\Invoice;
use App\Models\PME\Quote;
use App\Services\PME\InvoiceService;
use App\Services\PME\QuoteService;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

// ─── Reference generation ────────────────────────────────────────────────────

test('generateReference returns FYK-DEV-XXXXXX format', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $service = new QuoteService;

    $ref = $service->generateReference($company);

    expect($ref)->toMatch('/^FYK-DEV-[A-Z0-9]{6}$/');
});

test('generateReference returns unique references', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $service = new QuoteService;

    $refs = [];
    for ($i = 0; $i < 20; $i++) {
        $refs[] = $service->generateReference($company);
    }

    expect(array_unique($refs))->toHaveCount(20);
});

// ─── Line total calculation ──────────────────────────────────────────────────

test('calculateLineTotal computes quantity times unit price', function () {
    $service = new QuoteService;

    $total = $service->calculateLineTotal([
        'quantity' => 5,
        'unit_price' => 10_000,
    ]);

    expect($total)->toBe(50_000);
});

test('calculateLineTotal handles single item', function () {
    $service = new QuoteService;

    $total = $service->calculateLineTotal([
        'quantity' => 1,
        'unit_price' => 50_000,
    ]);

    expect($total)->toBe(50_000);
});

// ─── Quote totals calculation ─────────────────────────────────────────────────

test('calculateQuoteTotals sums lines with global tax rate', function () {
    $service = new QuoteService;

    $totals = $service->calculateQuoteTotals([
        ['quantity' => 2, 'unit_price' => 50_000],
        ['quantity' => 1, 'unit_price' => 30_000],
    ], 18);

    expect($totals['subtotal'])->toBe(130_000)
        ->and($totals['discount_amount'])->toBe(0)
        ->and($totals['discounted_subtotal'])->toBe(130_000)
        ->and($totals['tax_amount'])->toBe(23_400)
        ->and($totals['total'])->toBe(153_400);
});

test('calculateQuoteTotals applies percent discount before tax', function () {
    $service = new QuoteService;

    // Subtotal: 200 000, Discount 10%: 20 000, Discounted: 180 000, Tax 18%: 32 400
    $totals = $service->calculateQuoteTotals([
        ['quantity' => 10, 'unit_price' => 10_000],
        ['quantity' => 5, 'unit_price' => 20_000],
    ], taxRate: 18, discount: 10, discountType: 'percent');

    expect($totals['subtotal'])->toBe(200_000)
        ->and($totals['discount_amount'])->toBe(20_000)
        ->and($totals['discounted_subtotal'])->toBe(180_000)
        ->and($totals['tax_amount'])->toBe(32_400)
        ->and($totals['total'])->toBe(212_400);
});

test('calculateQuoteTotals applies fixed discount before tax', function () {
    $service = new QuoteService;

    // Subtotal: 100 000, Fixed discount: 15 000, Discounted: 85 000, Tax 18%: 15 300
    $totals = $service->calculateQuoteTotals([
        ['quantity' => 1, 'unit_price' => 100_000],
    ], taxRate: 18, discount: 15_000, discountType: 'fixed');

    expect($totals['subtotal'])->toBe(100_000)
        ->and($totals['discount_amount'])->toBe(15_000)
        ->and($totals['discounted_subtotal'])->toBe(85_000)
        ->and($totals['tax_amount'])->toBe(15_300)
        ->and($totals['total'])->toBe(100_300);
});

test('calculateQuoteTotals fixed discount cannot exceed subtotal', function () {
    $service = new QuoteService;

    $totals = $service->calculateQuoteTotals([
        ['quantity' => 1, 'unit_price' => 50_000],
    ], taxRate: 18, discount: 999_999, discountType: 'fixed');

    expect($totals['discount_amount'])->toBe(50_000)
        ->and($totals['total'])->toBe(0);
});

test('calculateQuoteTotals with 100% percent discount results in zero total', function () {
    $service = new QuoteService;

    $totals = $service->calculateQuoteTotals([
        ['quantity' => 2, 'unit_price' => 50_000],
    ], taxRate: 18, discount: 100, discountType: 'percent');

    expect($totals['subtotal'])->toBe(100_000)
        ->and($totals['discount_amount'])->toBe(100_000)
        ->and($totals['total'])->toBe(0);
});

test('calculateQuoteTotals with discount and no tax', function () {
    $service = new QuoteService;

    $totals = $service->calculateQuoteTotals([
        ['quantity' => 1, 'unit_price' => 100_000],
    ], taxRate: 0, discount: 15, discountType: 'percent');

    expect($totals['subtotal'])->toBe(100_000)
        ->and($totals['discount_amount'])->toBe(15_000)
        ->and($totals['discounted_subtotal'])->toBe(85_000)
        ->and($totals['tax_amount'])->toBe(0)
        ->and($totals['total'])->toBe(85_000);
});

test('calculateQuoteTotals handles empty lines', function () {
    $service = new QuoteService;

    $totals = $service->calculateQuoteTotals([]);

    expect($totals['subtotal'])->toBe(0)
        ->and($totals['discount_amount'])->toBe(0)
        ->and($totals['tax_amount'])->toBe(0)
        ->and($totals['total'])->toBe(0);
});

test('calculateQuoteTotals matches invoice calculation for same inputs', function () {
    $quoteService = new QuoteService;
    $invoiceService = new InvoiceService;

    $lines = [
        ['quantity' => 3, 'unit_price' => 25_000],
        ['quantity' => 1, 'unit_price' => 15_000],
    ];

    $quoteTotals = $quoteService->calculateQuoteTotals($lines, 18, 10, 'percent');
    $invoiceTotals = $invoiceService->calculateInvoiceTotals($lines, 18, 10, 'percent');

    expect($quoteTotals['subtotal'])->toBe($invoiceTotals['subtotal'])
        ->and($quoteTotals['discount_amount'])->toBe($invoiceTotals['discount_amount'])
        ->and($quoteTotals['tax_amount'])->toBe($invoiceTotals['tax_amount'])
        ->and($quoteTotals['total'])->toBe($invoiceTotals['total']);
});

test('calculateQuoteTotals fixed discount matches invoice calculation for same inputs', function () {
    $quoteService = new QuoteService;
    $invoiceService = new InvoiceService;

    $lines = [['quantity' => 2, 'unit_price' => 50_000]];

    $quoteTotals = $quoteService->calculateQuoteTotals($lines, 18, 5_000, 'fixed');
    $invoiceTotals = $invoiceService->calculateInvoiceTotals($lines, 18, 5_000, 'fixed');

    expect($quoteTotals['subtotal'])->toBe($invoiceTotals['subtotal'])
        ->and($quoteTotals['discount_amount'])->toBe($invoiceTotals['discount_amount'])
        ->and($quoteTotals['tax_amount'])->toBe($invoiceTotals['tax_amount'])
        ->and($quoteTotals['total'])->toBe($invoiceTotals['total']);
});

// ─── Create quote ─────────────────────────────────────────────────────────────

test('create stores quote with lines in transaction', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new QuoteService;

    $quote = $service->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-DEV-TEST01',
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

    expect($quote)->toBeInstanceOf(Quote::class)
        ->and($quote->status)->toBe(QuoteStatus::Draft)
        ->and($quote->subtotal)->toBe(130_000)
        ->and($quote->tax_amount)->toBe(23_400)
        ->and($quote->total)->toBe(153_400)
        ->and($quote->discount)->toBe(0)
        ->and($quote->discount_type)->toBe('percent')
        ->and($quote->lines)->toHaveCount(2);
});

test('create stores quote with percent discount', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new QuoteService;

    $quote = $service->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-DEV-DISC01',
        'currency' => 'XOF',
        'tax_rate' => 18,
        'discount' => 10,
        'discount_type' => 'percent',
        'issued_at' => now()->format('Y-m-d'),
        'valid_until' => now()->addDays(30)->format('Y-m-d'),
    ], [
        ['description' => 'Service A', 'quantity' => 2, 'unit_price' => 50_000],
    ]);

    // Subtotal: 100 000, Discount 10%: 10 000, Discounted: 90 000, Tax 18%: 16 200
    expect($quote->subtotal)->toBe(100_000)
        ->and($quote->discount)->toBe(10)
        ->and($quote->discount_type)->toBe('percent')
        ->and($quote->tax_amount)->toBe(16_200)
        ->and($quote->total)->toBe(106_200);
});

test('create stores quote with fixed discount', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new QuoteService;

    $quote = $service->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-DEV-FIX01',
        'currency' => 'XOF',
        'tax_rate' => 18,
        'discount' => 5_000,
        'discount_type' => 'fixed',
        'issued_at' => now()->format('Y-m-d'),
        'valid_until' => now()->addDays(30)->format('Y-m-d'),
    ], [
        ['description' => 'Service A', 'quantity' => 1, 'unit_price' => 100_000],
    ]);

    // Subtotal: 100 000, Fixed: 5 000, Discounted: 95 000, Tax 18%: 17 100
    expect($quote->subtotal)->toBe(100_000)
        ->and($quote->discount)->toBe(5_000)
        ->and($quote->discount_type)->toBe('fixed')
        ->and($quote->tax_amount)->toBe(17_100)
        ->and($quote->total)->toBe(112_100);
});

// ─── Update quote ─────────────────────────────────────────────────────────────

test('update replaces lines and recalculates totals', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new QuoteService;

    $quote = $service->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-DEV-UPD001',
        'currency' => 'XOF',
        'tax_rate' => 0,
        'discount' => 0,
        'discount_type' => 'percent',
        'issued_at' => now()->format('Y-m-d'),
        'valid_until' => now()->addDays(30)->format('Y-m-d'),
    ], [
        ['description' => 'Old line', 'quantity' => 1, 'unit_price' => 10_000],
    ]);

    $updated = $service->update($quote, [
        'client_id' => $client->id,
        'currency' => 'XOF',
        'tax_rate' => 18,
        'discount' => 5,
        'discount_type' => 'percent',
        'issued_at' => now()->format('Y-m-d'),
        'valid_until' => now()->addDays(60)->format('Y-m-d'),
    ], [
        ['description' => 'New line', 'quantity' => 3, 'unit_price' => 25_000],
    ]);

    // Subtotal: 75 000, Discount 5%: 3 750, Discounted: 71 250, Tax 18%: 12 825
    expect($updated->lines)->toHaveCount(1)
        ->and($updated->lines->first()->description)->toBe('New line')
        ->and($updated->subtotal)->toBe(75_000)
        ->and($updated->discount)->toBe(5)
        ->and($updated->discount_type)->toBe('percent')
        ->and($updated->tax_amount)->toBe(12_825)
        ->and($updated->total)->toBe(84_075);
});

// ─── Mark as sent ─────────────────────────────────────────────────────────────

test('markAsSent changes status to Sent', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new QuoteService;

    $quote = $service->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-DEV-SENT01',
        'currency' => 'XOF',
        'tax_rate' => 18,
        'discount' => 0,
        'discount_type' => 'percent',
        'issued_at' => now()->format('Y-m-d'),
        'valid_until' => now()->addDays(30)->format('Y-m-d'),
    ], [
        ['description' => 'Service', 'quantity' => 1, 'unit_price' => 50_000],
    ]);

    $result = $service->markAsSent($quote);

    expect($result->status)->toBe(QuoteStatus::Sent);
});

// ─── canEdit ──────────────────────────────────────────────────────────────────

test('canEdit returns true for draft and sent quotes', function () {
    $service = new QuoteService;
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);

    $draft = $service->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-DEV-DR01',
        'currency' => 'XOF',
        'tax_rate' => 18,
        'discount' => 0,
        'discount_type' => 'percent',
        'issued_at' => now()->format('Y-m-d'),
        'valid_until' => now()->addDays(30)->format('Y-m-d'),
    ], [['description' => 'X', 'quantity' => 1, 'unit_price' => 10_000]]);

    $sent = $service->markAsSent($draft);

    expect($service->canEdit($draft))->toBeTrue()
        ->and($service->canEdit($sent))->toBeTrue();
});

test('canEdit returns false for accepted quotes', function () {
    $service = new QuoteService;
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);

    $quote = $service->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-DEV-ACC01',
        'currency' => 'XOF',
        'tax_rate' => 18,
        'discount' => 0,
        'discount_type' => 'percent',
        'issued_at' => now()->format('Y-m-d'),
        'valid_until' => now()->addDays(30)->format('Y-m-d'),
    ], [['description' => 'X', 'quantity' => 1, 'unit_price' => 10_000]]);

    $service->markAsAccepted($quote);

    expect($service->canEdit($quote->fresh()))->toBeFalse();
});

// ─── convertToInvoice ─────────────────────────────────────────────────────────

test('convertToInvoice aborts with 409 when quote was already converted', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new QuoteService;

    $quote = $service->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-DEV-DUP01',
        'currency' => 'XOF',
        'tax_rate' => 18,
        'discount' => 0,
        'discount_type' => 'percent',
        'issued_at' => now()->format('Y-m-d'),
        'valid_until' => now()->addDays(30)->format('Y-m-d'),
    ], [
        ['description' => 'Service A', 'quantity' => 1, 'unit_price' => 50_000],
    ]);

    $service->convertToInvoice($quote, $company);

    // Second call must throw a 409
    $service->convertToInvoice($quote, $company);
})->throws(HttpException::class);

test('convertToInvoice 409 carries the expected message', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new QuoteService;

    $quote = $service->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-DEV-DUP02',
        'currency' => 'XOF',
        'tax_rate' => 0,
        'discount' => 0,
        'discount_type' => 'percent',
        'issued_at' => now()->format('Y-m-d'),
        'valid_until' => now()->addDays(30)->format('Y-m-d'),
    ], [
        ['description' => 'Service B', 'quantity' => 1, 'unit_price' => 50_000],
    ]);

    $service->convertToInvoice($quote, $company);

    try {
        $service->convertToInvoice($quote, $company);
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(409)
            ->and($e->getMessage())->toBe('Ce devis a déjà été converti en facture.');

        return;
    }

    $this->fail('Expected HttpException was not thrown.');
});

test('convertToInvoice does not create a second invoice on duplicate call', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new QuoteService;

    $quote = $service->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-DEV-DUP03',
        'currency' => 'XOF',
        'tax_rate' => 18,
        'discount' => 0,
        'discount_type' => 'percent',
        'issued_at' => now()->format('Y-m-d'),
        'valid_until' => now()->addDays(30)->format('Y-m-d'),
    ], [
        ['description' => 'Service C', 'quantity' => 1, 'unit_price' => 50_000],
    ]);

    $service->convertToInvoice($quote, $company);

    try {
        $service->convertToInvoice($quote, $company);
    } catch (HttpException) {
        // expected
    }

    expect(Invoice::query()->where('quote_id', $quote->id)->count())->toBe(1);
});

test('convertToInvoice creates a draft invoice with all quote data', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new QuoteService;

    $quote = $service->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-DEV-INV01',
        'currency' => 'XOF',
        'tax_rate' => 18,
        'discount' => 0,
        'discount_type' => 'percent',
        'issued_at' => now()->format('Y-m-d'),
        'valid_until' => now()->addDays(30)->format('Y-m-d'),
        'notes' => 'Note de test',
    ], [
        ['description' => 'Service D', 'quantity' => 2, 'unit_price' => 50_000],
    ]);

    $invoice = $service->convertToInvoice($quote, $company);

    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->quote_id)->toBe($quote->id)
        ->and($invoice->client_id)->toBe($quote->client_id)
        ->and($invoice->company_id)->toBe($company->id)
        ->and($invoice->currency)->toBe('XOF')
        ->and($invoice->subtotal)->toBe($quote->subtotal)
        ->and($invoice->tax_amount)->toBe($quote->tax_amount)
        ->and($invoice->total)->toBe($quote->total)
        ->and($invoice->notes)->toBe('Note de test')
        ->and($invoice->amount_paid)->toBe(0);
});

test('convertToInvoice copies all lines from the quote', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new QuoteService;

    $quote = $service->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-DEV-LIN01',
        'currency' => 'XOF',
        'tax_rate' => 18,
        'discount' => 0,
        'discount_type' => 'percent',
        'issued_at' => now()->format('Y-m-d'),
        'valid_until' => now()->addDays(30)->format('Y-m-d'),
    ], [
        ['description' => 'Ligne 1', 'quantity' => 2, 'unit_price' => 30_000],
        ['description' => 'Ligne 2', 'quantity' => 1, 'unit_price' => 20_000],
    ]);

    $invoice = $service->convertToInvoice($quote, $company);

    expect($invoice->lines)->toHaveCount(2)
        ->and($invoice->lines->first()->description)->toBe('Ligne 1')
        ->and($invoice->lines->first()->quantity)->toBe(2)
        ->and($invoice->lines->first()->unit_price)->toBe(30_000)
        ->and($invoice->lines->last()->description)->toBe('Ligne 2');
});

test('convertToInvoice marks a Sent quote as Accepted', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new QuoteService;

    $quote = $service->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-DEV-ACC02',
        'currency' => 'XOF',
        'tax_rate' => 18,
        'discount' => 0,
        'discount_type' => 'percent',
        'issued_at' => now()->format('Y-m-d'),
        'valid_until' => now()->addDays(30)->format('Y-m-d'),
    ], [
        ['description' => 'Service E', 'quantity' => 1, 'unit_price' => 50_000],
    ]);

    $service->markAsSent($quote);
    $service->convertToInvoice($quote->fresh(), $company);

    expect($quote->fresh()->status)->toBe(QuoteStatus::Accepted);
});

test('convertToInvoice does not change status of a Draft quote', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new QuoteService;

    $quote = $service->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-DEV-DRF02',
        'currency' => 'XOF',
        'tax_rate' => 18,
        'discount' => 0,
        'discount_type' => 'percent',
        'issued_at' => now()->format('Y-m-d'),
        'valid_until' => now()->addDays(30)->format('Y-m-d'),
    ], [
        ['description' => 'Service F', 'quantity' => 1, 'unit_price' => 50_000],
    ]);

    $service->convertToInvoice($quote, $company);

    expect($quote->fresh()->status)->toBe(QuoteStatus::Draft);
});

test('convertToInvoice dispatches QuoteAccepted event', function () {
    Event::fake([QuoteAccepted::class]);

    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new QuoteService;

    $quote = $service->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-DEV-EVT01',
        'currency' => 'XOF',
        'tax_rate' => 18,
        'discount' => 0,
        'discount_type' => 'percent',
        'issued_at' => now()->format('Y-m-d'),
        'valid_until' => now()->addDays(30)->format('Y-m-d'),
    ], [
        ['description' => 'Service G', 'quantity' => 1, 'unit_price' => 50_000],
    ]);

    $service->convertToInvoice($quote, $company);

    Event::assertDispatched(QuoteAccepted::class);
});

test('convertToInvoice copies discount_type from quote to invoice', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new QuoteService;

    $quote = $service->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-DEV-CONV01',
        'currency' => 'XOF',
        'tax_rate' => 18,
        'discount' => 5_000,
        'discount_type' => 'fixed',
        'issued_at' => now()->format('Y-m-d'),
        'valid_until' => now()->addDays(30)->format('Y-m-d'),
    ], [
        ['description' => 'Service A', 'quantity' => 1, 'unit_price' => 100_000],
    ]);

    $invoice = $service->convertToInvoice($quote, $company);

    expect($invoice->discount)->toBe(5_000)
        ->and($invoice->discount_type)->toBe('fixed')
        ->and($invoice->total)->toBe($quote->total);
});
