<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use App\Models\Auth\Company;
use App\Models\Shared\User;

uses(RefreshDatabase::class);

test('settings page is displayed', function () {
    $this->actingAs($user = User::factory()->accountantFirm()->create());

    $this->get(route('settings.index'))
        ->assertOk()
        ->assertSee('Profil du cabinet')
        ->assertSee('Compte & sécurité')
        ->assertSee('Sections des paramètres', false)
        ->assertSee("wire:click=\"setSection('profile')\"", false)
        ->assertSee("wire:click=\"setSection('account')\"", false);
});

test('profile information can be updated', function () {
    $user = User::factory()->accountantFirm()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::compta.settings.index')
        ->set('firstName', 'Test')
        ->set('lastName', 'User')
        ->call('saveAccount');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->first_name)->toEqual('Test');
    expect($user->last_name)->toEqual('User');
    expect($user->full_name)->toEqual('Test User');
});

test('phone number remains unchanged when profile information is updated', function () {
    $user = User::factory()->accountantFirm()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::compta.settings.index')
        ->set('firstName', 'Updated')
        ->set('lastName', 'Name')
        ->call('saveAccount');

    $response->assertHasNoErrors();

    expect($user->refresh()->phone)->toEqual($user->phone);
});

test('settings page uses the shared phone component for cabinet and account phone fields', function () {
    $user = User::factory()->accountantFirm()->create([
        'phone' => '+221771234567',
        'country_code' => 'SN',
    ]);
    $firm = Company::factory()->accountantFirm()->create([
        'phone' => '+221338001122',
        'country_code' => 'SN',
    ]);
    $firm->users()->attach($user->id, ['role' => 'owner']);

    $this->actingAs($user)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertSee('Téléphone du cabinet')
        ->assertSee('SEN (+221)')
        ->assertSee('33 800 11 22')
        ->assertSee('data-phone-field', false);

    Livewire::actingAs($user)
        ->test('pages::compta.settings.index')
        ->call('setSection', 'account')
        ->assertSee('Téléphone')
        ->assertSee('77 123 45 67')
        ->assertSee('data-phone-field', false);
});

test('cabinet profile can be updated from the settings page', function () {
    $user = User::factory()->accountantFirm()->create();
    $firm = Company::factory()->accountantFirm()->create([
        'name' => 'Cabinet Initial',
        'phone' => '+221330000000',
        'country_code' => 'SN',
    ]);
    $firm->users()->attach($user->id, ['role' => 'owner']);

    $response = Livewire::actingAs($user)
        ->test('pages::compta.settings.index')
        ->set('firmName', 'Cabinet Fayeku Conseil')
        ->set('firmPhone', '33 822 01 00')
        ->set('firmCountry', 'SN')
        ->set('firmCity', 'Dakar')
        ->call('saveFirmProfile');

    $response->assertHasNoErrors();

    expect($firm->refresh()->name)->toEqual('Cabinet Fayeku Conseil');
    expect($firm->phone)->toEqual('+221338220100');
    expect($firm->country_code)->toEqual('SN');
    expect($firm->city)->toEqual('Dakar');
});

test('user can delete their account', function () {
    $user = User::factory()->accountantFirm()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::compta.settings.delete-user-modal')
        ->set('password', 'password')
        ->call('deleteUser');

    $response
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect($user->fresh())->toBeNull();
    expect(auth()->check())->toBeFalse();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->accountantFirm()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::compta.settings.delete-user-modal')
        ->set('password', 'wrong-password')
        ->call('deleteUser');

    $response->assertHasErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});
