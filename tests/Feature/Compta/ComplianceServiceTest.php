<?php

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use App\Enums\Compta\CertificationAuthority;
use App\Services\Compta\ComplianceService;
use App\Services\Compta\DGIDConnector;
use App\Services\Compta\FNEFiscalConnector;
use App\Enums\PME\InvoiceStatus;
use App\Models\PME\Invoice;

uses(RefreshDatabase::class);

function makeFneInvoice(): Invoice
{
    return Invoice::factory()->forCompany(['country_code' => 'CI'])->create();
}

function makeDgidInvoice(): Invoice
{
    return Invoice::factory()->forCompany(['country_code' => 'SN'])->create();
}

function fakeSuccessfulFneResponse(array $overrides = []): void
{
    config(['fayeku.fne_api_url' => 'https://fne-api.test']);

    Http::fake([
        'https://fne-api.test/*' => Http::response(array_merge([
            'reference' => 'REF-FNE-001',
            'token' => 'tok-abc123',
            'balance_sticker' => 42_500,
        ], $overrides), 200),
    ]);
}

// ─── FNE — succès ─────────────────────────────────────────────────────────────

test('certify() écrit certification_authority = fne pour un pays CI', function () {
    fakeSuccessfulFneResponse();
    $invoice = makeFneInvoice();

    (new ComplianceService([new FNEFiscalConnector]))->certify($invoice);

    expect($invoice->fresh()->certification_authority)->toBe(CertificationAuthority::FNE);
});

test('certify() écrit les clés reference et token dans certification_data', function () {
    fakeSuccessfulFneResponse(['reference' => 'REF-001', 'token' => 'tok-xyz']);
    $invoice = makeFneInvoice();

    (new ComplianceService([new FNEFiscalConnector]))->certify($invoice);

    $data = $invoice->fresh()->certification_data;
    expect($data['reference'])->toBe('REF-001');
    expect($data['token'])->toBe('tok-xyz');
});

test('certify() pose certified_at comme chaîne ISO dans certification_data', function () {
    fakeSuccessfulFneResponse();
    $invoice = makeFneInvoice();

    (new ComplianceService([new FNEFiscalConnector]))->certify($invoice);

    $certifiedAt = $invoice->fresh()->certification_data['certified_at'];
    expect($certifiedAt)->toBeString();
    expect(fn () => Carbon::parse($certifiedAt))->not->toThrow(Exception::class);
});

test('certify() passe le statut à Certified', function () {
    fakeSuccessfulFneResponse();
    $invoice = makeFneInvoice();

    (new ComplianceService([new FNEFiscalConnector]))->certify($invoice);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Certified);
});

test('certification_data ne contient pas balance_sticker quand il est null', function () {
    config(['fayeku.fne_api_url' => 'https://fne-api.test']);

    Http::fake([
        'https://fne-api.test/*' => Http::response([
            'reference' => 'REF-001',
            'token' => 'tok-xyz',
            // balance_sticker absent
        ], 200),
    ]);
    $invoice = makeFneInvoice();

    (new ComplianceService([new FNEFiscalConnector]))->certify($invoice);

    expect($invoice->fresh()->certification_data)->not->toHaveKey('balance_sticker');
});

// ─── DGID — erreur attendue ────────────────────────────────────────────────────

test('certify() passe le statut à CertificationFailed et rethrow pour DGID', function () {
    $invoice = makeDgidInvoice();

    expect(fn () => (new ComplianceService([new DGIDConnector]))->certify($invoice))
        ->toThrow(RuntimeException::class);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::CertificationFailed);
});

// ─── Aucun connecteur ─────────────────────────────────────────────────────────

test('certify() ne fait rien si aucun connecteur ne supporte le pays', function () {
    $invoice = Invoice::factory()->forCompany(['country_code' => 'GH'])->create();

    (new ComplianceService([new FNEFiscalConnector]))->certify($invoice);

    $fresh = $invoice->fresh();
    expect($fresh->certification_authority)->toBeNull();
    expect($fresh->status)->toBe(InvoiceStatus::Draft);
});
