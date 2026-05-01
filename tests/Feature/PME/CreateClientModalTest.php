<?php

use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

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

test('save accepte un téléphone vide quand un email est fourni (téléphone optionnel)', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForModal();

    Livewire::actingAs($user)
        ->test('create-client-modal', ['company' => $company])
        ->dispatch('open-create-client-modal')
        ->set('clientName', 'Sow Import')
        ->set('clientPhone', '')
        ->set('clientEmail', 'contact@sow-import.sn')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showModal', false)
        ->assertSet('confirmNoContactId', null);

    $client = Client::query()->where('company_id', $company->id)->first();
    expect($client->phone)->toBeNull()
        ->and($client->email)->toBe('contact@sow-import.sn');
});

test('save accepte un email vide quand un téléphone est fourni', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForModal();

    Livewire::actingAs($user)
        ->test('create-client-modal', ['company' => $company])
        ->dispatch('open-create-client-modal')
        ->set('clientName', 'Diop Logistics')
        ->set('clientPhone', '+221771234567')
        ->set('clientEmail', '')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showModal', false);

    $client = Client::query()->where('company_id', $company->id)->first();
    expect($client->phone)->toBe('+221771234567')
        ->and($client->email)->toBeNull();
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

// ─── Popup de confirmation : client sans contact ─────────────────────────────

test('save ouvre la popup de confirmation quand téléphone ET email sont vides', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForModal();

    Livewire::actingAs($user)
        ->test('create-client-modal', ['company' => $company])
        ->dispatch('open-create-client-modal')
        ->set('clientName', 'Client Sans Contact')
        ->set('clientPhone', '')
        ->set('clientEmail', '')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('confirmNoContactId', 'pending')
        ->assertSet('showModal', true); // modale principale toujours ouverte derrière

    expect(Client::query()->where('company_id', $company->id)->count())->toBe(0);
});

test('cancelNoContact ferme la popup sans créer le client', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForModal();

    Livewire::actingAs($user)
        ->test('create-client-modal', ['company' => $company])
        ->dispatch('open-create-client-modal')
        ->set('clientName', 'Client Sans Contact')
        ->call('save') // ouvre la popup
        ->assertSet('confirmNoContactId', 'pending')
        ->call('cancelNoContact')
        ->assertSet('confirmNoContactId', null);

    expect(Client::query()->where('company_id', $company->id)->count())->toBe(0);
});

test('confirmCreateWithoutContact crée le client sans contact et dispatch client-created', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForModal();

    Livewire::actingAs($user)
        ->test('create-client-modal', ['company' => $company])
        ->dispatch('open-create-client-modal')
        ->set('clientName', 'Client Sans Contact')
        ->call('save')
        ->assertSet('confirmNoContactId', 'pending')
        ->call('confirmCreateWithoutContact', 'pending')
        ->assertSet('confirmNoContactId', null)
        ->assertSet('showModal', false)
        ->assertDispatched('client-created');

    $client = Client::query()->where('company_id', $company->id)->first();
    expect($client)->not->toBeNull()
        ->and($client->name)->toBe('Client Sans Contact')
        ->and($client->phone)->toBeNull()
        ->and($client->email)->toBeNull()
        ->and($client->hasContact())->toBeFalse();
});

test('save sans contact ne déclenche pas la popup s\'il y a une erreur de validation sur le nom', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForModal();

    Livewire::actingAs($user)
        ->test('create-client-modal', ['company' => $company])
        ->dispatch('open-create-client-modal')
        ->set('clientName', '')
        ->set('clientPhone', '')
        ->set('clientEmail', '')
        ->call('save')
        ->assertHasErrors(['clientName'])
        ->assertSet('confirmNoContactId', null);
});
