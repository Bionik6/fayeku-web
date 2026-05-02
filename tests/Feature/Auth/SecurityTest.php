<?php

use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('security settings are available on the settings page', function () {
    $user = User::factory()->accountantFirm()->create();

    $this->actingAs($user)
        ->get(route('settings.index'))
        ->assertOk();
});

test('unverified users are redirected to verify-email from the settings page', function () {
    $user = User::factory()->accountantFirm()->unverified()->create();

    $this->actingAs($user)
        ->get(route('settings.index'))
        ->assertRedirect(route('auth.verify-email'));
});

test('password can be updated', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($user);

    $response = Livewire::test('pages::compta.settings.index')
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

    $response = Livewire::test('pages::compta.settings.index')
        ->call('setSection', 'account')
        ->set('currentPassword', 'wrong-password')
        ->set('newPassword', 'new-password')
        ->set('newPasswordConfirmation', 'new-password')
        ->call('updatePassword');

    $response
        ->assertHasErrors(['currentPassword'])
        ->assertSee('Le mot de passe actuel est incorrect.');
});

test('password confirmation errors are displayed in french', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($user);

    $response = Livewire::test('pages::compta.settings.index')
        ->call('setSection', 'account')
        ->set('currentPassword', 'password')
        ->set('newPassword', 'new-password')
        ->set('newPasswordConfirmation', 'different-password')
        ->call('updatePassword');

    $response
        ->assertHasErrors(['newPassword'])
        ->assertSee('La confirmation du nouveau mot de passe ne correspond pas.');
});
