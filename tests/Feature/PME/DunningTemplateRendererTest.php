<?php

use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\DunningTemplate;
use App\Models\PME\Invoice;
use App\Services\PME\DunningTemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeRenderInvoice(string $currency, int $total): Invoice
{
    $company = Company::factory()->create(['type' => 'sme', 'name' => 'Sow BTP']);
    $client = Client::factory()->create([
        'company_id' => $company->id,
        'name' => 'Dakar Pharma',
    ]);

    return Invoice::unguarded(fn () => Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'FAC-RENDER-001',
        'currency' => $currency,
        'status' => 'sent',
        'issued_at' => now()->subDays(15),
        'due_at' => now()->subDays(3),
        'subtotal' => $total,
        'tax_amount' => 0,
        'total' => $total,
        'amount_paid' => 0,
    ]))->load('client', 'company');
}

it('remplace les placeholders de base', function () {
    $invoice = makeRenderInvoice('XOF', 300_000);
    $template = new DunningTemplate(['day_offset' => 3, 'body' => '{contact_name} — {invoice_reference}', 'active' => true]);

    $body = app(DunningTemplateRenderer::class)->render($template, $invoice);

    expect($body)->toBe('Dakar Pharma — FAC-RENDER-001');
});

it('utilise le label FCFA pour une facture en XOF', function () {
    $invoice = makeRenderInvoice('XOF', 300_000);
    $template = new DunningTemplate(['day_offset' => 3, 'body' => 'Montant {amount} {currency}', 'active' => true]);

    $body = app(DunningTemplateRenderer::class)->render($template, $invoice);

    expect($body)
        ->toContain('FCFA')
        ->toContain('300 000');
});

it('utilise le label EUR et le bon séparateur décimal pour une facture en EUR', function () {
    // EUR stores in cents: 885,00 EUR = 88500.
    $invoice = makeRenderInvoice('EUR', 88_500);
    $template = new DunningTemplate(['day_offset' => 3, 'body' => 'Montant {amount} {currency}', 'active' => true]);

    $body = app(DunningTemplateRenderer::class)->render($template, $invoice);

    expect($body)
        ->toContain('EUR')
        ->toContain('885,00');
});

it('utilise le label USD pour une facture en USD', function () {
    $invoice = makeRenderInvoice('USD', 12_345);
    $template = new DunningTemplate(['day_offset' => 3, 'body' => 'Montant {amount} {currency}', 'active' => true]);

    $body = app(DunningTemplateRenderer::class)->render($template, $invoice);

    expect($body)
        ->toContain('USD')
        ->toContain('123.45');
});

it('injecte la signature depuis la company', function () {
    $invoice = makeRenderInvoice('XOF', 300_000);
    $template = new DunningTemplate(['day_offset' => 3, 'body' => '-- {signature}', 'active' => true]);

    $body = app(DunningTemplateRenderer::class)->render($template, $invoice);

    expect($body)->toBe('-- Sow BTP');
});

it('formate la date d\'échéance en français', function () {
    $invoice = makeRenderInvoice('XOF', 300_000);
    $invoice->due_at = '2026-05-12';
    $invoice->save();
    $invoice->refresh();
    $template = new DunningTemplate(['day_offset' => 3, 'body' => 'Échéance : {due_date}', 'active' => true]);

    $body = app(DunningTemplateRenderer::class)->render($template, $invoice);

    expect($body)->toContain('12 mai 2026');
});
