<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Auth\Models\Company;
use Modules\PME\Clients\Models\Client;
use Modules\PME\Invoicing\Enums\InvoiceStatus;
use Modules\PME\Invoicing\Enums\QuoteStatus;
use Modules\PME\Invoicing\Models\Invoice;
use Modules\PME\Invoicing\Models\Quote;
use Modules\Shared\Models\User;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée un utilisateur SME avec sa PME associée.
 *
 * @return array{user: User, company: Company}
 */
function createSmeWithCompany(?string $companyName = null): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create([
        'type' => 'sme',
        'name' => $companyName ?? 'Test PME SARL',
    ]);
    $company->users()->attach($user->id, ['role' => 'owner']);

    return compact('user', 'company');
}

/**
 * Crée une facture pour une PME.
 *
 * @param  array<string, mixed>  $overrides
 */
function makeInvoice(Company $company, array $overrides = []): Invoice
{
    $client = Client::factory()->create(['company_id' => $company->id]);

    return Invoice::unguarded(fn () => Invoice::create(array_merge([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'FAC-'.fake()->unique()->numerify('###'),
        'status' => InvoiceStatus::Paid->value,
        'issued_at' => now(),
        'due_at' => now()->addDays(30),
        'subtotal' => 100_000,
        'tax_amount' => 18_000,
        'total' => 118_000,
        'amount_paid' => 118_000,
    ], $overrides)));
}

/**
 * Crée un devis pour une PME.
 *
 * @param  array<string, mixed>  $overrides
 */
function makeQuote(Company $company, array $overrides = []): Quote
{
    $client = Client::factory()->create(['company_id' => $company->id]);

    return Quote::unguarded(fn () => Quote::create(array_merge([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'DEV-'.fake()->unique()->numerify('###'),
        'status' => QuoteStatus::Sent->value,
        'issued_at' => now(),
        'valid_until' => now()->addDays(30),
        'subtotal' => 100_000,
        'tax_amount' => 18_000,
        'total' => 118_000,
    ], $overrides)));
}

// ─── Accès & sécurité ─────────────────────────────────────────────────────────

test('un visiteur non authentifié est redirigé vers la connexion', function () {
    $this->get(route('pme.invoices.index'))
        ->assertRedirect(route('login'));
});

test('un utilisateur SME peut accéder à la page factures', function () {
    ['user' => $user] = createSmeWithCompany();

    $this->actingAs($user)
        ->get(route('pme.invoices.index'))
        ->assertOk();
});

test('un utilisateur cabinet comptable ne peut pas accéder à la page factures PME', function () {
    $user = User::factory()->accountantFirm()->create();

    $this->actingAs($user)
        ->get(route('pme.invoices.index'))
        ->assertForbidden();
});

test('la page se rend sans erreur pour un SME sans PME associée', function () {
    $user = User::factory()->create(['profile_type' => 'sme']);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->assertOk();
});

// ─── KPIs (mount) ─────────────────────────────────────────────────────────────

test('invoiceCount reflète les factures émises ce mois', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, ['issued_at' => now(), 'status' => InvoiceStatus::Sent->value, 'amount_paid' => 0]);
    makeInvoice($company, ['issued_at' => now(), 'status' => InvoiceStatus::Paid->value]);
    makeInvoice($company, ['issued_at' => now()->subMonths(2)]); // mois précédent → non compté

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->assertSet('invoiceCount', 2);
});

test('invoiceCount exclut les brouillons et les annulées', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, ['issued_at' => now(), 'status' => InvoiceStatus::Draft->value, 'amount_paid' => 0]);
    makeInvoice($company, ['issued_at' => now(), 'status' => InvoiceStatus::Cancelled->value, 'amount_paid' => 0]);
    makeInvoice($company, ['issued_at' => now(), 'status' => InvoiceStatus::Sent->value, 'amount_paid' => 0]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->assertSet('invoiceCount', 1);
});

test('pendingQuoteCount compte uniquement les devis envoyés sans réponse', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeQuote($company, ['status' => QuoteStatus::Sent->value]);
    makeQuote($company, ['status' => QuoteStatus::Sent->value]);
    makeQuote($company, ['status' => QuoteStatus::Accepted->value]); // non compté
    makeQuote($company, ['status' => QuoteStatus::Declined->value]); // non compté

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->assertSet('pendingQuoteCount', 2);
});

test('invoicedAmount additionne les subtotals HT des factures de ce mois', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, ['issued_at' => now(), 'subtotal' => 200_000, 'total' => 236_000]);
    makeInvoice($company, ['issued_at' => now(), 'subtotal' => 300_000, 'total' => 354_000]);
    makeInvoice($company, ['issued_at' => now()->subMonths(2), 'subtotal' => 500_000]); // exclu

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->assertSet('invoicedAmount', 500_000);
});

test('actionRequiredCount additionne les factures overdue et sent', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, ['status' => InvoiceStatus::Overdue->value, 'due_at' => now()->subDays(40), 'amount_paid' => 0]);
    makeInvoice($company, ['status' => InvoiceStatus::Overdue->value, 'due_at' => now()->subDays(10), 'amount_paid' => 0]);
    makeInvoice($company, ['status' => InvoiceStatus::Sent->value, 'amount_paid' => 0]);
    makeInvoice($company, ['status' => InvoiceStatus::Paid->value]); // non compté

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->assertSet('actionRequiredCount', 3);
});

// ─── rows() — données de base ─────────────────────────────────────────────────

test('rows() retourne les factures de la bonne PME seulement', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();
    $otherCompany = Company::factory()->create(['type' => 'sme']);

    makeInvoice($company, ['reference' => 'FAC-MINE']);
    makeInvoice($otherCompany, ['reference' => 'FAC-OTHER']);

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    $rows = $component->get('rows');

    expect(collect($rows)->pluck('reference'))->toContain('FAC-MINE');
    expect(collect($rows)->pluck('reference'))->not->toContain('FAC-OTHER');
});

test('rows() retourne également les devis de la PME', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, ['reference' => 'FAC-001']);
    makeQuote($company, ['reference' => 'DEV-001']);

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    $rows = $component->get('rows');

    $types = collect($rows)->pluck('type')->unique()->values()->sort()->values()->toArray();
    expect($types)->toEqual(['invoice', 'quote']);
});

test('rows() exclut les factures annulées', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, ['reference' => 'FAC-VIS', 'status' => InvoiceStatus::Paid->value]);
    makeInvoice($company, ['reference' => 'FAC-CANCEL', 'status' => InvoiceStatus::Cancelled->value, 'amount_paid' => 0]);

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    $rows = $component->get('rows');

    expect(collect($rows)->pluck('reference'))->toContain('FAC-VIS');
    expect(collect($rows)->pluck('reference'))->not->toContain('FAC-CANCEL');
});

test('rows() est trié par issued_at décroissant', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, ['reference' => 'FAC-OLD', 'issued_at' => now()->subDays(10)]);
    makeInvoice($company, ['reference' => 'FAC-NEW', 'issued_at' => now()]);
    makeInvoice($company, ['reference' => 'FAC-MID', 'issued_at' => now()->subDays(5)]);

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    $refs = collect($component->get('rows'))->pluck('reference')->values()->toArray();

    expect($refs[0])->toBe('FAC-NEW');
    expect($refs[1])->toBe('FAC-MID');
    expect($refs[2])->toBe('FAC-OLD');
});

test('rows() mappe correctement le nom du client', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();
    $client = Client::factory()->create(['company_id' => $company->id, 'name' => 'Sonatel SA']);

    Invoice::unguarded(fn () => Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'FAC-CLI',
        'status' => InvoiceStatus::Paid->value,
        'issued_at' => now(),
        'due_at' => now()->addDays(30),
        'subtotal' => 100_000,
        'tax_amount' => 18_000,
        'total' => 118_000,
        'amount_paid' => 118_000,
    ]));

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    $row = collect($component->get('rows'))->firstWhere('reference', 'FAC-CLI');

    expect($row['client_name'])->toBe('Sonatel SA');
});

test('rows() retourne un tableau vide si la PME n\'a aucun document', function () {
    ['user' => $user] = createSmeWithCompany();

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');

    expect($component->get('rows'))->toBeArray()->toBeEmpty();
});

// ─── Filtre type ──────────────────────────────────────────────────────────────

test('setTypeFilter(invoice) ne retourne que les factures', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company);
    makeInvoice($company);
    makeQuote($company);

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    $component->call('setTypeFilter', 'invoice');

    $types = collect($component->get('rows'))->pluck('type')->unique()->toArray();
    expect($types)->toEqual(['invoice']);
    expect($component->get('rows'))->toHaveCount(2);
});

test('setTypeFilter(quote) ne retourne que les devis', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company);
    makeQuote($company);
    makeQuote($company);

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    $component->call('setTypeFilter', 'quote');

    $types = collect($component->get('rows'))->pluck('type')->unique()->toArray();
    expect($types)->toEqual(['quote']);
    expect($component->get('rows'))->toHaveCount(2);
});

test('setTypeFilter(all) retourne factures et devis', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company);
    makeQuote($company);

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    $component->call('setTypeFilter', 'all');

    expect($component->get('rows'))->toHaveCount(2);
});

test('setTypeFilter réinitialise le filtre statut à all', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company);

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    $component->call('setStatusFilter', 'paid');
    $component->call('setTypeFilter', 'invoice');

    expect($component->get('statusFilter'))->toBe('all');
});

// ─── Filtre statut ────────────────────────────────────────────────────────────

test('setStatusFilter(paid) ne retourne que les factures payées', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, ['status' => InvoiceStatus::Paid->value]);
    makeInvoice($company, ['status' => InvoiceStatus::Sent->value, 'amount_paid' => 0]);
    makeInvoice($company, ['status' => InvoiceStatus::Overdue->value, 'due_at' => now()->subDays(10), 'amount_paid' => 0]);

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    $component->call('setStatusFilter', 'paid');

    expect($component->get('rows'))->toHaveCount(1);
    expect($component->get('rows')[0]['status_value'])->toBe('paid');
});

test('setStatusFilter(overdue) ne retourne que les factures en retard', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, ['status' => InvoiceStatus::Paid->value]);
    makeInvoice($company, ['status' => InvoiceStatus::Overdue->value, 'due_at' => now()->subDays(40), 'amount_paid' => 0]);
    makeInvoice($company, ['status' => InvoiceStatus::Overdue->value, 'due_at' => now()->subDays(70), 'amount_paid' => 0]);

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    $component->call('setStatusFilter', 'overdue');

    expect($component->get('rows'))->toHaveCount(2);
});

test('setStatusFilter(accepted) ne retourne que les devis acceptés', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeQuote($company, ['status' => QuoteStatus::Accepted->value]);
    makeQuote($company, ['status' => QuoteStatus::Sent->value]);
    makeQuote($company, ['status' => QuoteStatus::Declined->value]);

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    $component->call('setStatusFilter', 'accepted');

    expect($component->get('rows'))->toHaveCount(1);
    expect($component->get('rows')[0]['status_value'])->toBe('accepted');
});

test('setStatusFilter(all) retourne tous les documents', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, ['status' => InvoiceStatus::Paid->value]);
    makeInvoice($company, ['status' => InvoiceStatus::Sent->value, 'amount_paid' => 0]);
    makeQuote($company, ['status' => QuoteStatus::Sent->value]);

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    $component->call('setStatusFilter', 'all');

    expect($component->get('rows'))->toHaveCount(3);
});

// ─── Filtre combiné type + statut ─────────────────────────────────────────────

test('le filtre type invoice + statut sent ne retourne que les factures envoyées', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, ['status' => InvoiceStatus::Sent->value, 'amount_paid' => 0]);
    makeInvoice($company, ['status' => InvoiceStatus::Paid->value]);
    makeQuote($company, ['status' => QuoteStatus::Sent->value]); // devis sent → exclu par filtre type

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    $component->call('setTypeFilter', 'invoice');
    $component->call('setStatusFilter', 'sent');

    $rows = $component->get('rows');
    expect($rows)->toHaveCount(1);
    expect($rows[0]['type'])->toBe('invoice');
    expect($rows[0]['status_value'])->toBe('sent');
});

// ─── Recherche ────────────────────────────────────────────────────────────────

test('la recherche filtre par référence (correspondance partielle)', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, ['reference' => 'FAC-094']);
    makeInvoice($company, ['reference' => 'FAC-095']);
    makeInvoice($company, ['reference' => 'DEV-001']);

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    $component->set('search', 'FAC-09');

    $refs = collect($component->get('rows'))->pluck('reference')->toArray();
    expect($refs)->toContain('FAC-094');
    expect($refs)->toContain('FAC-095');
    expect($refs)->not->toContain('DEV-001');
});

test('la recherche filtre par nom de client (correspondance partielle)', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    $sonatel = Client::factory()->create(['company_id' => $company->id, 'name' => 'Sonatel SA']);
    $pharma = Client::factory()->create(['company_id' => $company->id, 'name' => 'Dakar Pharma']);

    Invoice::unguarded(fn () => Invoice::create([
        'company_id' => $company->id, 'client_id' => $sonatel->id,
        'reference' => 'FAC-SN', 'status' => InvoiceStatus::Paid->value,
        'issued_at' => now(), 'due_at' => now()->addDays(30),
        'subtotal' => 100_000, 'tax_amount' => 18_000, 'total' => 118_000, 'amount_paid' => 118_000,
    ]));
    Invoice::unguarded(fn () => Invoice::create([
        'company_id' => $company->id, 'client_id' => $pharma->id,
        'reference' => 'FAC-PH', 'status' => InvoiceStatus::Paid->value,
        'issued_at' => now(), 'due_at' => now()->addDays(30),
        'subtotal' => 100_000, 'tax_amount' => 18_000, 'total' => 118_000, 'amount_paid' => 118_000,
    ]));

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    $component->set('search', 'sonatel');

    $refs = collect($component->get('rows'))->pluck('reference')->toArray();
    expect($refs)->toContain('FAC-SN');
    expect($refs)->not->toContain('FAC-PH');
});

test('la recherche est insensible à la casse', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, ['reference' => 'FAC-TEST']);

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    $component->set('search', 'fac-test');

    expect($component->get('rows'))->toHaveCount(1);
});

test('une recherche sans résultat retourne un tableau vide', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, ['reference' => 'FAC-001']);

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    $component->set('search', 'XYZ-INEXISTANT');

    expect($component->get('rows'))->toBeEmpty();
});

// ─── Filtre période ───────────────────────────────────────────────────────────

test('le filtre période retourne uniquement les documents du mois sélectionné', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, ['reference' => 'FAC-MARS', 'issued_at' => now()->setMonth(3)->setYear(2026)]);
    makeInvoice($company, ['reference' => 'FAC-FEV', 'issued_at' => now()->setMonth(2)->setYear(2026)]);
    makeQuote($company, ['reference' => 'DEV-MARS', 'issued_at' => now()->setMonth(3)->setYear(2026)]);

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    $component->set('period', '2026-03');

    $refs = collect($component->get('rows'))->pluck('reference')->toArray();
    expect($refs)->toContain('FAC-MARS');
    expect($refs)->toContain('DEV-MARS');
    expect($refs)->not->toContain('FAC-FEV');
});

test('un filtre période vide retourne tous les documents', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, ['issued_at' => now()->subMonths(3)]);
    makeInvoice($company, ['issued_at' => now()->subMonths(1)]);
    makeInvoice($company, ['issued_at' => now()]);

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    $component->set('period', '');

    expect($component->get('rows'))->toHaveCount(3);
});

// ─── typeCounts ───────────────────────────────────────────────────────────────

test('typeCounts reflète le nombre de factures et devis', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company);
    makeInvoice($company);
    makeQuote($company);

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    $counts = $component->get('typeCounts');

    expect($counts['all'])->toBe(3);
    expect($counts['invoice'])->toBe(2);
    expect($counts['quote'])->toBe(1);
});

test('typeCounts exclut les factures annulées du total', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, ['status' => InvoiceStatus::Paid->value]);
    makeInvoice($company, ['status' => InvoiceStatus::Cancelled->value, 'amount_paid' => 0]);

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    $counts = $component->get('typeCounts');

    expect($counts['invoice'])->toBe(1);
    expect($counts['all'])->toBe(1);
});

// ─── statusCounts ─────────────────────────────────────────────────────────────

test('statusCounts répartit les documents par statut', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, ['status' => InvoiceStatus::Paid->value]);
    makeInvoice($company, ['status' => InvoiceStatus::Paid->value]);
    makeInvoice($company, ['status' => InvoiceStatus::Sent->value, 'amount_paid' => 0]);
    makeInvoice($company, ['status' => InvoiceStatus::Overdue->value, 'due_at' => now()->subDays(40), 'amount_paid' => 0]);

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    $counts = $component->get('statusCounts');

    expect($counts['paid'])->toBe(2);
    expect($counts['sent'])->toBe(1);
    expect($counts['overdue'])->toBe(1);
    expect($counts['all'])->toBe(4);
});

test('statusCounts tient compte du filtre type actif', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, ['status' => InvoiceStatus::Sent->value, 'amount_paid' => 0]);
    makeQuote($company, ['status' => QuoteStatus::Sent->value]);

    // En filtrant sur invoice, statusCounts ne compte que les factures
    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    $component->call('setTypeFilter', 'invoice');
    $counts = $component->get('statusCounts');

    expect($counts['all'])->toBe(1);
    expect($counts['sent'] ?? 0)->toBe(1);
});

// ─── availablePeriods ─────────────────────────────────────────────────────────

test('availablePeriods retourne les 6 derniers mois', function () {
    ['user' => $user] = createSmeWithCompany();

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    $periods = $component->get('availablePeriods');

    expect($periods)->toHaveCount(6);
    expect(array_keys($periods))->toContain(now()->format('Y-m'));
    expect(array_keys($periods))->toContain(now()->subMonths(5)->format('Y-m'));
});

// ─── markAsPaid() ─────────────────────────────────────────────────────────────

test('markAsPaid() met à jour le statut de la facture en Payée', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();
    $invoice = makeInvoice($company, [
        'status' => InvoiceStatus::Sent->value,
        'total' => 118_000,
        'amount_paid' => 0,
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->call('markAsPaid', $invoice->id);

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::Paid);
    expect($invoice->amount_paid)->toBe(118_000);
    expect($invoice->paid_at)->not->toBeNull();
});

test('markAsPaid() met à jour actionRequiredCount en conséquence', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();
    $invoice = makeInvoice($company, ['status' => InvoiceStatus::Sent->value, 'amount_paid' => 0]);

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    expect($component->get('actionRequiredCount'))->toBe(1);

    $component->call('markAsPaid', $invoice->id);
    expect($component->get('actionRequiredCount'))->toBe(0);
});

test('markAsPaid() n\'affecte pas les factures d\'une autre PME', function () {
    ['user' => $user] = createSmeWithCompany();
    $otherCompany = Company::factory()->create(['type' => 'sme']);
    $otherInvoice = makeInvoice($otherCompany, [
        'status' => InvoiceStatus::Sent->value,
        'amount_paid' => 0,
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->call('markAsPaid', $otherInvoice->id);

    // Aucune modification : l'exception est levée avant le save
    $otherInvoice->refresh();
    expect($otherInvoice->status)->toBe(InvoiceStatus::Sent);
})->throws(ModelNotFoundException::class);

// ─── Affichage Blade ──────────────────────────────────────────────────────────

test('la page affiche le titre Factures & Devis', function () {
    ['user' => $user] = createSmeWithCompany();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->assertSee('Factures & Devis');
});

test('la page affiche les KPI labels', function () {
    ['user' => $user] = createSmeWithCompany();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->assertSee('Factures émises')
        ->assertSee('Devis en attente')
        ->assertSee('Montant facturé')
        ->assertSee('En retard ou en attente');
});

test('la page affiche les boutons CTA principaux', function () {
    ['user' => $user] = createSmeWithCompany();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->assertSee('Nouvelle facture')
        ->assertSee('Nouveau devis');
});

test('la page affiche les onglets de filtre type', function () {
    ['user' => $user] = createSmeWithCompany();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->assertSee('Factures')
        ->assertSee('Devis');
});

test('une facture s\'affiche avec le badge Facture et son statut', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, ['reference' => 'FAC-XYZ', 'status' => InvoiceStatus::Sent->value, 'amount_paid' => 0]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->assertSee('FAC-XYZ')
        ->assertSee('Facture')
        ->assertSee('Envoyée');
});

test('un devis s\'affiche avec le badge Devis et son statut', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeQuote($company, ['reference' => 'DEV-XYZ', 'status' => QuoteStatus::Accepted->value]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->assertSee('DEV-XYZ')
        ->assertSee('Devis')
        ->assertSee('Accepté');
});

test('une facture en retard affiche J+ dans l\'échéance', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(15),
        'amount_paid' => 0,
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->assertSee('J+15');
});

test('l\'état vide s\'affiche quand aucun document n\'existe', function () {
    ['user' => $user] = createSmeWithCompany();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->assertSee('Aucun document pour le moment')
        ->assertSee('Créer une facture');
});

test('le message "aucun résultat" s\'affiche quand le filtre ne correspond à rien', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, ['status' => InvoiceStatus::Paid->value]);

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    $component->set('search', 'AUCUN-RESULTAT-POSSIBLE');

    $component->assertSee('Aucun document ne correspond');
});

test('le bouton Réinitialiser les filtres est affiché dans l\'état vide filtré', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, ['status' => InvoiceStatus::Paid->value]);

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    $component->set('search', 'RIEN');

    $component->assertSee('Réinitialiser les filtres');
});
