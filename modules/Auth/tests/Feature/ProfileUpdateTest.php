<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Auth\Models\Company;
use Modules\Shared\Models\User;

uses(RefreshDatabase::class);

test('settings page is displayed', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get(route('settings.index'))->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.index')
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
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.index')
        ->set('firstName', 'Updated')
        ->set('lastName', 'Name')
        ->call('saveAccount');

    $response->assertHasNoErrors();

    expect($user->refresh()->phone)->toEqual($user->phone);
});

test('settings page uses the shared phone component for cabinet and account phone fields', function () {
    $user = User::factory()->create([
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
        ->assertSee('+221338001122')
        ->assertSee('data-phone-field', false);

    Livewire::actingAs($user)
        ->test('pages::settings.index')
        ->call('setSection', 'account')
        ->assertSee('Téléphone')
        ->assertSee('+221771234567')
        ->assertSee('data-phone-field', false);
});

test('cabinet profile can be updated from the settings page', function () {
    $user = User::factory()->create();
    $firm = Company::factory()->accountantFirm()->create([
        'name' => 'Cabinet Initial',
        'phone' => '+221330000000',
        'country_code' => 'SN',
    ]);
    $firm->users()->attach($user->id, ['role' => 'owner']);

    $response = Livewire::actingAs($user)
        ->test('pages::settings.index')
        ->set('firmName', 'Cabinet Fayeku Conseil')
        ->set('firmPhone', '+2250700000000')
        ->set('firmCountry', 'CI')
        ->set('firmCity', 'Abidjan')
        ->call('saveFirmProfile');

    $response->assertHasNoErrors();

    expect($firm->refresh()->name)->toEqual('Cabinet Fayeku Conseil');
    expect($firm->phone)->toEqual('+2250700000000');
    expect($firm->country_code)->toEqual('CI');
    expect($firm->city)->toEqual('Abidjan');
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.delete-user-modal')
        ->set('password', 'password')
        ->call('deleteUser');

    $response
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect($user->fresh())->toBeNull();
    expect(auth()->check())->toBeFalse();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.delete-user-modal')
        ->set('password', 'wrong-password')
        ->call('deleteUser');

    $response->assertHasErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});
