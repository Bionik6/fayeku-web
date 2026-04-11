<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Auth\Models\AccountantCompany;
use Modules\Auth\Models\Company;
use Modules\Auth\Models\Subscription;
use Modules\Compta\Partnership\Models\Commission;
use Modules\Compta\Partnership\Models\CommissionPayment;
use Modules\Compta\Partnership\Models\PartnerInvitation;
use Modules\Shared\Models\User;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function commTestCreateFirm(int $smeCount = 3): array
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
 * Creates a firm with SMEs that have named companies and active subscriptions.
 *
 * @return array{user: User, firm: Company, smes: array<string, Company>}
 */
function commTestCreateFirmWithSubscriptions(): array
{
    $user = User::factory()->accountantFirm()->create();
    $firm = Company::factory()->accountantFirm()->create();
    $firm->users()->attach($user->id, ['role' => 'admin']);

    $smeData = [
        'kane_import' => ['name' => 'Kane Import SARL', 'plan' => 'essentiel', 'price' => 20_000],
        'sow_btp' => ['name' => 'Sow BTP SARL', 'plan' => 'essentiel', 'price' => 20_000],
        'mbaye_transport' => ['name' => 'Mbaye Transport', 'plan' => 'basique', 'price' => 10_000],
        'coury_commerce' => ['name' => 'Coury Commerce', 'plan' => 'basique', 'price' => 10_000],
    ];

    $smes = [];
    foreach ($smeData as $key => $data) {
        $sme = Company::factory()->create(['name' => $data['name']]);
        AccountantCompany::create([
            'accountant_firm_id' => $firm->id,
            'sme_company_id' => $sme->id,
            'started_at' => now()->subMonths(3),
        ]);
        Subscription::factory()->active()->create([
            'company_id' => $sme->id,
            'plan_slug' => $data['plan'],
            'price_paid' => $data['price'],
            'invited_by_firm_id' => $firm->id,
        ]);
        $smes[$key] = $sme;
    }

    return compact('user', 'firm', 'smes');
}

// ─── Accès & rendu ────────────────────────────────────────────────────────────

test('la page commissions est accessible pour un utilisateur authentifié', function () {
    ['user' => $user] = commTestCreateFirm(0);

    $this->actingAs($user)
        ->get(route('commissions.index'))
        ->assertSuccessful();
});

test('la page commissions redirige un utilisateur non authentifié', function () {
    $this->get(route('commissions.index'))
        ->assertRedirect(route('login'));
});

test('le composant commissions se rend sans erreur pour un user sans cabinet', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::commissions.index')
        ->assertOk();
});

test('le composant commissions affiche les sections principales', function () {
    ['user' => $user] = commTestCreateFirm(1);

    Livewire::actingAs($user)
        ->test('pages::commissions.index')
        ->assertOk()
        ->assertSee('Programme Partenaire Fayeku')
        ->assertSee('Commissions & partenariat')
        ->assertSee('Votre niveau partenaire')
        ->assertSee('Commissions du mois')
        ->assertSee('Historique des versements')
        ->assertSee('Comment fonctionne le programme partenaire');
});

// ─── Tier ─────────────────────────────────────────────────────────────────────

test('le tier Partner est affiché pour 1-4 clients', function () {
    ['user' => $user] = commTestCreateFirm(3);

    $component = Livewire::actingAs($user)
        ->test('pages::commissions.index');

    expect($component->get('tierValue'))->toBe('partner');
    expect($component->get('tierLabel'))->toBe('Partner');
});

test('le tier Gold est affiché pour 5-14 clients', function () {
    ['user' => $user] = commTestCreateFirm(7);

    $component = Livewire::actingAs($user)
        ->test('pages::commissions.index');

    expect($component->get('tierValue'))->toBe('gold');
    expect($component->get('tierLabel'))->toBe('Gold');
});

test('le tier Platinum est affiché pour 15+ clients', function () {
    ['user' => $user] = commTestCreateFirm(16);

    $component = Livewire::actingAs($user)
        ->test('pages::commissions.index');

    expect($component->get('tierValue'))->toBe('platinum');
    expect($component->get('isPlatinum'))->toBeTrue();
});

test('la progression vers Gold est calculée correctement pour un Partner', function () {
    ['user' => $user] = commTestCreateFirm(2);

    $component = Livewire::actingAs($user)
        ->test('pages::commissions.index');

    // 2 clients sur 5 → 40 %
    expect($component->get('tierProgress'))->toBe(40);
    expect($component->get('nextTierLabel'))->toBe('Gold');
    expect($component->get('nextThreshold'))->toBe(5);
});

test('isPlatinum est false pour un niveau Gold', function () {
    ['user' => $user] = commTestCreateFirm(10);

    $component = Livewire::actingAs($user)
        ->test('pages::commissions.index');

    expect($component->get('tierValue'))->toBe('gold');
    expect($component->get('isPlatinum'))->toBeFalse();
});

// ─── Commissions du mois ──────────────────────────────────────────────────────

test('les commissions du mois sont affichées', function () {
    ['user' => $user, 'firm' => $firm, 'smes' => $smes] = commTestCreateFirm(2);

    Commission::create([
        'accountant_firm_id' => $firm->id,
        'sme_company_id' => $smes[0]->id,
        'amount' => 3_000,
        'period_month' => now()->startOfMonth(),
        'status' => 'pending',
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::commissions.index');

    expect($component->get('monthTotal'))->toBe(3_000);
});

test('le cumul annuel inclut tous les mois de l\'année', function () {
    ['user' => $user, 'firm' => $firm, 'smes' => $smes] = commTestCreateFirm(1);

    Commission::create([
        'accountant_firm_id' => $firm->id,
        'sme_company_id' => $smes[0]->id,
        'amount' => 3_000,
        'period_month' => now()->startOfMonth(),
        'status' => 'pending',
    ]);
    Commission::create([
        'accountant_firm_id' => $firm->id,
        'sme_company_id' => $smes[0]->id,
        'amount' => 2_000,
        'period_month' => now()->subMonth()->startOfMonth(),
        'status' => 'paid',
        'paid_at' => now()->subDays(10),
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::commissions.index');

    expect($component->get('yearTotal'))->toBe(5_000);
});

test('les commissions de l\'année précédente ne sont pas incluses dans le cumul', function () {
    ['user' => $user, 'firm' => $firm, 'smes' => $smes] = commTestCreateFirm(1);

    Commission::create([
        'accountant_firm_id' => $firm->id,
        'sme_company_id' => $smes[0]->id,
        'amount' => 5_000,
        'period_month' => now()->startOfMonth(),
        'status' => 'paid',
        'paid_at' => now()->subDays(5),
    ]);
    Commission::create([
        'accountant_firm_id' => $firm->id,
        'sme_company_id' => $smes[0]->id,
        'amount' => 99_000,
        'period_month' => now()->subYear()->startOfMonth(),
        'status' => 'paid',
        'paid_at' => now()->subYear(),
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::commissions.index');

    expect($component->get('yearTotal'))->toBe(5_000);
});

// ─── Toggle afficher tout ─────────────────────────────────────────────────────

test('toggleShowAll bascule entre vue réduite et complète', function () {
    ['user' => $user] = commTestCreateFirm(0);

    $component = Livewire::actingAs($user)
        ->test('pages::commissions.index');

    expect($component->get('showAllCommissions'))->toBeFalse();

    $component->call('toggleShowAll');
    expect($component->get('showAllCommissions'))->toBeTrue();

    $component->call('toggleShowAll');
    expect($component->get('showAllCommissions'))->toBeFalse();
});

// ─── Historique des versements ────────────────────────────────────────────────

test('l\'historique affiche les versements passés', function () {
    ['user' => $user, 'firm' => $firm] = commTestCreateFirm(2);

    CommissionPayment::create([
        'accountant_firm_id' => $firm->id,
        'period_month' => now()->subMonth()->startOfMonth(),
        'active_clients_count' => 18,
        'amount' => 187_500,
        'paid_at' => now()->subDays(15),
        'payment_method' => 'wave',
        'status' => 'paid',
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::commissions.index');

    $payments = $component->get('payments');
    expect($payments)->toHaveCount(1);
    expect($payments->first()->amount)->toBe(187_500);
});

test('l\'historique est limité aux 12 derniers mois', function () {
    ['user' => $user, 'firm' => $firm] = commTestCreateFirm(1);

    for ($i = 1; $i <= 15; $i++) {
        CommissionPayment::create([
            'accountant_firm_id' => $firm->id,
            'period_month' => now()->subMonths($i)->startOfMonth(),
            'active_clients_count' => 3,
            'amount' => 5_000,
            'paid_at' => now()->subMonths($i),
            'payment_method' => 'wave',
            'status' => 'paid',
        ]);
    }

    $component = Livewire::actingAs($user)
        ->test('pages::commissions.index');

    expect($component->get('payments'))->toHaveCount(12);
});

// ─── Filtres ─────────────────────────────────────────────────────────────────

test('le filtre par statut "pending" retourne uniquement les commissions en attente', function () {
    ['user' => $user, 'firm' => $firm, 'smes' => $smes] = commTestCreateFirmWithSubscriptions();

    Commission::create(['accountant_firm_id' => $firm->id, 'sme_company_id' => $smes['kane_import']->id, 'amount' => 3_000, 'period_month' => now()->startOfMonth(), 'status' => 'pending']);
    Commission::create(['accountant_firm_id' => $firm->id, 'sme_company_id' => $smes['sow_btp']->id, 'amount' => 3_000, 'period_month' => now()->startOfMonth(), 'status' => 'paid', 'paid_at' => now()]);

    $component = Livewire::actingAs($user)
        ->test('pages::commissions.index')
        ->set('filterStatus', 'pending');

    expect($component->get('monthCommissions'))->toHaveCount(1);
    expect($component->get('monthCommissions')->first()->status)->toBe('pending');
});

test('le filtre par statut "paid" retourne uniquement les commissions versées', function () {
    ['user' => $user, 'firm' => $firm, 'smes' => $smes] = commTestCreateFirmWithSubscriptions();

    Commission::create(['accountant_firm_id' => $firm->id, 'sme_company_id' => $smes['kane_import']->id, 'amount' => 3_000, 'period_month' => now()->startOfMonth(), 'status' => 'pending']);
    Commission::create(['accountant_firm_id' => $firm->id, 'sme_company_id' => $smes['sow_btp']->id, 'amount' => 3_000, 'period_month' => now()->startOfMonth(), 'status' => 'paid', 'paid_at' => now()]);

    $component = Livewire::actingAs($user)
        ->test('pages::commissions.index')
        ->set('filterStatus', 'paid');

    expect($component->get('monthCommissions'))->toHaveCount(1);
    expect($component->get('monthCommissions')->first()->status)->toBe('paid');
});

test('setFilterStatus("all") vide le filtre statut', function () {
    ['user' => $user] = commTestCreateFirm(0);

    $component = Livewire::actingAs($user)
        ->test('pages::commissions.index')
        ->set('filterStatus', 'pending');

    expect($component->get('filterStatus'))->toBe('pending');

    $component->call('setFilterStatus', 'all');

    expect($component->get('filterStatus'))->toBe('');
});

test('le filtre par offre "essentiel" retourne uniquement les clients Essentiel', function () {
    ['user' => $user, 'firm' => $firm, 'smes' => $smes] = commTestCreateFirmWithSubscriptions();

    Commission::create(['accountant_firm_id' => $firm->id, 'sme_company_id' => $smes['kane_import']->id, 'amount' => 3_000, 'period_month' => now()->startOfMonth(), 'status' => 'pending']);
    Commission::create(['accountant_firm_id' => $firm->id, 'sme_company_id' => $smes['mbaye_transport']->id, 'amount' => 1_500, 'period_month' => now()->startOfMonth(), 'status' => 'pending']);

    $component = Livewire::actingAs($user)
        ->test('pages::commissions.index')
        ->set('filterPlan', 'essentiel');

    $results = $component->get('monthCommissions');
    expect($results)->toHaveCount(1);
    expect($results->first()->smeCompany->subscription->plan_slug)->toBe('essentiel');
});

test('le filtre par offre "basique" retourne uniquement les clients Basique', function () {
    ['user' => $user, 'firm' => $firm, 'smes' => $smes] = commTestCreateFirmWithSubscriptions();

    Commission::create(['accountant_firm_id' => $firm->id, 'sme_company_id' => $smes['sow_btp']->id, 'amount' => 3_000, 'period_month' => now()->startOfMonth(), 'status' => 'pending']);
    Commission::create(['accountant_firm_id' => $firm->id, 'sme_company_id' => $smes['mbaye_transport']->id, 'amount' => 1_500, 'period_month' => now()->startOfMonth(), 'status' => 'pending']);
    Commission::create(['accountant_firm_id' => $firm->id, 'sme_company_id' => $smes['coury_commerce']->id, 'amount' => 1_500, 'period_month' => now()->startOfMonth(), 'status' => 'pending']);

    $component = Livewire::actingAs($user)
        ->test('pages::commissions.index')
        ->set('filterPlan', 'basique');

    expect($component->get('monthCommissions'))->toHaveCount(2);
});

test('la recherche par nom de client filtre correctement', function () {
    ['user' => $user, 'firm' => $firm, 'smes' => $smes] = commTestCreateFirmWithSubscriptions();

    Commission::create(['accountant_firm_id' => $firm->id, 'sme_company_id' => $smes['kane_import']->id, 'amount' => 3_000, 'period_month' => now()->startOfMonth(), 'status' => 'pending']);
    Commission::create(['accountant_firm_id' => $firm->id, 'sme_company_id' => $smes['mbaye_transport']->id, 'amount' => 1_500, 'period_month' => now()->startOfMonth(), 'status' => 'pending']);

    $component = Livewire::actingAs($user)
        ->test('pages::commissions.index')
        ->set('commissionSearch', 'kane');

    expect($component->get('monthCommissions'))->toHaveCount(1);
    expect($component->get('monthCommissions')->first()->smeCompany->name)->toBe('Kane Import SARL');
});

test('la recherche est insensible à la casse', function () {
    ['user' => $user, 'firm' => $firm, 'smes' => $smes] = commTestCreateFirmWithSubscriptions();

    Commission::create(['accountant_firm_id' => $firm->id, 'sme_company_id' => $smes['kane_import']->id, 'amount' => 3_000, 'period_month' => now()->startOfMonth(), 'status' => 'pending']);

    $component = Livewire::actingAs($user)
        ->test('pages::commissions.index')
        ->set('commissionSearch', 'KANE');

    expect($component->get('monthCommissions'))->toHaveCount(1);
});

test('les filtres combinés affinent les résultats', function () {
    ['user' => $user, 'firm' => $firm, 'smes' => $smes] = commTestCreateFirmWithSubscriptions();

    Commission::create(['accountant_firm_id' => $firm->id, 'sme_company_id' => $smes['kane_import']->id, 'amount' => 3_000, 'period_month' => now()->startOfMonth(), 'status' => 'pending']);
    Commission::create(['accountant_firm_id' => $firm->id, 'sme_company_id' => $smes['sow_btp']->id, 'amount' => 3_000, 'period_month' => now()->startOfMonth(), 'status' => 'paid', 'paid_at' => now()]);
    Commission::create(['accountant_firm_id' => $firm->id, 'sme_company_id' => $smes['mbaye_transport']->id, 'amount' => 1_500, 'period_month' => now()->startOfMonth(), 'status' => 'pending']);

    $component = Livewire::actingAs($user)
        ->test('pages::commissions.index')
        ->set('filterPlan', 'essentiel')
        ->set('filterStatus', 'pending');

    expect($component->get('monthCommissions'))->toHaveCount(1);
    expect($component->get('monthCommissions')->first()->smeCompany->name)->toBe('Kane Import SARL');
});

test('resetFilters remet tous les filtres à zéro', function () {
    ['user' => $user] = commTestCreateFirm(0);

    $component = Livewire::actingAs($user)
        ->test('pages::commissions.index')
        ->set('commissionSearch', 'kane')
        ->set('filterPlan', 'essentiel')
        ->set('filterStatus', 'pending');

    expect($component->get('hasActiveFilters'))->toBeTrue();

    $component->call('resetFilters');

    expect($component->get('commissionSearch'))->toBe('');
    expect($component->get('filterPlan'))->toBe('');
    expect($component->get('filterStatus'))->toBe('');
    expect($component->get('hasActiveFilters'))->toBeFalse();
});

test('le monthTotal n\'est pas affecté par les filtres actifs', function () {
    ['user' => $user, 'firm' => $firm, 'smes' => $smes] = commTestCreateFirmWithSubscriptions();

    Commission::create(['accountant_firm_id' => $firm->id, 'sme_company_id' => $smes['kane_import']->id, 'amount' => 3_000, 'period_month' => now()->startOfMonth(), 'status' => 'pending']);
    Commission::create(['accountant_firm_id' => $firm->id, 'sme_company_id' => $smes['mbaye_transport']->id, 'amount' => 1_500, 'period_month' => now()->startOfMonth(), 'status' => 'pending']);

    $component = Livewire::actingAs($user)
        ->test('pages::commissions.index')
        ->set('filterPlan', 'essentiel');

    // Filtered table shows 1, but KPI total stays at 4 500
    expect($component->get('monthCommissions'))->toHaveCount(1);
    expect($component->get('monthTotal'))->toBe(4_500);
});

// ─── statusCounts ─────────────────────────────────────────────────────────────

test('statusCounts retourne les bons compteurs par statut', function () {
    ['user' => $user, 'firm' => $firm, 'smes' => $smes] = commTestCreateFirm(3);

    Commission::create(['accountant_firm_id' => $firm->id, 'sme_company_id' => $smes[0]->id, 'amount' => 3_000, 'period_month' => now()->startOfMonth(), 'status' => 'pending']);
    Commission::create(['accountant_firm_id' => $firm->id, 'sme_company_id' => $smes[1]->id, 'amount' => 3_000, 'period_month' => now()->startOfMonth(), 'status' => 'pending']);
    Commission::create(['accountant_firm_id' => $firm->id, 'sme_company_id' => $smes[2]->id, 'amount' => 3_000, 'period_month' => now()->startOfMonth(), 'status' => 'paid', 'paid_at' => now()]);

    $component = Livewire::actingAs($user)
        ->test('pages::commissions.index');

    $counts = $component->get('statusCounts');
    expect($counts['all'])->toBe(3);
    expect($counts['pending'])->toBe(2);
    expect($counts['paid'])->toBe(1);
});

// ─── Pont vers Invitations ────────────────────────────────────────────────────

test('pendingInvitationsCount retourne le nombre d\'invitations non activées', function () {
    ['user' => $user, 'firm' => $firm] = commTestCreateFirm(0);

    PartnerInvitation::create(['accountant_firm_id' => $firm->id, 'token' => 'tk1', 'invitee_name' => 'A', 'status' => 'pending', 'channel' => 'whatsapp']);
    PartnerInvitation::create(['accountant_firm_id' => $firm->id, 'token' => 'tk2', 'invitee_name' => 'B', 'status' => 'registering', 'channel' => 'whatsapp']);
    PartnerInvitation::create(['accountant_firm_id' => $firm->id, 'token' => 'tk3', 'invitee_name' => 'C', 'status' => 'accepted', 'accepted_at' => now(), 'channel' => 'whatsapp']);
    PartnerInvitation::create(['accountant_firm_id' => $firm->id, 'token' => 'tk4', 'invitee_name' => 'D', 'status' => 'expired', 'channel' => 'whatsapp']);

    $component = Livewire::actingAs($user)
        ->test('pages::commissions.index');

    // pending + registering = 2 ; accepted et expired sont exclus
    expect($component->get('pendingInvitationsCount'))->toBe(2);
});

test('pendingInvitationsCount vaut 0 si toutes les invitations sont activées ou expirées', function () {
    ['user' => $user, 'firm' => $firm] = commTestCreateFirm(0);

    PartnerInvitation::create(['accountant_firm_id' => $firm->id, 'token' => 'tk1', 'invitee_name' => 'A', 'status' => 'accepted', 'accepted_at' => now(), 'channel' => 'whatsapp']);
    PartnerInvitation::create(['accountant_firm_id' => $firm->id, 'token' => 'tk2', 'invitee_name' => 'B', 'status' => 'expired', 'channel' => 'whatsapp']);

    $component = Livewire::actingAs($user)
        ->test('pages::commissions.index');

    expect($component->get('pendingInvitationsCount'))->toBe(0);
});

test('la carte pont vers invitations n\'est pas visible sans invitations en attente', function () {
    ['user' => $user, 'firm' => $firm] = commTestCreateFirm(0);

    PartnerInvitation::create(['accountant_firm_id' => $firm->id, 'token' => 'tk1', 'invitee_name' => 'A', 'status' => 'accepted', 'accepted_at' => now(), 'channel' => 'whatsapp']);

    Livewire::actingAs($user)
        ->test('pages::commissions.index')
        ->assertDontSee('Invitations en attente');
});

test('la carte pont vers invitations est visible quand des invitations sont en attente', function () {
    ['user' => $user, 'firm' => $firm] = commTestCreateFirm(0);

    PartnerInvitation::create(['accountant_firm_id' => $firm->id, 'token' => 'tk1', 'invitee_name' => 'A', 'status' => 'pending', 'channel' => 'whatsapp']);

    Livewire::actingAs($user)
        ->test('pages::commissions.index')
        ->assertSee('Invitations en attente')
        ->assertSee('Voir mes invitations');
});
