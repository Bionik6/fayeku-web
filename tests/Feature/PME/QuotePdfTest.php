<?php

use App\Enums\PME\QuoteStatus;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Quote;
use App\Models\Shared\User;
use App\Services\PME\PdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function createSmeUserForQuotePdf(): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme']);
    $company->users()->attach($user->id, ['role' => 'owner']);

    return compact('user', 'company');
}

function createQuoteForPdf(Company $company, array $overrides = []): Quote
{
    $client = Client::factory()->create(['company_id' => $company->id]);

    return Quote::unguarded(fn () => Quote::create(array_merge([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'DEV-'.fake()->unique()->numerify('######'),
        'currency' => 'XOF',
        'status' => QuoteStatus::Draft->value,
        'issued_at' => now(),
        'valid_until' => now()->addDays(30),
        'subtotal' => 100_000,
        'tax_amount' => 18_000,
        'total' => 118_000,
        'discount' => 0,
    ], $overrides)));
}

// ─── Accès & sécurité ─────────────────────────────────────────────────────────

test('un visiteur non authentifie peut telecharger le PDF devis via le public_code', function () {
    ['company' => $company] = createSmeUserForQuotePdf();
    $quote = createQuoteForPdf($company);

    $response = $this->get(route('pme.quotes.pdf', $quote));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

test('la route PDF devis utilise le public_code (8 caracteres alphanumeriques)', function () {
    ['company' => $company] = createSmeUserForQuotePdf();
    $quote = createQuoteForPdf($company);

    $url = route('pme.quotes.pdf', $quote);

    expect($quote->public_code)
        ->toMatch('/^[A-Za-z0-9]{8}$/')
        ->and($url)->toContain('/d/'.$quote->public_code.'/pdf')
        ->and($url)->not->toContain('/pme/')
        ->and($url)->not->toContain('/quotes/')
        ->and($url)->not->toContain($quote->id);
});

// ─── PdfService ──────────────────────────────────────────────────────────────

test('PdfService génère un contenu PDF non vide pour un devis', function () {
    ['company' => $company] = createSmeUserForQuotePdf();
    $quote = createQuoteForPdf($company);

    $pdfService = app(PdfService::class);
    $pdf = $pdfService->generateQuote($quote);

    expect($pdf->output())->not->toBeEmpty()
        ->and(str_starts_with($pdf->output(), '%PDF'))->toBeTrue();
});

// ─── Remise dans le PDF ──────────────────────────────────────────────────────

test('le PDF devis affiche la remise en pourcentage correctement', function () {
    ['company' => $company] = createSmeUserForQuotePdf();

    $quote = createQuoteForPdf($company, [
        'subtotal' => 100_000,
        'tax_amount' => 16_200,
        'total' => 106_200,
        'discount' => 10,
    ]);

    $html = view('pdf.quote', ['quote' => $quote->load(['company', 'client', 'lines']), 'logoBase64' => null])->render();

    expect($html)
        ->toContain('Remise (10%)')
        ->toContain('10 000')
        ->not->toContain('montant fixe');
});

test('le PDF devis n\'affiche pas de ligne remise quand elle est nulle', function () {
    ['company' => $company] = createSmeUserForQuotePdf();
    $quote = createQuoteForPdf($company, ['discount' => 0]);

    $html = view('pdf.quote', ['quote' => $quote->load(['company', 'client', 'lines']), 'logoBase64' => null])->render();

    expect($html)->not->toContain('Remise');
});

// ─── Mentions légales (NINEA / RCCM) ─────────────────────────────────────────

test('le PDF devis affiche NINEA et RCCM quand renseignés', function () {
    ['company' => $company] = createSmeUserForQuotePdf();
    $company->update(['ninea' => 'SN20240001', 'rccm' => 'SN-DKR-2024-B-00001']);
    $quote = createQuoteForPdf($company);

    $html = view('pdf.quote', ['quote' => $quote->load(['company', 'client', 'lines']), 'logoBase64' => null])->render();

    expect($html)
        ->toContain('NINEA')
        ->toContain('SN20240001')
        ->toContain('RCCM')
        ->toContain('SN-DKR-2024-B-00001');
});

test('le PDF devis masque NINEA et RCCM quand non renseignés', function () {
    ['company' => $company] = createSmeUserForQuotePdf();
    $company->update(['ninea' => null, 'rccm' => null]);
    $quote = createQuoteForPdf($company);

    $html = view('pdf.quote', ['quote' => $quote->load(['company', 'client', 'lines']), 'logoBase64' => null])->render();

    expect($html)
        ->not->toContain('NINEA')
        ->not->toContain('RCCM');
});
