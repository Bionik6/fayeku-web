<?php

use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\PaymentMethod;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Models\PME\Payment;
use App\Services\PME\InvoiceService;
use App\Services\PME\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

test('markAsSent changes status to Sent and stamps sent_at', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $invoice = Invoice::factory()->forCompany($company)->draft()->create();
    $service = new InvoiceService;

    $result = $service->markAsSent($invoice);

    expect($result->status)->toBe(InvoiceStatus::Sent)
        ->and($result->sent_at)->not->toBeNull();
});

test('markAsSent preserves the original sent_at on re-mark', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $invoice = Invoice::factory()->forCompany($company)->draft()->create();
    $service = new InvoiceService;

    $service->markAsSent($invoice);
    $original = $invoice->fresh()->sent_at;

    $this->travel(5)->minutes();
    $service->markAsSent($invoice->fresh());

    expect($invoice->fresh()->sent_at?->toIso8601String())->toBe($original->toIso8601String());
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

// ─── Deposit: resolveDepositAmount ───────────────────────────────────────────

test('resolveDepositAmount returns 0 when deposit is null, zero, or negative', function () {
    $service = new InvoiceService;

    expect($service->resolveDepositAmount(100_000, null))->toBe(0)
        ->and($service->resolveDepositAmount(100_000, 0))->toBe(0)
        ->and($service->resolveDepositAmount(100_000, -1_000))->toBe(0);
});

test('resolveDepositAmount returns 0 when total is 0', function () {
    $service = new InvoiceService;

    expect($service->resolveDepositAmount(0, 50_000))->toBe(0);
});

test('resolveDepositAmount returns the deposit when below the total', function () {
    $service = new InvoiceService;

    expect($service->resolveDepositAmount(100_000, 30_000))->toBe(30_000)
        ->and($service->resolveDepositAmount(100_000, 100_000))->toBe(100_000);
});

test('resolveDepositAmount caps the deposit at the total', function () {
    $service = new InvoiceService;

    expect($service->resolveDepositAmount(100_000, 200_000))->toBe(100_000);
});

// ─── Deposit: create with deposit ────────────────────────────────────────────

test('create with deposit stores deposit_amount and creates a deposit Payment', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new InvoiceService;

    $invoice = $service->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-FAC-DEPFX',
        'currency' => 'XOF',
        'tax_rate' => 18,
        'discount' => 0,
        'deposit_amount' => 30_000,
        'issued_at' => now()->format('Y-m-d'),
        'due_at' => now()->addDays(30)->format('Y-m-d'),
        'payment_method' => 'cash',
    ], [
        ['description' => 'Service', 'quantity' => 1, 'unit_price' => 100_000],
    ]);

    expect($invoice->total)->toBe(118_000)
        ->and($invoice->deposit_amount)->toBe(30_000)
        ->and($invoice->amount_paid)->toBe(30_000)
        ->and($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->payments)->toHaveCount(1)
        ->and($invoice->payments->first()->is_deposit)->toBeTrue()
        ->and($invoice->payments->first()->amount)->toBe(30_000)
        ->and($invoice->payments->first()->method)->toBe(PaymentMethod::Cash);
});

test('create caps the deposit_amount at the total when overpaid', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new InvoiceService;

    $invoice = $service->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-FAC-DEPCAP',
        'currency' => 'XOF',
        'tax_rate' => 0,
        'discount' => 0,
        'deposit_amount' => 200_000,
        'issued_at' => now()->format('Y-m-d'),
        'due_at' => now()->addDays(30)->format('Y-m-d'),
    ], [
        ['description' => 'Service', 'quantity' => 1, 'unit_price' => 100_000],
    ]);

    expect($invoice->total)->toBe(100_000)
        ->and($invoice->deposit_amount)->toBe(100_000)
        ->and($invoice->amount_paid)->toBe(100_000)
        ->and($invoice->payments->first()->amount)->toBe(100_000);
});

test('create with full deposit covers the invoice but stays in Draft', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new InvoiceService;

    $invoice = $service->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-FAC-DEPFULL',
        'currency' => 'XOF',
        'tax_rate' => 0,
        'discount' => 0,
        'deposit_amount' => 50_000,
        'issued_at' => now()->format('Y-m-d'),
        'due_at' => now()->addDays(30)->format('Y-m-d'),
    ], [
        ['description' => 'Service', 'quantity' => 1, 'unit_price' => 50_000],
    ]);

    expect($invoice->total)->toBe(50_000)
        ->and($invoice->amount_paid)->toBe(50_000)
        ->and($invoice->status)->toBe(InvoiceStatus::Draft);
});

test('create without deposit creates no Payment', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new InvoiceService;

    $invoice = $service->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-FAC-NODEP',
        'currency' => 'XOF',
        'tax_rate' => 18,
        'discount' => 0,
        'issued_at' => now()->format('Y-m-d'),
        'due_at' => now()->addDays(30)->format('Y-m-d'),
    ], [
        ['description' => 'Service', 'quantity' => 1, 'unit_price' => 100_000],
    ]);

    expect($invoice->deposit_amount)->toBe(0)
        ->and($invoice->amount_paid)->toBe(0)
        ->and($invoice->payments)->toHaveCount(0);
});

test('create maps payment_method strings to PaymentMethod enum on the deposit Payment', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new InvoiceService;

    $cases = [
        'wave' => PaymentMethod::MobileMoney,
        'orange_money' => PaymentMethod::MobileMoney,
        'cash' => PaymentMethod::Cash,
        'bank_transfer' => PaymentMethod::Transfer,
    ];

    foreach ($cases as $formMethod => $expectedEnum) {
        $invoice = $service->create($company, [
            'client_id' => $client->id,
            'reference' => 'FYK-FAC-MAP-'.strtoupper(substr($formMethod, 0, 4)),
            'currency' => 'XOF',
            'tax_rate' => 0,
            'discount' => 0,
            'deposit_amount' => 10_000,
            'payment_method' => $formMethod,
            'issued_at' => now()->format('Y-m-d'),
            'due_at' => now()->addDays(30)->format('Y-m-d'),
        ], [
            ['description' => 'Service', 'quantity' => 1, 'unit_price' => 50_000],
        ]);

        expect($invoice->payments->first()->method)->toBe($expectedEnum);
    }
});

test('create stamps the deposit Payment paid_at with the invoice issued_at', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new InvoiceService;

    $issuedAt = '2026-04-15';

    $invoice = $service->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-FAC-DEPDT',
        'currency' => 'XOF',
        'tax_rate' => 0,
        'discount' => 0,
        'deposit_amount' => 10_000,
        'issued_at' => $issuedAt,
        'due_at' => '2026-05-15',
    ], [
        ['description' => 'Service', 'quantity' => 1, 'unit_price' => 50_000],
    ]);

    expect($invoice->payments->first()->paid_at->format('Y-m-d'))->toBe($issuedAt);
});

// ─── Deposit: update flow ────────────────────────────────────────────────────

test('update modifies the deposit Payment in place when amount changes', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new InvoiceService;

    $invoice = $service->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-FAC-UPDDEP',
        'currency' => 'XOF',
        'tax_rate' => 0,
        'discount' => 0,
        'deposit_amount' => 10_000,
        'issued_at' => now()->format('Y-m-d'),
        'due_at' => now()->addDays(30)->format('Y-m-d'),
    ], [
        ['description' => 'Service', 'quantity' => 1, 'unit_price' => 50_000],
    ]);

    $service->update($invoice, [
        'client_id' => $client->id,
        'currency' => 'XOF',
        'tax_rate' => 0,
        'discount' => 0,
        'deposit_amount' => 25_000,
        'issued_at' => now()->format('Y-m-d'),
        'due_at' => now()->addDays(30)->format('Y-m-d'),
    ], [
        ['description' => 'Service', 'quantity' => 1, 'unit_price' => 50_000],
    ]);

    $invoice->refresh();

    expect($invoice->deposit_amount)->toBe(25_000)
        ->and($invoice->amount_paid)->toBe(25_000)
        ->and($invoice->payments()->where('is_deposit', true)->count())->toBe(1)
        ->and((int) $invoice->payments()->where('is_deposit', true)->sum('amount'))->toBe(25_000);
});

test('update removes the deposit Payment when deposit is cleared', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new InvoiceService;

    $invoice = $service->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-FAC-CLR',
        'currency' => 'XOF',
        'tax_rate' => 0,
        'discount' => 0,
        'deposit_amount' => 10_000,
        'issued_at' => now()->format('Y-m-d'),
        'due_at' => now()->addDays(30)->format('Y-m-d'),
    ], [
        ['description' => 'Service', 'quantity' => 1, 'unit_price' => 50_000],
    ]);

    $service->update($invoice, [
        'client_id' => $client->id,
        'currency' => 'XOF',
        'tax_rate' => 0,
        'discount' => 0,
        'deposit_amount' => 0,
        'issued_at' => now()->format('Y-m-d'),
        'due_at' => now()->addDays(30)->format('Y-m-d'),
    ], [
        ['description' => 'Service', 'quantity' => 1, 'unit_price' => 50_000],
    ]);

    $invoice->refresh();

    expect($invoice->deposit_amount)->toBe(0)
        ->and($invoice->amount_paid)->toBe(0)
        ->and($invoice->payments()->where('is_deposit', true)->count())->toBe(0);
});

test('update preserves non-deposit Payments when syncing the deposit', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new InvoiceService;
    $paymentService = app(PaymentService::class);

    $invoice = $service->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-FAC-MIX',
        'currency' => 'XOF',
        'tax_rate' => 0,
        'discount' => 0,
        'deposit_amount' => 10_000,
        'issued_at' => now()->format('Y-m-d'),
        'due_at' => now()->addDays(30)->format('Y-m-d'),
    ], [
        ['description' => 'Service', 'quantity' => 1, 'unit_price' => 100_000],
    ]);

    $service->markAsSent($invoice);

    // Manual partial payment recorded after the fact
    $paymentService->record($invoice->fresh(), [
        'amount' => 20_000,
        'paid_at' => now(),
        'method' => 'transfer',
    ]);

    // Now bump the deposit to 15 000 — manual payment must remain
    $service->update($invoice->fresh(), [
        'client_id' => $client->id,
        'currency' => 'XOF',
        'tax_rate' => 0,
        'discount' => 0,
        'deposit_amount' => 15_000,
        'issued_at' => now()->format('Y-m-d'),
        'due_at' => now()->addDays(30)->format('Y-m-d'),
    ], [
        ['description' => 'Service', 'quantity' => 1, 'unit_price' => 100_000],
    ]);

    $invoice->refresh();

    expect($invoice->payments()->where('is_deposit', true)->count())->toBe(1)
        ->and($invoice->payments()->where('is_deposit', false)->count())->toBe(1)
        ->and($invoice->amount_paid)->toBe(35_000); // 15 000 deposit + 20 000 manual
});

// ─── Deposit + markAsSent status logic ───────────────────────────────────────

test('markAsSent transitions to PartiallyPaid when an acompte was set', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new InvoiceService;

    $invoice = $service->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-FAC-SENTPP',
        'currency' => 'XOF',
        'tax_rate' => 0,
        'discount' => 0,
        'deposit_amount' => 30_000,
        'issued_at' => now()->format('Y-m-d'),
        'due_at' => now()->addDays(30)->format('Y-m-d'),
    ], [
        ['description' => 'Service', 'quantity' => 1, 'unit_price' => 100_000],
    ]);

    $service->markAsSent($invoice);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::PartiallyPaid)
        ->and($invoice->fresh()->sent_at)->not->toBeNull();
});

test('markAsSent transitions to Paid when the deposit covers the total', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new InvoiceService;

    $invoice = $service->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-FAC-SENTPD',
        'currency' => 'XOF',
        'tax_rate' => 0,
        'discount' => 0,
        'deposit_amount' => 50_000,
        'issued_at' => now()->format('Y-m-d'),
        'due_at' => now()->addDays(30)->format('Y-m-d'),
    ], [
        ['description' => 'Service', 'quantity' => 1, 'unit_price' => 50_000],
    ]);

    $service->markAsSent($invoice);

    $fresh = $invoice->fresh();

    expect($fresh->status)->toBe(InvoiceStatus::Paid)
        ->and($fresh->paid_at)->not->toBeNull();
});

test('markAsSent stays Sent when no acompte was set', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new InvoiceService;

    $invoice = $service->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-FAC-SENTNO',
        'currency' => 'XOF',
        'tax_rate' => 0,
        'discount' => 0,
        'issued_at' => now()->format('Y-m-d'),
        'due_at' => now()->addDays(30)->format('Y-m-d'),
    ], [
        ['description' => 'Service', 'quantity' => 1, 'unit_price' => 100_000],
    ]);

    $service->markAsSent($invoice);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
});
