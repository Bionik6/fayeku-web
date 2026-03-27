<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\Company;
use Modules\PME\Clients\Models\Client;
use Modules\PME\Invoicing\Models\Invoice;
use Modules\PME\Invoicing\Models\InvoiceLine;
use Modules\PME\Invoicing\Services\PdfService;
use Modules\Shared\Models\User;

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

test('un visiteur non authentifié est redirigé vers la connexion depuis la route PDF', function () {
    ['company' => $company] = createSmeUserForPdf();
    $invoice = createInvoiceForPdf($company);

    $this->get(route('pme.invoices.pdf', $invoice))
        ->assertRedirect(route('login'));
});

test('un utilisateur ne peut pas accéder au PDF d\'une facture d\'une autre entreprise', function () {
    ['user' => $user] = createSmeUserForPdf();
    ['company' => $otherCompany] = createSmeUserForPdf();
    $invoice = createInvoiceForPdf($otherCompany);

    $this->actingAs($user)
        ->get(route('pme.invoices.pdf', $invoice))
        ->assertForbidden();
});

test('un utilisateur autorisé peut voir le PDF de sa facture', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForPdf();
    $invoice = createInvoiceForPdf($company);

    $response = $this->actingAs($user)
        ->get(route('pme.invoices.pdf', $invoice));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
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
