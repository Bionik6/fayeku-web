<?php

use App\Enums\PME\InvoiceStatus;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Services\PME\ClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeClientVisibilityCompany(): Company
{
    return Company::factory()->create(['type' => 'sme']);
}

function makeClientVisibilityInvoice(Client $client, array $overrides = []): Invoice
{
    return Invoice::unguarded(fn () => Invoice::create(array_merge([
        'company_id' => $client->company_id,
        'client_id' => $client->id,
        'reference' => 'FYK-FAC-'.fake()->unique()->numerify('######'),
        'currency' => 'XOF',
        'status' => InvoiceStatus::Sent->value,
        'issued_at' => now()->subDays(5),
        'due_at' => now()->addDays(25),
        'subtotal' => 100_000,
        'tax_amount' => 18_000,
        'total' => 118_000,
        'amount_paid' => 0,
    ], $overrides)));
}

test('la fiche client inclut les factures brouillon dans la liste', function () {
    $company = makeClientVisibilityCompany();
    $client = Client::factory()->create(['company_id' => $company->id]);

    $draft = makeClientVisibilityInvoice($client, [
        'reference' => 'FYK-FAC-DRAFT01',
        'status' => InvoiceStatus::Draft->value,
    ]);
    $sent = makeClientVisibilityInvoice($client, [
        'reference' => 'FYK-FAC-SENT01',
        'status' => InvoiceStatus::Sent->value,
    ]);

    $detail = app(ClientService::class)->detail($client);
    $references = collect($detail['invoices'])->pluck('reference');

    expect($references)->toContain('FYK-FAC-DRAFT01');
    expect($references)->toContain('FYK-FAC-SENT01');
});

test('la fiche client inclut les factures annulées dans la liste', function () {
    $company = makeClientVisibilityCompany();
    $client = Client::factory()->create(['company_id' => $company->id]);

    makeClientVisibilityInvoice($client, [
        'reference' => 'FYK-FAC-CAN01',
        'status' => InvoiceStatus::Cancelled->value,
    ]);

    $detail = app(ClientService::class)->detail($client);

    expect(collect($detail['invoices'])->pluck('reference'))->toContain('FYK-FAC-CAN01');
});

test('la timeline client n\'inclut pas d\'événement « Facture envoyée » pour un brouillon', function () {
    $company = makeClientVisibilityCompany();
    $client = Client::factory()->create(['company_id' => $company->id]);

    makeClientVisibilityInvoice($client, [
        'reference' => 'FYK-FAC-DRAFT02',
        'status' => InvoiceStatus::Draft->value,
    ]);

    $timeline = app(ClientService::class)->detail($client)['timeline'];

    expect(collect($timeline)->where('title', 'Facture envoyée')->pluck('body')->all())
        ->not->toContain('FYK-FAC-DRAFT02 · 118 000 FCFA');
});
