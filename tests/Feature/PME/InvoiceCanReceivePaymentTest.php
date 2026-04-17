<?php

use App\Enums\PME\InvoiceStatus;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeInvoiceForCanReceivePayment(array $overrides = []): Invoice
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

test('canReceivePayment() refuse un brouillon', function () {
    $invoice = makeInvoiceForCanReceivePayment(['status' => InvoiceStatus::Draft->value]);

    expect($invoice->canReceivePayment())->toBeFalse();
});

test('canReceivePayment() refuse une facture annulée', function () {
    $invoice = makeInvoiceForCanReceivePayment(['status' => InvoiceStatus::Cancelled->value]);

    expect($invoice->canReceivePayment())->toBeFalse();
});

test('canReceivePayment() refuse une facture soldée (amount_paid >= total)', function () {
    $invoice = makeInvoiceForCanReceivePayment([
        'status' => InvoiceStatus::Paid->value,
        'amount_paid' => 100_000,
    ]);

    expect($invoice->canReceivePayment())->toBeFalse();
});

test('canReceivePayment() accepte une facture envoyée avec solde dû', function () {
    $invoice = makeInvoiceForCanReceivePayment(['status' => InvoiceStatus::Sent->value]);

    expect($invoice->canReceivePayment())->toBeTrue();
});

test('canReceivePayment() accepte une facture en retard avec solde dû', function () {
    $invoice = makeInvoiceForCanReceivePayment([
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(5),
    ]);

    expect($invoice->canReceivePayment())->toBeTrue();
});

test('canReceivePayment() accepte une facture partiellement payée', function () {
    $invoice = makeInvoiceForCanReceivePayment([
        'status' => InvoiceStatus::PartiallyPaid->value,
        'amount_paid' => 40_000,
    ]);

    expect($invoice->canReceivePayment())->toBeTrue();
});

test('canReceivePayment() refuse quand amount_paid atteint le total même sans statut Paid', function () {
    $invoice = makeInvoiceForCanReceivePayment([
        'status' => InvoiceStatus::Sent->value,
        'amount_paid' => 100_000,
    ]);

    expect($invoice->canReceivePayment())->toBeFalse();
});

test('canReceiveReminder() refuse un brouillon, une payée et une annulée', function () {
    foreach ([InvoiceStatus::Draft, InvoiceStatus::Paid, InvoiceStatus::Cancelled] as $status) {
        $invoice = makeInvoiceForCanReceivePayment(['status' => $status->value]);
        expect($invoice->canReceiveReminder())
            ->toBeFalse("Statut {$status->value} devrait refuser la relance");
    }
});

test('canReceiveReminder() accepte Sent, Overdue et PartiallyPaid', function () {
    foreach ([InvoiceStatus::Sent, InvoiceStatus::Overdue, InvoiceStatus::PartiallyPaid] as $status) {
        $invoice = makeInvoiceForCanReceivePayment(['status' => $status->value]);
        expect($invoice->canReceiveReminder())
            ->toBeTrue("Statut {$status->value} devrait autoriser la relance");
    }
});
