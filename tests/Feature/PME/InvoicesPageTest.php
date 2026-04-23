<?php

use App\Enums\PME\InvoiceStatus;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Models\Shared\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(now()->startOfWeek()->setHour(10));
});

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

test('un utilisateur cabinet comptable est redirigé vers son dashboard', function () {
    $user = User::factory()->accountantFirm()->create();

    $this->actingAs($user)
        ->get(route('pme.invoices.index'))
        ->assertRedirect(route('dashboard'));
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

test('unpaidCount compte les factures Sent, Overdue et PartiallyPaid', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, ['status' => InvoiceStatus::Sent->value, 'amount_paid' => 0]);
    makeInvoice($company, ['status' => InvoiceStatus::Overdue->value, 'due_at' => now()->subDays(10), 'amount_paid' => 0]);
    makeInvoice($company, ['status' => InvoiceStatus::PartiallyPaid->value, 'amount_paid' => 50_000]);
    makeInvoice($company, ['status' => InvoiceStatus::Paid->value]); // non compté
    makeInvoice($company, ['status' => InvoiceStatus::Draft->value, 'amount_paid' => 0]); // non compté

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->assertSet('unpaidCount', 3);
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

test('rows() trie par created_at décroissant quand issued_at est identique', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    $sameDate = now()->startOfDay();

    $first = makeInvoice($company, ['reference' => 'FAC-FIRST', 'issued_at' => $sameDate]);
    $second = makeInvoice($company, ['reference' => 'FAC-SECOND', 'issued_at' => $sameDate]);
    $third = makeInvoice($company, ['reference' => 'FAC-THIRD', 'issued_at' => $sameDate]);

    // Forcer des created_at distincts pour garantir l'ordre
    $first->timestamps = false;
    $first->forceFill(['created_at' => now()->subMinutes(10)])->save();
    $second->forceFill(['created_at' => now()->subMinutes(5)])->save();
    $third->forceFill(['created_at' => now()])->save();

    $refs = collect(
        Livewire::actingAs($user)->test('pages::pme.invoices.index')->get('rows')
    )->pluck('reference')->values()->toArray();

    // La plus récemment créée doit apparaître en premier
    expect($refs[0])->toBe('FAC-THIRD');
    expect($refs[1])->toBe('FAC-SECOND');
    expect($refs[2])->toBe('FAC-FIRST');
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

test('rows() retourne un tableau vide si la PME n\'a aucune facture', function () {
    ['user' => $user] = createSmeWithCompany();

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');

    expect($component->get('rows'))->toBeArray()->toBeEmpty();
});

test('la page ne recharge les factures qu\'une seule fois par rendu', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, ['reference' => 'FAC-ONLY']);

    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->assertSee('Factures');

    $queries = collect(DB::getQueryLog())
        ->pluck('query')
        ->map(fn (string $query) => mb_strtolower($query));

    $invoiceSelects = $queries->filter(fn (string $query) => str_starts_with($query, 'select * from "invoices"')
        && str_contains($query, '"status" not in'));

    expect($invoiceSelects)->toHaveCount(1);
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

test('setStatusFilter(all) retourne toutes les factures', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, ['status' => InvoiceStatus::Paid->value]);
    makeInvoice($company, ['status' => InvoiceStatus::Sent->value, 'amount_paid' => 0]);
    makeInvoice($company, ['status' => InvoiceStatus::Draft->value, 'amount_paid' => 0]);

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    $component->call('setStatusFilter', 'all');

    expect($component->get('rows'))->toHaveCount(3);
});

// ─── Recherche ────────────────────────────────────────────────────────────────

test('la recherche filtre par référence (correspondance partielle)', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, ['reference' => 'FAC-094']);
    makeInvoice($company, ['reference' => 'FAC-095']);
    makeInvoice($company, ['reference' => 'FAC-200']);

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    $component->set('search', 'FAC-09');

    $refs = collect($component->get('rows'))->pluck('reference')->toArray();
    expect($refs)->toContain('FAC-094');
    expect($refs)->toContain('FAC-095');
    expect($refs)->not->toContain('FAC-200');
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

test('le filtre période retourne uniquement les factures du mois sélectionné', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, ['reference' => 'FAC-MARS', 'issued_at' => Carbon::create(2026, 3, 10)]);
    makeInvoice($company, ['reference' => 'FAC-FEV', 'issued_at' => Carbon::create(2026, 2, 15)]);
    makeInvoice($company, ['reference' => 'FAC-MARS2', 'issued_at' => Carbon::create(2026, 3, 20)]);

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    $component->set('period', '2026-03');

    $refs = collect($component->get('rows'))->pluck('reference')->toArray();
    expect($refs)->toContain('FAC-MARS');
    expect($refs)->toContain('FAC-MARS2');
    expect($refs)->not->toContain('FAC-FEV');
});

test('un filtre période vide retourne toutes les factures', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, ['issued_at' => now()->subMonths(3)]);
    makeInvoice($company, ['issued_at' => now()->subMonths(1)]);
    makeInvoice($company, ['issued_at' => now()]);

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    $component->set('period', '');

    expect($component->get('rows'))->toHaveCount(3);
});

test('une période spécifique conserve le filtre statut actif', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    $selectedPeriod = now()->format('Y-m');

    $currentStart = now()->copy()->startOfMonth();
    $previousMonth = now()->copy()->startOfMonth()->subDays(15);

    makeInvoice($company, [
        'reference' => 'FAC-CURRENT',
        'issued_at' => $currentStart->copy()->addDays(2),
        'status' => InvoiceStatus::Paid->value,
    ]);
    makeInvoice($company, [
        'reference' => 'FAC-CURRENT-SENT',
        'issued_at' => $currentStart->copy()->addDays(3),
        'status' => InvoiceStatus::Sent->value,
        'amount_paid' => 0,
    ]);
    makeInvoice($company, [
        'reference' => 'FAC-OLD-PAID',
        'issued_at' => $previousMonth,
        'status' => InvoiceStatus::Paid->value,
    ]);

    $component = Livewire::actingAs($user)
        ->withQueryParams([
            'periode' => $selectedPeriod,
            'statut' => 'paid',
        ])
        ->test('pages::pme.invoices.index');

    expect($component->get('period'))->toBe($selectedPeriod);
    expect($component->get('statusFilter'))->toBe('paid');
    expect(collect($component->get('rows'))->pluck('reference')->all())
        ->toEqual(['FAC-CURRENT']);
    expect($component->get('statusCounts'))->toMatchArray([
        'all' => 2,
        'paid' => 1,
        'sent' => 1,
    ]);
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

// ─── availablePeriods ─────────────────────────────────────────────────────────

test('availablePeriods retourne les 6 derniers mois', function () {
    ['user' => $user] = createSmeWithCompany();

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    $periods = $component->get('availablePeriods');

    expect($periods)->toHaveCount(count(
        collect(range(0, 5))->map(fn ($i) => now()->subMonths($i)->format('Y-m'))->unique()->all()
    ));
    expect(array_keys($periods))->toContain(now()->format('Y-m'));
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

test('la page affiche le titre Factures', function () {
    ['user' => $user] = createSmeWithCompany();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->assertSee('Factures');
});

test('la page affiche les KPI labels', function () {
    ['user' => $user] = createSmeWithCompany();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->assertSee('Factures émises')
        ->assertSee('Factures impayées')
        ->assertSee('Montant facturé')
        ->assertSee('En retard ou en attente');
});

test('la page factures utilise FCFA hors tableau et conserve F dans le tableau', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, [
        'reference' => 'FAC-FORMAT',
        'subtotal' => 321_000,
        'tax_amount' => 57_780,
        'total' => 378_780,
        'amount_paid' => 378_780,
        'issued_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->assertSee('321 000 FCFA')
        ->assertSee('378 780F');
});

test('la page affiche le bouton CTA principal', function () {
    ['user' => $user] = createSmeWithCompany();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->assertSee('Nouvelle facture');
});

test('une facture s\'affiche avec sa référence et son statut', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, ['reference' => 'FAC-XYZ', 'status' => InvoiceStatus::Sent->value, 'amount_paid' => 0]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->assertSee('FAC-XYZ')
        ->assertSee('Envoyée');
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

test('l\'état vide s\'affiche quand aucune facture n\'existe', function () {
    ['user' => $user] = createSmeWithCompany();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->assertSee('Aucune facture pour le moment')
        ->assertSee('Créer une facture');
});

test('le message "aucun résultat" s\'affiche quand le filtre ne correspond à rien', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, ['status' => InvoiceStatus::Paid->value]);

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    $component->set('search', 'AUCUN-RESULTAT-POSSIBLE');

    $component->assertSee('Aucune facture ne correspond');
});

test('le bouton Réinitialiser les filtres est affiché dans l\'état vide filtré', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, ['status' => InvoiceStatus::Paid->value]);

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');
    $component->set('search', 'RIEN');

    $component->assertSee('Réinitialiser les filtres');
});

test('les filtres statut restent visibles quand une période spécifique est choisie', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, [
        'reference' => 'FAC-CURRENT',
        'issued_at' => now()->copy()->startOfMonth()->addDays(2),
        'status' => InvoiceStatus::Paid->value,
    ]);

    Livewire::actingAs($user)
        ->withQueryParams(['periode' => now()->format('Y-m')])
        ->test('pages::pme.invoices.index')
        ->assertSeeHtml('wire:click="setStatusFilter(\'paid\')"');
});

test('une ligne facture pointe vers la page détail via wire:navigate', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    $invoice = makeInvoice($company, [
        'reference' => 'FAC-NAV',
        'status' => InvoiceStatus::Sent->value,
        'amount_paid' => 0,
    ]);

    $html = (string) Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->assertOk()
        ->html();

    expect($html)->toContain(route('pme.invoices.show', $invoice->id));
});

test('le menu Actions affiche un lien vers la page d\'édition pour les factures modifiables', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    $invoice = makeInvoice($company, [
        'reference' => 'FAC-EDIT-LINK',
        'status' => InvoiceStatus::Draft->value,
    ]);

    $editUrl = route('pme.invoices.edit', $invoice->id);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->assertSeeHtml($editUrl);
});

test('deleteInvoice supprime une facture de la PME courante', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    $invoice = makeInvoice($company, [
        'reference' => 'FAC-DELETE',
        'status' => InvoiceStatus::Sent->value,
        'amount_paid' => 0,
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->call('deleteInvoice', $invoice->id)
        ->assertDispatched('toast', type: 'success', title: 'La facture a été supprimée.');

    expect(Invoice::query()->find($invoice->id))->toBeNull()
        ->and(Invoice::withTrashed()->find($invoice->id))->not->toBeNull();
});

test('deleteInvoice n affecte pas les factures d une autre PME', function () {
    ['user' => $user] = createSmeWithCompany();
    $otherCompany = Company::factory()->create(['type' => 'sme']);
    $otherInvoice = makeInvoice($otherCompany, ['reference' => 'FAC-OTHER-DELETE']);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->call('deleteInvoice', $otherInvoice->id);
})->throws(ModelNotFoundException::class);

// ─── Devise (currency) ────────────────────────────────────────────────────────

test('rows() inclut le champ currency pour chaque facture', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, ['reference' => 'FAC-EUR', 'currency' => 'EUR']);
    makeInvoice($company, ['reference' => 'FAC-XOF', 'currency' => 'XOF']);

    $rows = collect(
        Livewire::actingAs($user)->test('pages::pme.invoices.index')->get('rows')
    );

    expect($rows->firstWhere('reference', 'FAC-EUR')['currency'])->toBe('EUR');
    expect($rows->firstWhere('reference', 'FAC-XOF')['currency'])->toBe('XOF');
});

test('les montants EUR sont affichés en euros dans la liste des factures', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    // 814 000 centimes EUR = 8 140,00 EUR
    makeInvoice($company, [
        'reference' => 'FAC-EUR',
        'currency' => 'EUR',
        'subtotal' => 814_000,
        'tax_amount' => 0,
        'total' => 814_000,
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->assertSeeHtml('8 140,00');
});

test('les montants XOF sont affichés en FCFA dans la liste des factures', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    makeInvoice($company, [
        'reference' => 'FAC-XOF',
        'currency' => 'XOF',
        'subtotal' => 814_000,
        'tax_amount' => 0,
        'total' => 814_000,
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->assertSeeHtml('814 000');
});

test('la page détail affiche les montants EUR correctement', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    $client = Client::factory()->create(['company_id' => $company->id]);
    $invoice = Invoice::factory()
        ->forCompany($company)
        ->withClient($client)
        ->draft()
        ->create([
            'currency' => 'EUR',
            'subtotal' => 814_000,
            'tax_amount' => 0,
            'total' => 814_000,
        ]);

    $this->actingAs($user)
        ->get(route('pme.invoices.show', $invoice))
        ->assertOk()
        ->assertSeeHtml('8 140,00')
        ->assertDontSeeHtml('814 000 FCFA');
});

test('la page détail affiche les montants XOF correctement', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    $client = Client::factory()->create(['company_id' => $company->id]);
    $invoice = Invoice::factory()
        ->forCompany($company)
        ->withClient($client)
        ->draft()
        ->create([
            'currency' => 'XOF',
            'subtotal' => 500_000,
            'tax_amount' => 90_000,
            'total' => 590_000,
        ]);

    $this->actingAs($user)
        ->get(route('pme.invoices.show', $invoice))
        ->assertOk()
        ->assertSeeHtml('500 000')
        ->assertSeeHtml('590 000');
});

// ─── Réduction ────────────────────────────────────────────────────────────────

test('la page détail affiche la réduction quand elle est présente', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    $client = Client::factory()->create(['company_id' => $company->id]);
    $invoice = Invoice::factory()
        ->forCompany($company)
        ->withClient($client)
        ->draft()
        ->create([
            'currency' => 'XOF',
            'discount' => 10,
            'subtotal' => 100_000,
            'tax_amount' => 16_200,
            'total' => 106_200,
        ]);

    $this->actingAs($user)
        ->get(route('pme.invoices.show', $invoice))
        ->assertOk()
        ->assertSeeHtml('Réduction')
        ->assertSeeHtml('10%')
        ->assertSeeHtml('10 000');
});

test('la page détail n\'affiche pas la réduction quand elle est nulle', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    $invoice = makeInvoice($company, ['discount' => 0, 'currency' => 'XOF']);

    $this->actingAs($user)
        ->get(route('pme.invoices.show', $invoice))
        ->assertOk()
        ->assertDontSeeHtml('Réduction');
});

// ─── Actions dropdown ─────────────────────────────────────────────────────────

test('le lien Afficher la facture en PDF est présent dans le dropdown Actions', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();

    $invoice = makeInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->assertSeeHtml(route('pme.invoices.pdf', $invoice));
});

// ─── Aperçu de relance — openPreview() ───────────────────────────────────────

test('openPreview() positionne previewInvoiceId', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();
    $invoice = makeInvoice($company, ['status' => InvoiceStatus::Sent->value, 'amount_paid' => 0]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->assertSet('previewInvoiceId', null)
        ->call('openPreview', $invoice->id)
        ->assertSet('previewInvoiceId', $invoice->id);
});

test('openPreview() utilise WhatsApp quand le client a un téléphone', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();
    $client = Client::factory()->create(['company_id' => $company->id, 'phone' => '+221771112233', 'email' => 'c@example.com']);
    $invoice = makeInvoice($company, ['status' => InvoiceStatus::Sent->value, 'amount_paid' => 0, 'client_id' => $client->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->call('openPreview', $invoice->id)
        ->assertSet('previewChannel', 'whatsapp')
        ->assertSet('previewTone', 'cordial')
        ->assertSet('previewAttachPdf', true);
});

test('openPreview() fallback sur email quand le client n\'a pas de téléphone', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();
    $client = Client::factory()->create(['company_id' => $company->id, 'phone' => null, 'email' => 'c@example.com']);
    $invoice = makeInvoice($company, ['status' => InvoiceStatus::Sent->value, 'amount_paid' => 0, 'client_id' => $client->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->call('openPreview', $invoice->id)
        ->assertSet('previewChannel', 'email');
});

test('openPreview() ferme la timeline ouverte', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();
    $invoice = makeInvoice($company, ['status' => InvoiceStatus::Sent->value, 'amount_paid' => 0]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->set('timelineInvoiceId', $invoice->id)
        ->call('openPreview', $invoice->id)
        ->assertSet('timelineInvoiceId', null);
});

test('openPreview() lève une exception pour une facture d\'une autre PME', function () {
    ['user' => $user] = createSmeWithCompany();
    $otherCompany = Company::factory()->create(['type' => 'sme']);
    $otherInvoice = makeInvoice($otherCompany, ['status' => InvoiceStatus::Sent->value, 'amount_paid' => 0]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->call('openPreview', $otherInvoice->id);
})->throws(ModelNotFoundException::class);

// ─── Aperçu de relance — closePreview() ──────────────────────────────────────

test('closePreview() remet previewInvoiceId à null', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();
    $invoice = makeInvoice($company, ['status' => InvoiceStatus::Sent->value, 'amount_paid' => 0]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->call('openPreview', $invoice->id)
        ->assertSet('previewInvoiceId', $invoice->id)
        ->call('closePreview')
        ->assertSet('previewInvoiceId', null);
});

// ─── Aperçu de relance — previewInvoice (computed) ───────────────────────────

test('previewInvoice retourne la facture correspondant à previewInvoiceId', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();
    $invoice = makeInvoice($company, [
        'reference' => 'FAC-PREVIEW',
        'status' => InvoiceStatus::Sent->value,
        'amount_paid' => 0,
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->call('openPreview', $invoice->id);

    expect($component->get('previewInvoice'))->not->toBeNull()
        ->and($component->get('previewInvoice')->reference)->toBe('FAC-PREVIEW');
});

test('previewInvoice retourne null quand previewInvoiceId est null', function () {
    ['user' => $user] = createSmeWithCompany();

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.index');

    expect($component->get('previewInvoice'))->toBeNull();
});

test('previewInvoice n\'expose pas une facture d\'une autre PME', function () {
    ['user' => $user] = createSmeWithCompany();
    $otherCompany = Company::factory()->create(['type' => 'sme']);
    $otherInvoice = makeInvoice($otherCompany);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->set('previewInvoiceId', $otherInvoice->id);

    expect($component->get('previewInvoice'))->toBeNull();
});

// ─── Aperçu de relance — buildPreviewMessage() ───────────────────────────────

test('buildPreviewMessage() retourne le message cordial correct', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();
    $client = Client::factory()->create(['company_id' => $company->id, 'name' => 'Sonatel SA']);

    $invoice = Invoice::factory()
        ->forCompany($company)
        ->withClient($client)
        ->create([
            'status' => InvoiceStatus::Sent->value,
            'reference' => 'FAC-MSG',
            'total' => 100_000,
            'amount_paid' => 0,
        ]);

    $msg = Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->call('openPreview', $invoice->id)
        ->set('previewTone', 'cordial')
        ->instance()
        ->buildPreviewMessage();

    expect($msg)
        ->toContain('Sonatel SA')
        ->toContain('FAC-MSG')
        ->toContain('Cordialement,');
});

test('buildPreviewMessage() retourne le message ferme correct', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();
    $client = Client::factory()->create(['company_id' => $company->id, 'name' => 'Dakar Tech']);

    $invoice = Invoice::factory()
        ->forCompany($company)
        ->withClient($client)
        ->create([
            'status' => InvoiceStatus::Overdue->value,
            'reference' => 'FAC-FERME',
            'total' => 200_000,
            'amount_paid' => 0,
        ]);

    $msg = Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->call('openPreview', $invoice->id)
        ->set('previewTone', 'ferme')
        ->instance()
        ->buildPreviewMessage();

    expect($msg)
        ->toContain('FAC-FERME')
        ->toContain('retard de paiement');
});

test('buildPreviewMessage() retourne le message urgent correct', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();
    $client = Client::factory()->create(['company_id' => $company->id, 'name' => 'SENELEC']);

    $invoice = Invoice::factory()
        ->forCompany($company)
        ->withClient($client)
        ->create([
            'status' => InvoiceStatus::Overdue->value,
            'reference' => 'FAC-URGENT',
            'total' => 500_000,
            'amount_paid' => 0,
        ]);

    $msg = Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->call('openPreview', $invoice->id)
        ->set('previewTone', 'urgent')
        ->instance()
        ->buildPreviewMessage();

    expect($msg)
        ->toContain('SENELEC')
        ->toContain('URGENT')
        ->toContain('immédiate');
});

test('buildPreviewMessage() retourne une chaine vide quand previewInvoiceId est null', function () {
    ['user' => $user] = createSmeWithCompany();

    $msg = Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->instance()
        ->buildPreviewMessage();

    expect($msg)->toBe('');
});

// ─── Aperçu de relance — sendReminder() ──────────────────────────────────────

test('sendReminder() dispatche un toast et ferme l\'aperçu', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();
    $invoice = makeInvoice($company, [
        'status' => InvoiceStatus::Sent->value,
        'total' => 118_000,
        'amount_paid' => 0,
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->call('openPreview', $invoice->id)
        ->call('sendReminder', $invoice->id)
        ->assertDispatched('toast')
        ->assertSet('previewInvoiceId', null);
});

test('sendReminder() lève une exception pour une facture d\'une autre PME', function () {
    ['user' => $user] = createSmeWithCompany();
    $otherCompany = Company::factory()->create(['type' => 'sme']);
    $otherInvoice = makeInvoice($otherCompany, ['status' => InvoiceStatus::Sent->value, 'amount_paid' => 0]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->call('sendReminder', $otherInvoice->id);
})->throws(ModelNotFoundException::class);

// ─── Aperçu de relance — rendu Blade ─────────────────────────────────────────

test('le slideover de relance s\'affiche quand previewInvoiceId est défini', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();
    $client = Client::factory()->create(['company_id' => $company->id]);
    $invoice = Invoice::factory()
        ->forCompany($company)
        ->withClient($client)
        ->create(['status' => InvoiceStatus::Sent->value, 'amount_paid' => 0]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->call('openPreview', $invoice->id)
        ->assertSee('Aperçu de la relance');
});

test('"Relancer le client" est visible dans le dropdown pour une facture envoyée', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();
    $invoice = makeInvoice($company, ['status' => InvoiceStatus::Sent->value, 'amount_paid' => 0]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->assertSeeHtml("openPreview('{$invoice->id}')");
});

test('"Relancer le client" est visible pour une facture en retard', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();
    $invoice = makeInvoice($company, [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(10),
        'amount_paid' => 0,
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->assertSeeHtml("openPreview('{$invoice->id}')");
});

test('"Relancer le client" n\'est pas affiché pour une facture payée', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();
    $invoice = makeInvoice($company, ['status' => InvoiceStatus::Paid->value]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->assertDontSeeHtml("openPreview('{$invoice->id}')");
});

test('"Joindre PDF" est masqué dans le slideover quand le canal est SMS', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();
    $client = Client::factory()->create([
        'company_id' => $company->id,
        'phone' => '+221700000000',
    ]);
    $invoice = Invoice::factory()
        ->forCompany($company)
        ->withClient($client)
        ->create(['status' => InvoiceStatus::Sent->value, 'amount_paid' => 0]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->call('openPreview', $invoice->id)
        ->set('previewChannel', 'sms')
        ->assertDontSee('Joindre PDF');
});

test('"Joindre PDF" est visible quand le canal est WhatsApp', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();
    $client = Client::factory()->create([
        'company_id' => $company->id,
        'phone' => '+221700000000',
    ]);
    $invoice = Invoice::factory()
        ->forCompany($company)
        ->withClient($client)
        ->create(['status' => InvoiceStatus::Sent->value, 'amount_paid' => 0]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->call('openPreview', $invoice->id)
        ->set('previewChannel', 'whatsapp')
        ->assertSee('Joindre PDF');
});

// ─── Voir le client — factures ────────────────────────────────────────────────

test('rows() inclut client_id pour chaque facture', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Invoice::unguarded(fn () => Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'FAC-CLI-ID',
        'status' => InvoiceStatus::Paid->value,
        'issued_at' => now(),
        'due_at' => now()->addDays(30),
        'subtotal' => 100_000,
        'tax_amount' => 18_000,
        'total' => 118_000,
        'amount_paid' => 118_000,
    ]));

    $row = collect(
        Livewire::actingAs($user)->test('pages::pme.invoices.index')->get('rows')
    )->firstWhere('reference', 'FAC-CLI-ID');

    expect($row['client_id'])->toBe($client->id);
});

test('"Voir le client" est affiché dans le dropdown quand la facture a un client', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();
    $invoice = makeInvoice($company);

    $clientUrl = route('pme.clients.show', $invoice->client_id);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->assertSeeHtml($clientUrl)
        ->assertSee('Voir le client');
});

// ─── Canal d'envoi — ordre et conditions ──────────────────────────────────────

test('WhatsApp apparaît avant SMS dans le slideover quand le client a un téléphone', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();
    $client = Client::factory()->create([
        'company_id' => $company->id,
        'phone' => '+221700000000',
        'email' => null,
    ]);
    $invoice = Invoice::factory()
        ->forCompany($company)
        ->withClient($client)
        ->create(['status' => InvoiceStatus::Sent->value, 'amount_paid' => 0]);

    $html = Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->call('openPreview', $invoice->id)
        ->html();

    expect(strpos($html, "'whatsapp'"))->toBeLessThan(strpos($html, "'sms'"));
});

test('Email apparaît après WhatsApp et SMS quand le client a téléphone et email', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();
    $client = Client::factory()->create([
        'company_id' => $company->id,
        'phone' => '+221700000000',
        'email' => 'client@example.com',
    ]);
    $invoice = Invoice::factory()
        ->forCompany($company)
        ->withClient($client)
        ->create(['status' => InvoiceStatus::Sent->value, 'amount_paid' => 0]);

    $html = Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->call('openPreview', $invoice->id)
        ->html();

    $posWhatsapp = strpos($html, "'whatsapp'");
    $posSms = strpos($html, "'sms'");
    $posEmail = strpos($html, "'email'");

    expect($posWhatsapp)->toBeLessThan($posSms)
        ->and($posSms)->toBeLessThan($posEmail);
});

test('Email est affiché quand le client a seulement un email', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();
    $client = Client::factory()->create([
        'company_id' => $company->id,
        'phone' => null,
        'email' => 'client@example.com',
    ]);
    $invoice = Invoice::factory()
        ->forCompany($company)
        ->withClient($client)
        ->create(['status' => InvoiceStatus::Sent->value, 'amount_paid' => 0]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->call('openPreview', $invoice->id)
        ->assertSee('Email')
        ->assertDontSee('WhatsApp')
        ->assertDontSee('SMS');
});

test('Email est masqué quand le client n\'a pas d\'adresse email', function () {
    ['user' => $user, 'company' => $company] = createSmeWithCompany();
    $client = Client::factory()->create([
        'company_id' => $company->id,
        'phone' => '+221700000000',
        'email' => null,
    ]);
    $invoice = Invoice::factory()
        ->forCompany($company)
        ->withClient($client)
        ->create(['status' => InvoiceStatus::Sent->value, 'amount_paid' => 0]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->call('openPreview', $invoice->id)
        ->assertDontSee('Email');
});
