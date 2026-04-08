<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Auth\Models\Company;
use Modules\PME\Clients\Models\Client;
use Modules\Shared\Models\User;

uses(RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function createSmeUserForModal(): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme']);
    $company->users()->attach($user->id, ['role' => 'owner']);

    return compact('user', 'company');
}

// ─── Mount ───────────────────────────────────────────────────────────────────

test('clientPhoneCountry est initialisé au code pays de la compagnie', function () {
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme', 'country_code' => 'CI']);
    $company->users()->attach($user->id, ['role' => 'owner']);

    Livewire::actingAs($user)
        ->test('create-client-modal', ['company' => $company])
        ->assertSet('clientPhoneCountry', 'CI');
});

// ─── openModal ───────────────────────────────────────────────────────────────

test('open-create-client-modal ouvre la modale', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForModal();

    Livewire::actingAs($user)
        ->test('create-client-modal', ['company' => $company])
        ->dispatch('open-create-client-modal')
        ->assertSet('showModal', true);
});

test('open-create-client-modal réinitialise le formulaire', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForModal();

    Livewire::actingAs($user)
        ->test('create-client-modal', ['company' => $company])
        ->set('clientPhone', '+221771234567')
        ->set('clientPhoneCountry', 'CI')
        ->dispatch('open-create-client-modal')
        ->assertSet('clientPhone', '')
        ->assertSet('clientPhoneCountry', $company->country_code ?? 'SN');
});

// ─── save ────────────────────────────────────────────────────────────────────

test('save crée le client et dispatch client-created', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForModal();

    Livewire::actingAs($user)
        ->test('create-client-modal', ['company' => $company])
        ->dispatch('open-create-client-modal')
        ->set('clientName', 'Dakar Pharma')
        ->set('clientPhone', '+221771234567')
        ->set('clientEmail', 'contact@dakarpharma.sn')
        ->call('save')
        ->assertSet('showModal', false)
        ->assertDispatched('client-created');

    expect(Client::query()->where('company_id', $company->id)->where('name', 'Dakar Pharma')->first())->not->toBeNull();
});

test('save normalise un numéro SN sans préfixe', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForModal();

    Livewire::actingAs($user)
        ->test('create-client-modal', ['company' => $company])
        ->dispatch('open-create-client-modal')
        ->set('clientName', 'Diallo Tech')
        ->set('clientPhoneCountry', 'SN')
        ->set('clientPhone', '771234567')
        ->call('save')
        ->assertHasNoErrors();

    expect(Client::query()->where('company_id', $company->id)->first()->phone)
        ->toBe('+221771234567');
});

test('save normalise un numéro CI sans préfixe', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForModal();

    Livewire::actingAs($user)
        ->test('create-client-modal', ['company' => $company])
        ->dispatch('open-create-client-modal')
        ->set('clientName', 'Kouassi BTP')
        ->set('clientPhoneCountry', 'CI')
        ->set('clientPhone', '0712345678')
        ->call('save')
        ->assertHasNoErrors();

    expect(Client::query()->where('company_id', $company->id)->first()->phone)
        ->toBe('+2250712345678');
});

test('save conserve un numéro déjà internationalisé', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForModal();

    Livewire::actingAs($user)
        ->test('create-client-modal', ['company' => $company])
        ->dispatch('open-create-client-modal')
        ->set('clientName', 'Fall Logistics')
        ->set('clientPhoneCountry', 'SN')
        ->set('clientPhone', '+221771234567')
        ->call('save')
        ->assertHasNoErrors();

    expect(Client::query()->where('company_id', $company->id)->first()->phone)
        ->toBe('+221771234567');
});

test('save échoue si clientPhone est vide', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForModal();

    Livewire::actingAs($user)
        ->test('create-client-modal', ['company' => $company])
        ->dispatch('open-create-client-modal')
        ->set('clientName', 'Sow Import')
        ->set('clientPhone', '')
        ->call('save')
        ->assertHasErrors(['clientPhone']);
});

test('save échoue si clientName est vide', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForModal();

    Livewire::actingAs($user)
        ->test('create-client-modal', ['company' => $company])
        ->dispatch('open-create-client-modal')
        ->set('clientName', '')
        ->set('clientPhone', '+221771234567')
        ->call('save')
        ->assertHasErrors(['clientName']);
});
