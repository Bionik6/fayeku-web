<?php

use App\Enums\PME\InvoiceStatus;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Models\PME\InvoiceLine;
use App\Models\Shared\User;
use App\Services\PME\PdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function createSmeUserForPdf(): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme']);
    $company->users()->attach($user->id, ['role' => 'owner']);

    return compact('user', 'company');
}

function createInvoiceForPdf(Company $company): Invoice
{
    $client = Client::factory()->create(['company_id' => $company->id]);

    $invoice = Invoice::factory()
        ->forCompany($company)
        ->withClient($client)
        ->draft()
        ->create(['currency' => 'XOF']);

    InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'description' => 'Service test',
        'quantity' => 2,
        'unit_price' => 50_000,
        'tax_rate' => 18,
        'total' => 100_000,
    ]);

    return $invoice;
}

// ─── Access control ──────────────────────────────────────────────────────────

test('un visiteur non authentifie peut telecharger le PDF via le public_code', function () {
    ['company' => $company] = createSmeUserForPdf();
    $invoice = createInvoiceForPdf($company);

    $response = $this->get(route('pme.invoices.pdf', $invoice));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

test('la route PDF utilise le public_code (8 caracteres alphanumeriques)', function () {
    ['company' => $company] = createSmeUserForPdf();
    $invoice = createInvoiceForPdf($company);

    $url = route('pme.invoices.pdf', $invoice);

    expect($invoice->public_code)
        ->toMatch('/^[A-Za-z0-9]{8}$/')
        ->and($url)->toContain('/invoices/'.$invoice->public_code.'/pdf')
        ->and($url)->not->toContain('/pme/')
        ->and($url)->not->toContain($invoice->id);
});

test('un public_code inconnu renvoie 404', function () {
    $this->get('/invoices/ZZZZZZZZ/pdf')->assertNotFound();
});

// ─── PdfService ─────────────────────────────────────────────────────────────

test('PdfService génère un contenu PDF non vide', function () {
    ['company' => $company] = createSmeUserForPdf();
    $invoice = createInvoiceForPdf($company);

    $pdfService = app(PdfService::class);
    $content = $pdfService->rawContent($invoice);

    expect($content)->not->toBeEmpty()
        ->and(str_starts_with($content, '%PDF'))->toBeTrue();
});

test('PdfService stream retourne une réponse HTTP valide', function () {
    ['company' => $company] = createSmeUserForPdf();
    $invoice = createInvoiceForPdf($company);

    $pdfService = app(PdfService::class);
    $response = $pdfService->stream($invoice);

    expect($response->getStatusCode())->toBe(200);
});

// ─── Remise dans le PDF ──────────────────────────────────────────────────────

test('le PDF facture affiche la remise en pourcentage correctement', function () {
    ['company' => $company] = createSmeUserForPdf();
    $client = Client::factory()->create(['company_id' => $company->id]);

    $invoice = Invoice::unguarded(fn () => Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'FYK-FAC-REMPC',
        'currency' => 'XOF',
        'status' => InvoiceStatus::Draft,
        'issued_at' => now(),
        'due_at' => now()->addDays(30),
        'subtotal' => 100_000,
        'tax_amount' => 16_200,
        'total' => 106_200,
        'discount' => 10,
        'discount_type' => 'percent',
        'amount_paid' => 0,
    ]));

    $html = view('pdf.invoice', ['invoice' => $invoice->load(['company', 'client', 'lines']), 'logoBase64' => null])->render();

    expect($html)
        ->toContain('Remise (10%)')
        ->toContain('10 000')
        ->not->toContain('montant fixe');
});

test('le PDF facture affiche la remise en montant fixe correctement', function () {
    ['company' => $company] = createSmeUserForPdf();
    $client = Client::factory()->create(['company_id' => $company->id]);

    $invoice = Invoice::unguarded(fn () => Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'FYK-FAC-REMFX',
        'currency' => 'XOF',
        'status' => InvoiceStatus::Draft,
        'issued_at' => now(),
        'due_at' => now()->addDays(30),
        'subtotal' => 100_000,
        'tax_amount' => 16_380,
        'total' => 108_380,
        'discount' => 8_000,
        'discount_type' => 'fixed',
        'amount_paid' => 0,
    ]));

    $html = view('pdf.invoice', ['invoice' => $invoice->load(['company', 'client', 'lines']), 'logoBase64' => null])->render();

    expect($html)
        ->toContain('Remise (montant fixe)')
        ->toContain('8 000')
        ->not->toContain('8000%');
});

test('le PDF facture n\'affiche pas de ligne remise quand elle est nulle', function () {
    ['company' => $company] = createSmeUserForPdf();
    $invoice = createInvoiceForPdf($company);

    $html = view('pdf.invoice', ['invoice' => $invoice->load(['company', 'client', 'lines']), 'logoBase64' => null])->render();

    expect($html)->not->toContain('Remise');
});
