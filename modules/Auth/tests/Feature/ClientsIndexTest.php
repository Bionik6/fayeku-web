<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Auth\Models\AccountantCompany;
use Modules\Auth\Models\Company;
use Modules\PME\Invoicing\Enums\InvoiceStatus;
use Modules\PME\Invoicing\Models\Invoice;
use Modules\Shared\Models\User;

uses(RefreshDatabase::class);

/**
 * Helper : crée un user avec son cabinet et des PMEs gérées.
 *
 * @return array{user: User, firm: Company, smes: array<Company>}
 */
function setupClientsPortfolio(int $count = 3): array
{
    $user = User::factory()->create();
    $firm = Company::factory()->accountantFirm()->create();
    $firm->users()->attach($user->id, ['role' => 'admin']);

    $smes = [];
    for ($i = 0; $i < $count; $i++) {
        $sme = Company::factory()->create(['plan' => 'basique']);
        AccountantCompany::create([
            'accountant_firm_id' => $firm->id,
            'sme_company_id' => $sme->id,
            'started_at' => now()->subMonths(3),
        ]);
        $smes[] = $sme;
    }

    return compact('user', 'firm', 'smes');
}

/**
 * Helper : crée une facture pour une PME.
 *
 * @param  array<string, mixed>  $overrides
 */
function makeClientInvoice(Company $sme, array $overrides = []): Invoice
{
    return Invoice::unguarded(fn () => Invoice::create(array_merge([
        'company_id' => $sme->id,
        'client_id' => null,
        'reference' => 'FAC-'.fake()->unique()->numerify('###'),
        'status' => InvoiceStatus::Paid->value,
        'issued_at' => now(),
        'due_at' => now()->addDays(30),
        'subtotal' => 100_000,
        'tax_amount' => 0,
        'total' => 100_000,
        'amount_paid' => 100_000,
    ], $overrides)));
}

// ─── Accès ────────────────────────────────────────────────────────────────────

test('un invité est redirigé vers la page de connexion', function () {
    $this->get(route('clients.index'))->assertRedirect(route('login'));
});

test('un utilisateur authentifié peut accéder à la page clients', function () {
    ['user' => $user] = setupClientsPortfolio(0);

    Livewire::actingAs($user)
        ->test('pages::clients.index')
        ->assertOk();
});

// ─── Rendu ────────────────────────────────────────────────────────────────────

test('le header PORTEFEUILLE et le titre Clients sont visibles', function () {
    ['user' => $user] = setupClientsPortfolio(0);

    Livewire::actingAs($user)
        ->test('pages::clients.index')
        ->assertSee('Portefeuille')
        ->assertSee('Clients');
});

test('le sous-titre affiche le nombre correct de clients', function () {
    ['user' => $user] = setupClientsPortfolio(4);

    Livewire::actingAs($user)
        ->test('pages::clients.index')
        ->assertSee('4 clients');
});

test('le badge tier est Gold avec 7 clients', function () {
    ['user' => $user] = setupClientsPortfolio(7);

    Livewire::actingAs($user)
        ->test('pages::clients.index')
        ->assertSet('tierValue', 'gold')
        ->assertSee('Gold');
});

test('un cabinet sans clients affiche le message vide', function () {
    ['user' => $user] = setupClientsPortfolio(0);

    Livewire::actingAs($user)
        ->test('pages::clients.index')
        ->assertSee('Aucun client dans votre portefeuille');
});

// ─── statusCounts ─────────────────────────────────────────────────────────────

test('statusCounts retourne les bons totaux non filtrés', function () {
    ['user' => $user, 'smes' => $smes] = setupClientsPortfolio(3);

    // critique
    makeClientInvoice($smes[0], [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(65),
        'amount_paid' => 0,
    ]);
    // attente
    makeClientInvoice($smes[1], [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(10),
        'amount_paid' => 0,
    ]);
    // à jour
    makeClientInvoice($smes[2]);

    $component = Livewire::actingAs($user)->test('pages::clients.index');

    expect($component->get('statusCounts'))->toBe([
        'all' => 3,
        'a_jour' => 1,
        'attente' => 1,
        'critique' => 1,
    ]);
});

test('statusCounts ne tient pas compte des filtres actifs', function () {
    ['user' => $user, 'smes' => $smes] = setupClientsPortfolio(2);

    makeClientInvoice($smes[0], [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(70),
        'amount_paid' => 0,
    ]);
    makeClientInvoice($smes[1]);

    $component = Livewire::actingAs($user)
        ->test('pages::clients.index')
        ->call('setFilterStatus', 'critique');

    // Même avec le filtre critique actif, statusCounts.all reste 2
    expect($component->get('statusCounts')['all'])->toBe(2);
    expect($component->get('statusCounts')['a_jour'])->toBe(1);
});

// ─── Filtrage par statut ───────────────────────────────────────────────────────

test('setFilterStatus("critique") ne retourne que les clients critiques', function () {
    ['user' => $user, 'smes' => $smes] = setupClientsPortfolio(3);

    makeClientInvoice($smes[0], [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(65),
        'amount_paid' => 0,
    ]);
    makeClientInvoice($smes[1], [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(10),
        'amount_paid' => 0,
    ]);
    makeClientInvoice($smes[2]);

    $component = Livewire::actingAs($user)
        ->test('pages::clients.index')
        ->call('setFilterStatus', 'critique');

    $rows = $component->get('rows');
    expect($rows)->toHaveCount(1);
    expect($rows[0]['status'])->toBe('critique');
});

test('setFilterStatus("a_jour") ne retourne que les clients sains', function () {
    ['user' => $user, 'smes' => $smes] = setupClientsPortfolio(3);

    makeClientInvoice($smes[0], [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(65),
        'amount_paid' => 0,
    ]);
    makeClientInvoice($smes[1], [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(10),
        'amount_paid' => 0,
    ]);
    makeClientInvoice($smes[2]);

    $component = Livewire::actingAs($user)
        ->test('pages::clients.index')
        ->call('setFilterStatus', 'a_jour');

    $rows = $component->get('rows');
    expect($rows)->toHaveCount(1);
    expect($rows[0]['status'])->toBe('a_jour');
});

test('setFilterStatus réinitialise showAll', function () {
    ['user' => $user] = setupClientsPortfolio(1);

    Livewire::actingAs($user)
        ->test('pages::clients.index')
        ->set('showAll', true)
        ->call('setFilterStatus', 'a_jour')
        ->assertSet('showAll', false);
});

// ─── Filtrage par plan ────────────────────────────────────────────────────────

test('filterPlan filtre par plan essentiel', function () {
    ['user' => $user, 'firm' => $firm] = setupClientsPortfolio(0);

    $essentiel = Company::factory()->create(['plan' => 'essentiel']);
    $basique = Company::factory()->create(['plan' => 'basique']);

    foreach ([$essentiel, $basique] as $sme) {
        AccountantCompany::create([
            'accountant_firm_id' => $firm->id,
            'sme_company_id' => $sme->id,
            'started_at' => now()->subMonth(),
        ]);
    }

    $component = Livewire::actingAs($user)
        ->test('pages::clients.index')
        ->set('filterPlan', 'essentiel');

    $rows = $component->get('rows');
    expect($rows)->toHaveCount(1);
    expect($rows[0]['plan_slug'])->toBe('essentiel');
});

// ─── Recherche ────────────────────────────────────────────────────────────────

test('la recherche retourne uniquement les clients dont le nom correspond', function () {
    ['user' => $user, 'firm' => $firm] = setupClientsPortfolio(0);

    $kane = Company::factory()->create(['name' => 'Kane Import SARL']);
    $other = Company::factory()->create(['name' => 'Sow BTP SARL']);

    foreach ([$kane, $other] as $sme) {
        AccountantCompany::create([
            'accountant_firm_id' => $firm->id,
            'sme_company_id' => $sme->id,
            'started_at' => now()->subMonth(),
        ]);
    }

    $rows = Livewire::actingAs($user)
        ->test('pages::clients.index')
        ->set('search', 'kane')
        ->get('rows');

    expect($rows)->toHaveCount(1);
    expect($rows[0]['name'])->toBe('Kane Import SARL');
});

test('la recherche est insensible à la casse', function () {
    ['user' => $user, 'firm' => $firm] = setupClientsPortfolio(0);

    $sme = Company::factory()->create(['name' => 'Kane Import SARL']);
    AccountantCompany::create([
        'accountant_firm_id' => $firm->id,
        'sme_company_id' => $sme->id,
        'started_at' => now()->subMonth(),
    ]);

    $rowsUpper = Livewire::actingAs($user)
        ->test('pages::clients.index')
        ->set('search', 'KANE')
        ->get('rows');

    expect($rowsUpper)->toHaveCount(1);
});

test('une recherche vide retourne tous les clients', function () {
    ['user' => $user] = setupClientsPortfolio(3);

    $rows = Livewire::actingAs($user)
        ->test('pages::clients.index')
        ->set('search', '')
        ->get('rows');

    expect($rows)->toHaveCount(3);
});

test('une recherche sans correspondance affiche le message vide', function () {
    ['user' => $user] = setupClientsPortfolio(2);

    Livewire::actingAs($user)
        ->test('pages::clients.index')
        ->set('search', 'xyzinexistant')
        ->assertSee('Aucun client ne correspond à ces filtres');
});

// ─── Tri ──────────────────────────────────────────────────────────────────────

test('sort("name") trie par ordre alphabétique ascendant', function () {
    ['user' => $user, 'firm' => $firm] = setupClientsPortfolio(0);

    foreach (['Zara Corp', 'Alpha Ltd', 'Beta Inc'] as $name) {
        $sme = Company::factory()->create(['name' => $name]);
        AccountantCompany::create([
            'accountant_firm_id' => $firm->id,
            'sme_company_id' => $sme->id,
            'started_at' => now()->subMonth(),
        ]);
    }

    $rows = Livewire::actingAs($user)
        ->test('pages::clients.index')
        ->call('sort', 'name')
        ->get('rows');

    expect($rows[0]['name'])->toBe('Alpha Ltd');
    expect($rows[1]['name'])->toBe('Beta Inc');
    expect($rows[2]['name'])->toBe('Zara Corp');
});

test('sort("name") deux fois inverse le tri en descendant', function () {
    ['user' => $user, 'firm' => $firm] = setupClientsPortfolio(0);

    foreach (['Zara Corp', 'Alpha Ltd', 'Beta Inc'] as $name) {
        $sme = Company::factory()->create(['name' => $name]);
        AccountantCompany::create([
            'accountant_firm_id' => $firm->id,
            'sme_company_id' => $sme->id,
            'started_at' => now()->subMonth(),
        ]);
    }

    $rows = Livewire::actingAs($user)
        ->test('pages::clients.index')
        ->call('sort', 'name')
        ->call('sort', 'name')
        ->get('rows');

    expect($rows[0]['name'])->toBe('Zara Corp');
    expect($rows[2]['name'])->toBe('Alpha Ltd');
});

test('sort("pending_amount") desc met le montant le plus élevé en premier', function () {
    ['user' => $user, 'smes' => $smes] = setupClientsPortfolio(2);

    makeClientInvoice($smes[0], [
        'status' => InvoiceStatus::Overdue->value,
        'total' => 200_000,
        'amount_paid' => 0,
        'due_at' => now()->subDays(5),
    ]);
    makeClientInvoice($smes[1], [
        'status' => InvoiceStatus::Overdue->value,
        'total' => 800_000,
        'amount_paid' => 0,
        'due_at' => now()->subDays(5),
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::clients.index')
        ->call('sort', 'pending_amount')
        ->call('sort', 'pending_amount'); // second call → desc

    $rows = $component->get('rows');
    expect($rows[0]['pending_amount'])->toBe(800_000);
    expect($rows[1]['pending_amount'])->toBe(200_000);
});

test('le tri par statut met les critiques en premier par défaut', function () {
    ['user' => $user, 'smes' => $smes] = setupClientsPortfolio(3);

    makeClientInvoice($smes[0]);  // à jour
    makeClientInvoice($smes[1], [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(65),
        'amount_paid' => 0,
    ]);  // critique
    makeClientInvoice($smes[2], [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(10),
        'amount_paid' => 0,
    ]);  // attente

    $rows = Livewire::actingAs($user)
        ->test('pages::clients.index')
        ->get('rows');

    expect($rows[0]['status'])->toBe('critique');
    expect($rows[1]['status'])->toBe('attente');
    expect($rows[2]['status'])->toBe('a_jour');
});

// ─── Show-all ─────────────────────────────────────────────────────────────────

test('au maximum 6 lignes sont affichées par défaut avec plus de 6 clients', function () {
    ['user' => $user] = setupClientsPortfolio(8);

    Livewire::actingAs($user)
        ->test('pages::clients.index')
        ->assertSet('showAll', false)
        ->assertSee('Afficher tout');
});

test('le bouton affiche le bon nombre de clients restants', function () {
    ['user' => $user] = setupClientsPortfolio(8);

    Livewire::actingAs($user)
        ->test('pages::clients.index')
        ->assertSee('+ 2 autres clients');
});

test('showAll=true expose tous les clients', function () {
    ['user' => $user] = setupClientsPortfolio(8);

    Livewire::actingAs($user)
        ->test('pages::clients.index')
        ->set('showAll', true)
        ->assertDontSee('Afficher tout');
});

test('avec 6 clients ou moins le bouton Afficher tout est absent', function () {
    ['user' => $user] = setupClientsPortfolio(6);

    Livewire::actingAs($user)
        ->test('pages::clients.index')
        ->assertDontSee('Afficher tout');
});

// ─── État URL (#[Url]) ────────────────────────────────────────────────────────

test('#[Url] — search est persisté via assertSet', function () {
    ['user' => $user] = setupClientsPortfolio(2);

    Livewire::actingAs($user)
        ->test('pages::clients.index')
        ->set('search', 'test query')
        ->assertSet('search', 'test query');
});

test('#[Url] — filterStatus est persisté via assertSet', function () {
    ['user' => $user] = setupClientsPortfolio(1);

    Livewire::actingAs($user)
        ->test('pages::clients.index')
        ->call('setFilterStatus', 'critique')
        ->assertSet('filterStatus', 'critique');
});

test('#[Url] — filterPlan est persisté via assertSet', function () {
    ['user' => $user] = setupClientsPortfolio(1);

    Livewire::actingAs($user)
        ->test('pages::clients.index')
        ->set('filterPlan', 'essentiel')
        ->assertSet('filterPlan', 'essentiel');
});

test('#[Url] — sortBy et sortDirection sont persistés après sort()', function () {
    ['user' => $user] = setupClientsPortfolio(1);

    Livewire::actingAs($user)
        ->test('pages::clients.index')
        ->call('sort', 'name')
        ->assertSet('sortBy', 'name')
        ->assertSet('sortDirection', 'asc')
        ->call('sort', 'name')
        ->assertSet('sortBy', 'name')
        ->assertSet('sortDirection', 'desc');
});
