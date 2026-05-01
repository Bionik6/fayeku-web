<?php

use App\Enums\PME\ProformaStatus;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Proforma;
use App\Models\Shared\User;
use App\Services\PME\PdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createSmeForProformaPdf(): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme']);
    $company->users()->attach($user->id, ['role' => 'owner']);

    return compact('user', 'company');
}

function createProformaForPdf(Company $company, array $overrides = []): Proforma
{
    $client = Client::factory()->create(['company_id' => $company->id]);

    return Proforma::unguarded(fn () => Proforma::create(array_merge([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'FYK-PRO-'.strtoupper(fake()->unique()->bothify('??????')),
        'currency' => 'XOF',
        'status' => ProformaStatus::Draft->value,
        'issued_at' => now(),
        'valid_until' => now()->addDays(30),
        'subtotal' => 100_000,
        'tax_amount' => 18_000,
        'total' => 118_000,
        'discount' => 0,
    ], $overrides)));
}

test('public PDF route resolves a proforma by public_code', function () {
    ['company' => $company] = createSmeForProformaPdf();
    $proforma = createProformaForPdf($company);

    $response = $this->get(route('pme.proformas.pdf', $proforma));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

test('PDF route uses the 8-char public_code (no auth, no internal id)', function () {
    ['company' => $company] = createSmeForProformaPdf();
    $proforma = createProformaForPdf($company);

    $url = route('pme.proformas.pdf', $proforma);

    expect($proforma->public_code)->toMatch('/^[A-Za-z0-9]{8}$/')
        ->and($url)->toContain('/p/'.$proforma->public_code.'/pdf')
        ->and($url)->not->toContain('/proformas/'.$proforma->public_code)
        ->and($url)->not->toContain($proforma->id);
});

test('PdfService produces non-empty PDF for a proforma', function () {
    ['company' => $company] = createSmeForProformaPdf();
    $proforma = createProformaForPdf($company);

    $pdf = app(PdfService::class)->generateProforma($proforma);

    expect($pdf->output())->not->toBeEmpty()
        ->and(str_starts_with($pdf->output(), '%PDF'))->toBeTrue();
});

test('PDF view shows FACTURE PROFORMA header and proforma-specific fields', function () {
    ['company' => $company] = createSmeForProformaPdf();

    $proforma = createProformaForPdf($company, [
        'dossier_reference' => 'DAO N°2026/MEF/045',
        'payment_terms' => '30 jours fin de mois',
        'delivery_terms' => '15 jours ouvrés après BC',
    ]);

    $html = view('pdf.proforma', [
        'proforma' => $proforma->load(['company', 'client', 'lines']),
        'logoBase64' => null,
    ])->render();

    expect($html)
        ->toContain('Facture Proforma')
        ->toContain('DAO N°2026/MEF/045')
        ->toContain('30 jours fin de mois')
        ->toContain('15 jours ouvrés après BC')
        ->toContain('Document non comptable');
});

test('PDF view omits the conditions block when no proforma fields are set', function () {
    ['company' => $company] = createSmeForProformaPdf();
    $proforma = createProformaForPdf($company);

    $html = view('pdf.proforma', [
        'proforma' => $proforma->load(['company', 'client', 'lines']),
        'logoBase64' => null,
    ])->render();

    expect($html)
        ->not->toContain('Référence dossier')
        ->not->toContain('Conditions de paiement')
        ->not->toContain('Délai d\'exécution');
});

test('PDF view affiche NINEA et RCCM quand renseignés sur la company', function () {
    ['company' => $company] = createSmeForProformaPdf();
    $company->update(['ninea' => 'SN20240001', 'rccm' => 'SN-DKR-2024-B-00001']);
    $proforma = createProformaForPdf($company);

    $html = view('pdf.proforma', [
        'proforma' => $proforma->load(['company', 'client', 'lines']),
        'logoBase64' => null,
    ])->render();

    expect($html)
        ->toContain('NINEA')
        ->toContain('SN20240001')
        ->toContain('RCCM')
        ->toContain('SN-DKR-2024-B-00001');
});

test('PDF view masque NINEA et RCCM quand non renseignés', function () {
    ['company' => $company] = createSmeForProformaPdf();
    $company->update(['ninea' => null, 'rccm' => null]);
    $proforma = createProformaForPdf($company);

    $html = view('pdf.proforma', [
        'proforma' => $proforma->load(['company', 'client', 'lines']),
        'logoBase64' => null,
    ])->render();

    expect($html)
        ->not->toContain('NINEA')
        ->not->toContain('RCCM');
});

test('PDF view affiche uniquement le NINEA si seul le NINEA est renseigné', function () {
    ['company' => $company] = createSmeForProformaPdf();
    $company->update(['ninea' => 'SN20240001', 'rccm' => null]);
    $proforma = createProformaForPdf($company);

    $html = view('pdf.proforma', [
        'proforma' => $proforma->load(['company', 'client', 'lines']),
        'logoBase64' => null,
    ])->render();

    expect($html)
        ->toContain('NINEA')
        ->toContain('SN20240001')
        ->not->toContain('RCCM');
});
