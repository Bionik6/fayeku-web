<?php

use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\ProformaStatus;
use App\Events\PME\ProformaConverted;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Models\PME\Proforma;
use App\Services\PME\ProformaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

function makeProformaPayload(string $clientId, array $overrides = []): array
{
    return array_merge([
        'client_id' => $clientId,
        'reference' => 'FYK-PRO-'.strtoupper(fake()->bothify('??????')),
        'currency' => 'XOF',
        'tax_rate' => 18,
        'discount' => 0,
        'discount_type' => 'percent',
        'issued_at' => now()->format('Y-m-d'),
        'valid_until' => now()->addDays(30)->format('Y-m-d'),
        'dossier_reference' => null,
        'payment_terms' => null,
        'delivery_terms' => null,
        'notes' => null,
    ], $overrides);
}

// ─── Reference generation ────────────────────────────────────────────────────

test('generateReference returns FYK-PRO-XXXXXX format', function () {
    $company = Company::factory()->create(['type' => 'sme']);

    $ref = (new ProformaService)->generateReference($company);

    expect($ref)->toMatch('/^FYK-PRO-[A-Z0-9]{6}$/');
});

test('generateReference returns unique references', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $service = new ProformaService;

    $refs = collect(range(1, 20))->map(fn () => $service->generateReference($company));

    expect($refs->unique())->toHaveCount(20);
});

// ─── Totals ──────────────────────────────────────────────────────────────────

test('calculateProformaTotals computes subtotal + tax', function () {
    $totals = (new ProformaService)->calculateProformaTotals([
        ['quantity' => 2, 'unit_price' => 50_000],
        ['quantity' => 1, 'unit_price' => 30_000],
    ], 18);

    expect($totals['subtotal'])->toBe(130_000)
        ->and($totals['tax_amount'])->toBe(23_400)
        ->and($totals['total'])->toBe(153_400);
});

test('calculateProformaTotals applies percent discount before tax', function () {
    $totals = (new ProformaService)->calculateProformaTotals(
        [['quantity' => 10, 'unit_price' => 10_000], ['quantity' => 5, 'unit_price' => 20_000]],
        taxRate: 18, discount: 10, discountType: 'percent'
    );

    expect($totals['subtotal'])->toBe(200_000)
        ->and($totals['discount_amount'])->toBe(20_000)
        ->and($totals['tax_amount'])->toBe(32_400)
        ->and($totals['total'])->toBe(212_400);
});

// ─── Create / update ─────────────────────────────────────────────────────────

test('create stores proforma with lines and proforma-specific fields', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new ProformaService;

    $proforma = $service->create($company, makeProformaPayload($client->id, [
        'dossier_reference' => 'DAO N°2026/MEF/045',
        'payment_terms' => '30 jours fin de mois',
        'delivery_terms' => '15 jours ouvrés après BC',
        'notes' => 'Note de test',
    ]), [
        ['description' => 'Service A', 'quantity' => 2, 'unit_price' => 50_000],
        ['description' => 'Service B', 'quantity' => 1, 'unit_price' => 30_000],
    ]);

    expect($proforma)->toBeInstanceOf(Proforma::class)
        ->and($proforma->status)->toBe(ProformaStatus::Draft)
        ->and($proforma->subtotal)->toBe(130_000)
        ->and($proforma->total)->toBe(153_400)
        ->and($proforma->dossier_reference)->toBe('DAO N°2026/MEF/045')
        ->and($proforma->payment_terms)->toBe('30 jours fin de mois')
        ->and($proforma->delivery_terms)->toBe('15 jours ouvrés après BC')
        ->and($proforma->notes)->toBe('Note de test')
        ->and($proforma->lines)->toHaveCount(2);
});

test('update replaces lines and persists new proforma fields', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new ProformaService;

    $proforma = $service->create($company, makeProformaPayload($client->id), [
        ['description' => 'Old', 'quantity' => 1, 'unit_price' => 10_000],
    ]);

    $updated = $service->update($proforma, makeProformaPayload($client->id, [
        'reference' => $proforma->reference,
        'dossier_reference' => 'UPDATED-DOSSIER',
        'payment_terms' => 'À réception',
    ]), [
        ['description' => 'New', 'quantity' => 3, 'unit_price' => 25_000],
    ]);

    expect($updated->lines)->toHaveCount(1)
        ->and($updated->lines->first()->description)->toBe('New')
        ->and($updated->subtotal)->toBe(75_000)
        ->and($updated->dossier_reference)->toBe('UPDATED-DOSSIER')
        ->and($updated->payment_terms)->toBe('À réception');
});

// ─── Status transitions ──────────────────────────────────────────────────────

test('markAsSent → markAsPoReceived transitions', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new ProformaService;

    $proforma = $service->create($company, makeProformaPayload($client->id), [
        ['description' => 'X', 'quantity' => 1, 'unit_price' => 50_000],
    ]);

    $service->markAsSent($proforma);
    expect($proforma->fresh()->status)->toBe(ProformaStatus::Sent);

    $service->markAsPoReceived($proforma);
    expect($proforma->fresh()->status)->toBe(ProformaStatus::PoReceived);
});

test('canEdit allows draft and sent only', function () {
    $service = new ProformaService;
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);

    $draft = $service->create($company, makeProformaPayload($client->id), [
        ['description' => 'X', 'quantity' => 1, 'unit_price' => 10_000],
    ]);

    expect($service->canEdit($draft))->toBeTrue();

    $service->markAsSent($draft);
    expect($service->canEdit($draft->fresh()))->toBeTrue();

    $service->markAsPoReceived($draft);
    expect($service->canEdit($draft->fresh()))->toBeFalse();
});

// ─── convertToInvoice ────────────────────────────────────────────────────────

test('convertToInvoice creates a draft invoice with proforma_id and copies fields', function () {
    Event::fake([ProformaConverted::class]);

    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new ProformaService;

    $proforma = $service->create($company, makeProformaPayload($client->id, [
        'payment_terms' => '50% commande, 50% livraison',
        'notes' => 'Note proforma',
    ]), [
        ['description' => 'Service A', 'quantity' => 2, 'unit_price' => 50_000],
    ]);

    $invoice = $service->convertToInvoice($proforma, $company);

    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->proforma_id)->toBe($proforma->id)
        ->and($invoice->client_id)->toBe($client->id)
        ->and($invoice->company_id)->toBe($company->id)
        ->and($invoice->total)->toBe($proforma->total)
        ->and($invoice->payment_terms)->toBe('50% commande, 50% livraison')
        ->and($invoice->notes)->toBe('Note proforma')
        ->and($invoice->lines)->toHaveCount(1)
        ->and($invoice->lines->first()->description)->toBe('Service A');

    expect($proforma->fresh()->status)->toBe(ProformaStatus::Converted);

    Event::assertDispatched(ProformaConverted::class);
});

test('convertToInvoice aborts with 409 on duplicate conversion', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $service = new ProformaService;

    $proforma = $service->create($company, makeProformaPayload($client->id), [
        ['description' => 'X', 'quantity' => 1, 'unit_price' => 50_000],
    ]);

    $service->convertToInvoice($proforma, $company);

    try {
        $service->convertToInvoice($proforma->fresh(), $company);
        $this->fail('Expected HttpException was not thrown.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(409)
            ->and($e->getMessage())->toBe('Cette proforma a déjà été convertie en facture.');
    }

    expect(Invoice::query()->where('proforma_id', $proforma->id)->count())->toBe(1);
});
