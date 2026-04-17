<?php

use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\PaymentMethod;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Models\PME\Payment;
use App\Services\PME\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makePaymentServiceInvoice(array $overrides = []): Invoice
{
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);

    return Invoice::unguarded(fn () => Invoice::create(array_merge([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'FYK-FAC-'.fake()->unique()->bothify('??????'),
        'currency' => 'XOF',
        'status' => InvoiceStatus::Sent->value,
        'issued_at' => now()->subDays(2),
        'due_at' => now()->addDays(28),
        'subtotal' => 100_000,
        'tax_amount' => 0,
        'total' => 100_000,
        'amount_paid' => 0,
    ], $overrides)));
}

test('record() sur un paiement partiel bascule la facture en PartiallyPaid', function () {
    $invoice = makePaymentServiceInvoice();

    app(PaymentService::class)->record($invoice, [
        'amount' => 40_000,
        'paid_at' => now()->toDateString(),
        'method' => PaymentMethod::Transfer->value,
    ]);

    $fresh = $invoice->fresh();
    expect($fresh->amount_paid)->toBe(40_000);
    expect($fresh->status)->toBe(InvoiceStatus::PartiallyPaid);
    expect($fresh->paid_at)->toBeNull();
});

test('record() qui solde exactement le total bascule en Paid avec paid_at', function () {
    $invoice = makePaymentServiceInvoice();

    app(PaymentService::class)->record($invoice, [
        'amount' => 100_000,
        'paid_at' => now()->toDateString(),
        'method' => PaymentMethod::Cash->value,
    ]);

    $fresh = $invoice->fresh();
    expect($fresh->status)->toBe(InvoiceStatus::Paid);
    expect($fresh->amount_paid)->toBe(100_000);
    expect($fresh->paid_at)->not->toBeNull();
});

test('record() qui dépasse le total reste en Paid', function () {
    $invoice = makePaymentServiceInvoice();

    app(PaymentService::class)->record($invoice, [
        'amount' => 120_000,
        'paid_at' => now()->toDateString(),
        'method' => PaymentMethod::Transfer->value,
    ]);

    $fresh = $invoice->fresh();
    expect($fresh->status)->toBe(InvoiceStatus::Paid);
    expect($fresh->amount_paid)->toBe(120_000);
});

test('delete() d\'un paiement recalcule le statut selon la nouvelle somme', function () {
    $invoice = makePaymentServiceInvoice();
    $service = app(PaymentService::class);

    $p1 = $service->record($invoice, [
        'amount' => 60_000,
        'paid_at' => now()->toDateString(),
        'method' => PaymentMethod::Transfer->value,
    ]);
    $service->record($invoice, [
        'amount' => 40_000,
        'paid_at' => now()->toDateString(),
        'method' => PaymentMethod::Cash->value,
    ]);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);

    $service->delete($p1);

    $fresh = $invoice->fresh();
    expect($fresh->amount_paid)->toBe(40_000);
    expect($fresh->status)->toBe(InvoiceStatus::PartiallyPaid);
    expect($fresh->paid_at)->toBeNull();
});

test('delete() du dernier paiement bascule en Sent si pas en retard', function () {
    $invoice = makePaymentServiceInvoice(['due_at' => now()->addDays(5)]);
    $service = app(PaymentService::class);

    $payment = $service->record($invoice, [
        'amount' => 30_000,
        'paid_at' => now()->toDateString(),
        'method' => PaymentMethod::Transfer->value,
    ]);

    $service->delete($payment);

    $fresh = $invoice->fresh();
    expect($fresh->amount_paid)->toBe(0);
    expect($fresh->status)->toBe(InvoiceStatus::Sent);
});

test('delete() du dernier paiement bascule en Overdue si échéance dépassée', function () {
    $invoice = makePaymentServiceInvoice([
        'due_at' => now()->subDays(5),
        'status' => InvoiceStatus::PartiallyPaid->value,
    ]);
    $service = app(PaymentService::class);

    $payment = $service->record($invoice, [
        'amount' => 30_000,
        'paid_at' => now()->toDateString(),
        'method' => PaymentMethod::Transfer->value,
    ]);

    $service->delete($payment);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Overdue);
});

test('record() persiste la méthode, la référence et l\'auteur', function () {
    $invoice = makePaymentServiceInvoice();

    $payment = app(PaymentService::class)->record($invoice, [
        'amount' => 25_000,
        'paid_at' => now()->toDateString(),
        'method' => PaymentMethod::MobileMoney->value,
        'reference' => 'WAVE-42',
        'notes' => 'Reçu via Wave',
    ]);

    expect($payment)->toBeInstanceOf(Payment::class);
    expect($payment->method)->toBe(PaymentMethod::MobileMoney);
    expect($payment->reference)->toBe('WAVE-42');
    expect($payment->notes)->toBe('Reçu via Wave');
});
