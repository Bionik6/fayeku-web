<?php

use App\Models\Auth\Company;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * @return array{user: User, company: Company}
 */
function createSmeForBadge(): array
{
    $user = User::factory()->create([
        'profile_type' => 'sme',
        'first_name' => 'Moussa',
        'last_name' => 'Diop',
    ]);

    $company = Company::factory()->create([
        'type' => 'sme',
        'name' => 'Diop Services SARL',
    ]);
    $company->users()->attach($user->id, ['role' => 'owner']);

    return compact('user', 'company');
}

test('le badge affiche le nom complet et le nom de l\'entreprise depuis la session', function () {
    ['user' => $user] = createSmeForBadge();

    Livewire::actingAs($user)
        ->test('account-badge')
        ->assertSee('Moussa Diop')
        ->assertSee('Diop Services SARL');
});

test('le badge se rafraîchit après l\'event account-updated', function () {
    ['user' => $user] = createSmeForBadge();

    $component = Livewire::actingAs($user)
        ->test('account-badge')
        ->assertSee('Moussa Diop');

    $user->update(['first_name' => 'Fatou', 'last_name' => 'Sall']);

    $component->dispatch('account-updated')
        ->assertSee('Fatou Sall')
        ->assertDontSee('Moussa Diop');
});

test('le badge se rafraîchit après l\'event company-updated', function () {
    ['user' => $user, 'company' => $company] = createSmeForBadge();

    $component = Livewire::actingAs($user)
        ->test('account-badge')
        ->assertSee('Diop Services SARL');

    $company->update(['name' => 'Saatys Home & Design']);

    $component->dispatch('company-updated')
        ->assertSee('Saatys Home & Design')
        ->assertDontSee('Diop Services SARL');
});

test('le badge utilise « Mon entreprise » quand l\'utilisateur n\'a pas d\'entreprise', function () {
    $user = User::factory()->create([
        'profile_type' => 'sme',
        'first_name' => 'Aïssatou',
        'last_name' => 'Ndiaye',
    ]);

    Livewire::actingAs($user)
        ->test('account-badge')
        ->assertSee('Aïssatou Ndiaye')
        ->assertSee('Mon entreprise');
});

test('saveAccount sur la page settings émet l\'event account-updated', function () {
    ['user' => $user] = createSmeForBadge();

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->set('firstName', 'Fatou')
        ->set('lastName', 'Sall')
        ->call('saveAccount')
        ->assertDispatched('account-updated');
});

test('saveCompanyProfile sur la page settings émet l\'event company-updated', function () {
    ['user' => $user, 'company' => $company] = createSmeForBadge();

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->set('firmName', 'Saatys Home & Design')
        ->call('saveCompanyProfile')
        ->assertDispatched('company-updated');
});
