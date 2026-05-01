<?php

use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\QuoteStatus;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Models\PME\Quote;
use App\Models\PME\QuoteLine;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function createSmeWithCompanyForQuotes(): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create([
        'type' => 'sme',
        'name' => 'Test PME SARL',
    ]);
    $company->users()->attach($user->id, ['role' => 'owner']);

    return compact('user', 'company');
}

/**
 * @param  array<string, mixed>  $overrides
 */
function makeQuote(Company $company, array $overrides = []): Quote
{
    $client = Client::factory()->create(['company_id' => $company->id]);

    return Quote::unguarded(fn () => Quote::create(array_merge([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'DEV-'.fake()->unique()->numerify('###'),
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

/**
 * Crée une facture liée à un devis.
 *
 * @param  array<string, mixed>  $overrides
 */
function makeQuoteInvoice(Quote $quote, array $overrides = []): Invoice
{
    return Invoice::unguarded(fn () => Invoice::create(array_merge([
        'company_id' => $quote->company_id,
        'client_id' => $quote->client_id,
        'quote_id' => $quote->id,
        'reference' => 'FAC-'.fake()->unique()->numerify('###'),
        'status' => InvoiceStatus::Sent->value,
        'issued_at' => now(),
        'due_at' => now()->addDays(30),
        'subtotal' => 100_000,
        'tax_amount' => 18_000,
        'total' => 118_000,
        'amount_paid' => 0,
    ], $overrides)));
}

// ─── Accès & sécurité ─────────────────────────────────────────────────────────

test('un visiteur non authentifié est redirigé vers la connexion', function () {
    $this->get(route('pme.quotes.index'))
        ->assertRedirect(route('login'));
});

test('un utilisateur SME peut accéder à la page devis', function () {
    ['user' => $user] = createSmeWithCompanyForQuotes();

    $this->actingAs($user)
        ->get(route('pme.quotes.index'))
        ->assertOk();
});

// ─── Devise (currency) ────────────────────────────────────────────────────────

test('rows() inclut le champ currency pour chaque devis', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompanyForQuotes();

    makeQuote($company, ['reference' => 'DEV-EUR', 'currency' => 'EUR']);
    makeQuote($company, ['reference' => 'DEV-XOF', 'currency' => 'XOF']);

    $rows = collect(
        Livewire::actingAs($user)->test('pages::pme.quotes.index')->get('rows')
    );

    expect($rows->firstWhere('reference', 'DEV-EUR')['currency'])->toBe('EUR');
    expect($rows->firstWhere('reference', 'DEV-XOF')['currency'])->toBe('XOF');
});

test('les montants EUR sont affichés en euros dans la liste des devis', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompanyForQuotes();

    // 814 000 centimes EUR = 8 140,00 EUR
    makeQuote($company, [
        'reference' => 'DEV-EUR',
        'currency' => 'EUR',
        'subtotal' => 814_000,
        'tax_amount' => 0,
        'total' => 814_000,
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.index')
        ->assertSeeHtml('8 140,00');
});

test('les montants XOF sont affichés en FCFA dans la liste des devis', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompanyForQuotes();

    makeQuote($company, [
        'reference' => 'DEV-XOF',
        'currency' => 'XOF',
        'subtotal' => 814_000,
        'tax_amount' => 0,
        'total' => 814_000,
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.index')
        ->assertSeeHtml('814 000');
});

test('le modal de détail affiche les montants EUR correctement', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompanyForQuotes();

    $client = Client::factory()->create(['company_id' => $company->id]);
    $quote = Quote::unguarded(fn () => Quote::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'DEV-EUR-DETAIL',
        'currency' => 'EUR',
        'status' => QuoteStatus::Draft->value,
        'issued_at' => now(),
        'valid_until' => now()->addDays(30),
        'subtotal' => 814_000,
        'tax_amount' => 0,
        'total' => 814_000,
        'discount' => 0,
    ]));

    QuoteLine::query()->create([
        'quote_id' => $quote->id,
        'description' => 'Prestation EUR',
        'quantity' => 22,
        'unit_price' => 37_000,
        'tax_rate' => 0,
        'total' => 814_000,
    ]);

    $this->actingAs($user)
        ->get(route('pme.quotes.show', $quote))
        ->assertOk()
        ->assertSeeHtml('8 140,00')
        ->assertDontSeeHtml('814 000 FCFA');
});

test('la show page affiche les montants XOF correctement', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompanyForQuotes();

    $client = Client::factory()->create(['company_id' => $company->id]);
    $quote = Quote::unguarded(fn () => Quote::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'DEV-XOF-DETAIL',
        'currency' => 'XOF',
        'status' => QuoteStatus::Draft->value,
        'issued_at' => now(),
        'valid_until' => now()->addDays(30),
        'subtotal' => 500_000,
        'tax_amount' => 90_000,
        'total' => 590_000,
        'discount' => 0,
    ]));

    $this->actingAs($user)
        ->get(route('pme.quotes.show', $quote))
        ->assertOk()
        ->assertSeeHtml('500 000')
        ->assertSeeHtml('590 000');
});

// ─── Réduction ────────────────────────────────────────────────────────────────

test('la show page devis affiche la réduction quand elle est présente', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompanyForQuotes();

    $client = Client::factory()->create(['company_id' => $company->id]);
    $quote = Quote::unguarded(fn () => Quote::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'DEV-REMISE',
        'currency' => 'XOF',
        'status' => QuoteStatus::Draft->value,
        'issued_at' => now(),
        'valid_until' => now()->addDays(30),
        'discount' => 10,
        'subtotal' => 100_000,
        'tax_amount' => 16_200,
        'total' => 106_200,
    ]));

    $this->actingAs($user)
        ->get(route('pme.quotes.show', $quote))
        ->assertOk()
        ->assertSeeHtml('Remise')
        ->assertSeeHtml('10 000');
});

test('la show page devis n\'affiche pas la réduction quand elle est nulle', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompanyForQuotes();

    $quote = makeQuote($company, ['discount' => 0]);

    $this->actingAs($user)
        ->get(route('pme.quotes.show', $quote))
        ->assertOk()
        ->assertDontSeeHtml('Remise');
});

// ─── Devis converti en facture ────────────────────────────────────────────────

test('rows expose has_invoice=true et invoice_id quand le devis est converti en facture', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompanyForQuotes();

    $quote = makeQuote($company);
    $invoice = makeQuoteInvoice($quote);

    $rows = collect(
        Livewire::actingAs($user)->test('pages::pme.quotes.index')->get('rows')
    );

    $row = $rows->firstWhere('id', $quote->id);

    expect($row['has_invoice'])->toBeTrue();
    expect($row['invoice_id'])->toBe($invoice->id);
});

test('rows expose has_invoice=false et invoice_id=null quand le devis n\'est pas converti', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompanyForQuotes();

    $quote = makeQuote($company);

    $rows = collect(
        Livewire::actingAs($user)->test('pages::pme.quotes.index')->get('rows')
    );

    $row = $rows->firstWhere('id', $quote->id);

    expect($row['has_invoice'])->toBeFalse();
    expect($row['invoice_id'])->toBeNull();
});

test('viewInvoice() positionne selectedInvoiceId avec l\'id de la facture', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompanyForQuotes();

    $quote = makeQuote($company);
    $invoice = makeQuoteInvoice($quote);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.index')
        ->call('viewInvoice', $invoice->id)
        ->assertSet('selectedInvoiceId', $invoice->id);
});

test('closeInvoice() remet selectedInvoiceId à null', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompanyForQuotes();

    $quote = makeQuote($company);
    $invoice = makeQuoteInvoice($quote);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.index')
        ->call('viewInvoice', $invoice->id)
        ->call('closeInvoice')
        ->assertSet('selectedInvoiceId', null);
});

test('la modale de facture est rendue après viewInvoice()', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompanyForQuotes();

    $quote = makeQuote($company);
    $invoice = makeQuoteInvoice($quote, ['reference' => 'FAC-MODAL-001']);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.index')
        ->call('viewInvoice', $invoice->id)
        ->assertSee('FAC-MODAL-001');
});

test('les options Voir la facture et PDF apparaissent dans le rendu quand le devis est facturé', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompanyForQuotes();

    $quote = makeQuote($company);
    $invoice = makeQuoteInvoice($quote);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.index')
        ->assertSeeHtml('Voir la facture')
        ->assertSeeHtml(route('pme.invoices.pdf', $invoice));
});

test('les options Voir la facture et PDF n\'apparaissent pas quand le devis n\'est pas facturé', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompanyForQuotes();

    makeQuote($company);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.index')
        ->assertDontSeeHtml('Voir la facture');
});

// ─── Voir le client — devis ───────────────────────────────────────────────────

test('rows() inclut client_id pour chaque devis', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompanyForQuotes();
    $quote = makeQuote($company);

    $row = collect(
        Livewire::actingAs($user)->test('pages::pme.quotes.index')->get('rows')
    )->firstWhere('reference', $quote->reference);

    expect($row['client_id'])->toBe($quote->client_id);
});

test('"Voir le client" est affiché dans le dropdown quand le devis a un client', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompanyForQuotes();
    $quote = makeQuote($company);

    $clientUrl = route('pme.clients.show', $quote->client_id);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.index')
        ->assertSeeHtml($clientUrl)
        ->assertSee('Voir le client');
});
