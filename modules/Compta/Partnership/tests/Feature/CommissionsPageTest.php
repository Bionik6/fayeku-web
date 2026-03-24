<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Auth\Models\AccountantCompany;
use Modules\Auth\Models\Company;
use Modules\Compta\Partnership\Models\Commission;
use Modules\Compta\Partnership\Models\CommissionPayment;
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
        ->assertSee('Votre niveau partenaire')
        ->assertSee('Commissions du mois')
        ->assertSee('Historique des versements');
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

// ─── Commissions du mois ──────────────────────────────────────────────────────

test('les commissions du mois sont affichées', function () {
    ['user' => $user, 'firm' => $firm, 'smes' => $smes] = commTestCreateFirm(2);

    Commission::create([
        'accountant_firm_id' => $firm->id,
        'sme_company_id' => $smes[0]->id,
        'amount' => 3000,
        'period_month' => now()->startOfMonth(),
        'status' => 'pending',
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::commissions.index');

    expect($component->get('monthTotal'))->toBe(3000);
});

test('le cumul annuel inclut tous les mois', function () {
    ['user' => $user, 'firm' => $firm, 'smes' => $smes] = commTestCreateFirm(1);

    Commission::create([
        'accountant_firm_id' => $firm->id,
        'sme_company_id' => $smes[0]->id,
        'amount' => 3000,
        'period_month' => now()->startOfMonth(),
        'status' => 'pending',
    ]);
    Commission::create([
        'accountant_firm_id' => $firm->id,
        'sme_company_id' => $smes[0]->id,
        'amount' => 2000,
        'period_month' => now()->subMonth()->startOfMonth(),
        'status' => 'paid',
        'paid_at' => now()->subDays(10),
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::commissions.index');

    expect($component->get('yearTotal'))->toBe(5000);
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
        'amount' => 187500,
        'paid_at' => now()->subDays(15),
        'payment_method' => 'wave',
        'status' => 'paid',
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::commissions.index');

    $payments = $component->get('payments');
    expect($payments)->toHaveCount(1);
    expect($payments->first()->amount)->toBe(187500);
});
