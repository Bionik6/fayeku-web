<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Modules\Shared\Models\User;

uses(RefreshDatabase::class);

test('security settings are available on the settings page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.index'))
        ->assertOk();
});

test('unverified users are redirected to otp from the settings page', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('settings.index'))
        ->assertRedirect(route('auth.otp'));
});

test('password can be updated', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.index')
        ->call('setSection', 'account')
        ->set('currentPassword', 'password')
        ->set('newPassword', 'new-password')
        ->set('newPasswordConfirmation', 'new-password')
        ->call('updatePassword');

    $response->assertHasNoErrors();

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.index')
        ->call('setSection', 'account')
        ->set('currentPassword', 'wrong-password')
        ->set('newPassword', 'new-password')
        ->set('newPasswordConfirmation', 'new-password')
        ->call('updatePassword');

    $response->assertHasErrors(['currentPassword']);
});
