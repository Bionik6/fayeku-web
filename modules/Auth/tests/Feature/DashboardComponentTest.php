<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Auth\Models\AccountantCompany;
use Modules\Auth\Models\Company;
use Modules\Compta\Partnership\Models\Commission;
use Modules\Compta\Partnership\Models\PartnerInvitation;
use Modules\Compta\Portfolio\Models\DismissedAlert;
use Modules\PME\Invoicing\Enums\InvoiceStatus;
use Modules\PME\Invoicing\Models\Invoice;
use Modules\Shared\Models\User;

uses(RefreshDatabase::class);

/**
 * Helper : crée un user avec son cabinet comptable et des PMEs gérées.
 *
 * @return array{user: User, firm: Company, smes: array<Company>}
 */
function createFirmWithSmes(int $smeCount = 3): array
{
    $user = User::factory()->accountantFirm()->create();
    $firm = Company::factory()->accountantFirm()->create();
    $firm->users()->attach($user->id, ['role' => 'admin']);

    $smes = [];
    for ($i = 0; $i < $smeCount; $i++) {
        $sme = Company::factory()->create();
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
function createInvoice(Company $sme, array $overrides = []): Invoice
{
    // amount_paid n'est pas dans $fillable → on contourne avec unguarded()
    return Invoice::unguarded(fn () => Invoice::create(array_merge([
        'company_id' => $sme->id,
        'client_id' => null,
        'reference' => 'FAC-'.fake()->numerify('###'),
        'status' => InvoiceStatus::Paid->value,
        'issued_at' => now(),
        'due_at' => now()->addDays(30),
        'subtotal' => 100_000,
        'tax_amount' => 0,
        'total' => 100_000,
        'amount_paid' => 100_000,
    ], $overrides)));
}

// ─── Affichage de base ────────────────────────────────────────────────────────

test('le dashboard affiche le nom du cabinet', function () {
    ['user' => $user, 'firm' => $firm] = createFirmWithSmes(0);

    Livewire::actingAs($user)
        ->test('pages::dashboard.index')
        ->assertSee($firm->name);
});

test('un user sans cabinet voit le dashboard sans erreur', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard.index')
        ->assertSet('activeClientsCount', 0)
        ->assertSet('criticalCount', 0)
        ->assertSet('watchCount', 0)
        ->assertSet('upToDateCount', 0);
});

// ─── Stat cards ───────────────────────────────────────────────────────────────

test('activeClientsCount reflète les PMEs gérées actives', function () {
    ['user' => $user] = createFirmWithSmes(5);

    Livewire::actingAs($user)
        ->test('pages::dashboard.index')
        ->assertSet('activeClientsCount', 5);
});

test('les PMEs avec contrat terminé ne sont pas comptées', function () {
    ['user' => $user, 'firm' => $firm] = createFirmWithSmes(2);

    $endedSme = Company::factory()->create();
    AccountantCompany::create([
        'accountant_firm_id' => $firm->id,
        'sme_company_id' => $endedSme->id,
        'started_at' => now()->subYear(),
        'ended_at' => now()->subMonth(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::dashboard.index')
        ->assertSet('activeClientsCount', 2);
});

test('criticalCount : PME avec facture overdue > 60 jours', function () {
    ['user' => $user, 'smes' => $smes] = createFirmWithSmes(3);

    // 1 facture critique (overdue depuis 62 jours)
    createInvoice($smes[0], [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(62),
        'amount_paid' => 0,
    ]);

    // 1 facture overdue récente (30 jours) → à surveiller
    createInvoice($smes[1], [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(30),
        'amount_paid' => 0,
    ]);

    // PME saine
    createInvoice($smes[2]);

    Livewire::actingAs($user)
        ->test('pages::dashboard.index')
        ->assertSet('criticalCount', 1)
        ->assertSet('watchCount', 1)
        ->assertSet('upToDateCount', 1);
});

test('watchCount : PME sans facture depuis 30 jours', function () {
    ['user' => $user, 'smes' => $smes] = createFirmWithSmes(2);

    // Facture ancienne → inactif
    createInvoice($smes[0], ['issued_at' => now()->subDays(45)]);

    // Facture récente → à jour
    createInvoice($smes[1], ['issued_at' => now()->subDays(5)]);

    Livewire::actingAs($user)
        ->test('pages::dashboard.index')
        ->assertSet('watchCount', 1)
        ->assertSet('upToDateCount', 1);
});

test('upToDateCount = actifs - critiques - à surveiller', function () {
    ['user' => $user, 'smes' => $smes] = createFirmWithSmes(4);

    createInvoice($smes[0], [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(90),
        'amount_paid' => 0,
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::dashboard.index');

    expect($component->get('upToDateCount'))
        ->toBe($component->get('activeClientsCount') - $component->get('criticalCount') - $component->get('watchCount'));
});

// ─── Commission ───────────────────────────────────────────────────────────────

test('commissionAmount affiche la somme des commissions du mois courant', function () {
    ['user' => $user, 'firm' => $firm, 'smes' => $smes] = createFirmWithSmes(2);

    Commission::create([
        'accountant_firm_id' => $firm->id,
        'sme_company_id' => $smes[0]->id,
        'amount' => 75_000,
        'period_month' => now()->startOfMonth(),
        'status' => 'pending',
    ]);

    Commission::create([
        'accountant_firm_id' => $firm->id,
        'sme_company_id' => $smes[1]->id,
        'amount' => 112_500,
        'period_month' => now()->startOfMonth(),
        'status' => 'pending',
    ]);

    // Commission du mois passé → ne doit pas être incluse
    Commission::create([
        'accountant_firm_id' => $firm->id,
        'sme_company_id' => $smes[0]->id,
        'amount' => 999_999,
        'period_month' => now()->subMonth()->startOfMonth(),
        'status' => 'paid',
    ]);

    Livewire::actingAs($user)
        ->test('pages::dashboard.index')
        ->assertSet('commissionAmount', 187_500);
});

// ─── Tier partenaire ──────────────────────────────────────────────────────────

test('tier est Partner avec moins de 5 clients', function () {
    ['user' => $user] = createFirmWithSmes(3);

    Livewire::actingAs($user)
        ->test('pages::dashboard.index')
        ->assertSet('tierValue', 'partner');
});

test('tier est Gold avec 5 à 14 clients', function () {
    ['user' => $user] = createFirmWithSmes(7);

    Livewire::actingAs($user)
        ->test('pages::dashboard.index')
        ->assertSet('tierValue', 'gold');
});

test('tier est Platinum avec 15 clients ou plus', function () {
    ['user' => $user] = createFirmWithSmes(15);

    Livewire::actingAs($user)
        ->test('pages::dashboard.index')
        ->assertSet('tierValue', 'platinum')
        ->assertSet('isPlatinum', true)
        ->assertSet('tierProgress', 100);
});

test('tierProgress est calculé correctement en Gold', function () {
    ['user' => $user] = createFirmWithSmes(10); // 10 clients, Gold (5-14), progress = (10-5)/10 = 50%

    Livewire::actingAs($user)
        ->test('pages::dashboard.index')
        ->assertSet('tierValue', 'gold')
        ->assertSet('tierProgress', 50)
        ->assertSet('nextThreshold', 15);
});

// ─── Alertes ──────────────────────────────────────────────────────────────────

test('une facture overdue > 60j génère une alerte critique', function () {
    ['user' => $user, 'smes' => $smes] = createFirmWithSmes(1);

    createInvoice($smes[0], [
        'reference' => 'FAC-089',
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(62),
        'total' => 850_000,
        'amount_paid' => 0,
    ]);

    $component = Livewire::actingAs($user)->test('pages::dashboard.index');

    $alerts = $component->get('alerts');
    expect($alerts)->toHaveCount(1);
    expect($alerts[0]['type'])->toBe('critical');
    expect($alerts[0]['title'])->toContain('Impayé critique');
});

test('une PME inactive depuis 30j génère une alerte watch', function () {
    ['user' => $user, 'smes' => $smes] = createFirmWithSmes(1);

    createInvoice($smes[0], ['issued_at' => now()->subDays(35)]);

    $component = Livewire::actingAs($user)->test('pages::dashboard.index');

    $alerts = $component->get('alerts');
    expect($alerts)->toHaveCount(1);
    expect($alerts[0]['type'])->toBe('watch');
    expect($alerts[0]['title'])->toContain('Inactif');
});

test('une invitation acceptée récemment génère une alerte new', function () {
    ['user' => $user, 'firm' => $firm, 'smes' => $smes] = createFirmWithSmes(0);

    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => fake()->uuid(),
        'invitee_phone' => '+221701234567',
        'invitee_name' => 'Dakar Pharma',
        'recommended_plan' => 'essentiel',
        'status' => 'accepted',
        'accepted_at' => now()->subDays(2),
        'sme_company_id' => $smes[0]->id ?? null,
    ]);

    $component = Livewire::actingAs($user)->test('pages::dashboard.index');

    $alerts = $component->get('alerts');
    expect(collect($alerts)->where('type', 'new'))->toHaveCount(1);
});

test('le widget alertes du dashboard exclut les alertes archivées', function () {
    ['user' => $user, 'smes' => $smes] = createFirmWithSmes(1);

    $invoice = createInvoice($smes[0], [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(65),
        'amount_paid' => 0,
    ]);

    DismissedAlert::create([
        'user_id' => $user->id,
        'alert_key' => 'critical_'.$invoice->id,
        'dismissed_at' => now(),
    ]);

    $alerts = Livewire::actingAs($user)
        ->test('pages::dashboard.index')
        ->get('alerts');

    expect(collect($alerts)->where('alert_key', 'critical_'.$invoice->id))->toHaveCount(0);
});

test('le widget alertes du dashboard reflète les mêmes alertes actives que la page alertes', function () {
    ['user' => $user, 'firm' => $firm, 'smes' => $smes] = createFirmWithSmes(2);

    $dismissedInvoice = createInvoice($smes[0], [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(70),
        'amount_paid' => 0,
    ]);

    createInvoice($smes[1], [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(65),
        'amount_paid' => 0,
    ]);

    createInvoice($smes[0], ['issued_at' => now()->subDays(45)]);
    createInvoice($smes[1], ['issued_at' => now()->subDays(40)]);

    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => fake()->uuid(),
        'invitee_phone' => '+221701234579',
        'invitee_name' => 'Ba Industries',
        'status' => 'accepted',
        'accepted_at' => now()->subDay(),
    ]);

    DismissedAlert::create([
        'user_id' => $user->id,
        'alert_key' => 'critical_'.$dismissedInvoice->id,
        'dismissed_at' => now(),
    ]);

    $dashboardAlerts = Livewire::actingAs($user)
        ->test('pages::dashboard.index')
        ->get('alerts');

    $pageAlerts = Livewire::actingAs($user)
        ->test('pages::alerts.index')
        ->get('alerts');

    expect(collect($dashboardAlerts)->pluck('alert_key')->values()->all())
        ->toEqual(collect($pageAlerts)->take(5)->pluck('alert_key')->values()->all());
});

test('pas d\'alerte de type new pour une invitation acceptée il y a plus de 7 jours', function () {
    ['user' => $user, 'firm' => $firm] = createFirmWithSmes(0);

    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => fake()->uuid(),
        'invitee_phone' => '+221701234567',
        'invitee_name' => 'Vieille Pharma',
        'status' => 'accepted',
        'accepted_at' => now()->subDays(10),
    ]);

    $component = Livewire::actingAs($user)->test('pages::dashboard.index');

    expect(collect($component->get('alerts'))->where('type', 'new'))->toHaveCount(0);
});

// ─── Aperçu portefeuille ──────────────────────────────────────────────────────

test('portfolio contient au maximum 10 entrées', function () {
    ['user' => $user] = createFirmWithSmes(12);

    $portfolio = Livewire::actingAs($user)
        ->test('pages::dashboard.index')
        ->get('portfolio');

    expect($portfolio)->toHaveCount(10);
});

test('portfolio trie les clients critiques en premier', function () {
    ['user' => $user, 'smes' => $smes] = createFirmWithSmes(2);

    createInvoice($smes[0], [
        'status' => 'overdue',
        'due_at' => now()->subDays(70),
        'total' => 100_000,
    ]);

    $portfolio = Livewire::actingAs($user)
        ->test('pages::dashboard.index')
        ->get('portfolio');

    expect(collect($portfolio)->first()['status'])->toBe('critique');
});

test('portfolio affiche le tableau dans la vue', function () {
    ['user' => $user, 'smes' => $smes] = createFirmWithSmes(1);

    Livewire::actingAs($user)
        ->test('pages::dashboard.index')
        ->assertSee(__('Aperçu du portefeuille'));
});

test('le dashboard affiche le nouveau copy métier', function () {
    ['user' => $user, 'firm' => $firm, 'smes' => $smes] = createFirmWithSmes(2);

    createInvoice($smes[0], [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(70),
        'amount_paid' => 0,
    ]);

    Commission::create([
        'accountant_firm_id' => $firm->id,
        'sme_company_id' => $smes[0]->id,
        'amount' => 187_500,
        'period_month' => now()->startOfMonth(),
        'status' => 'pending',
    ]);

    Livewire::actingAs($user)
        ->test('pages::dashboard.index')
        ->assertSee('1 impayé critique à traiter')
        ->assertSee(__('Clients suivis'))
        ->assertSee(__('Clients à jour'))
        ->assertSee(__('Dossiers à relancer'))
        ->assertSee(__('Commissions du mois'))
        ->assertSee(__('Votre niveau partenaire'))
        ->assertSee(__('Aperçu du portefeuille'))
        ->assertSee(__('Offre'))
        ->assertSee(__('Taux de recouvrement'));
});

test('portfolio est vide sans clients', function () {
    $user = User::factory()->accountantFirm()->create();
    $firm = Company::factory()->accountantFirm()->create();
    $firm->users()->attach($user->id, ['role' => 'admin']);

    $portfolio = Livewire::actingAs($user)
        ->test('pages::dashboard.index')
        ->get('portfolio');

    expect($portfolio)->toBeEmpty();
});

// ─── Navigation ───────────────────────────────────────────────────────────────

test('les raccourcis affichent le lien vers le portefeuille clients', function () {
    ['user' => $user] = createFirmWithSmes(1);

    Livewire::actingAs($user)
        ->test('pages::dashboard.index')
        ->assertSee(route('clients.index'), false);
});

test('la route HTTP dashboard est accessible', function () {
    ['user' => $user] = createFirmWithSmes(1);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful();
});
