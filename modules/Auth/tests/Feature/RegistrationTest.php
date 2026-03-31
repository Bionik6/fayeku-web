<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\Company;
use Modules\Auth\Models\Subscription;
use Modules\Shared\Models\User;

uses(RefreshDatabase::class);

test('registration page can be rendered', function () {
    $this->get(route('auth.register'))
        ->assertOk();
});

test('user can register as sme', function () {
    $response = $this->post(route('auth.register.submit'), [
        'first_name' => 'Amadou',
        'last_name' => 'Diallo',
        'phone' => '771234567',
        'password' => 'P@ssword123!',
        'password_confirmation' => 'P@ssword123!',
        'profile_type' => 'sme',
        'country_code' => 'SN',
    ]);

    $response->assertRedirect(route('auth.otp'));
    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
        'phone' => '+221771234567',
        'profile_type' => 'sme',
    ]);
    expect(Company::count())->toBe(1);
    expect(Company::first()->name)->toBe('Amadou Diallo');
    expect(Company::first()->setup_completed_at)->toBeNull();
    expect(Subscription::count())->toBe(1);
});

test('user can register as accountant firm', function () {
    $response = $this->post(route('auth.register.submit'), [
        'first_name' => 'Fatou',
        'last_name' => 'Sow',
        'phone' => '781234567',
        'password' => 'P@ssword123!',
        'password_confirmation' => 'P@ssword123!',
        'profile_type' => 'accountant_firm',
        'country_code' => 'CI',
    ]);

    $response->assertRedirect(route('auth.otp'));
    $this->assertDatabaseHas('users', [
        'phone' => '+225781234567',
        'profile_type' => 'accountant_firm',
    ]);
});

test('registration fails with missing fields', function () {
    $response = $this->post(route('auth.register.submit'), []);

    $response->assertSessionHasErrors([
        'first_name', 'last_name', 'phone', 'password',
        'profile_type', 'country_code',
    ]);
});

test('registration fails with duplicate phone', function () {
    User::factory()->create(['phone' => '+221771234567']);

    $response = $this->post(route('auth.register.submit'), [
        'first_name' => 'Amadou',
        'last_name' => 'Diallo',
        'phone' => '771234567',
        'password' => 'P@ssword123!',
        'password_confirmation' => 'P@ssword123!',
        'profile_type' => 'sme',
        'country_code' => 'SN',
    ]);

    $response->assertSessionHasErrors('phone');
});

test('api registration returns json with token', function () {
    $response = $this->postJson(route('api.auth.register'), [
        'first_name' => 'Amadou',
        'last_name' => 'Diallo',
        'phone' => '771234567',
        'password' => 'P@ssword123!',
        'password_confirmation' => 'P@ssword123!',
        'profile_type' => 'sme',
        'country_code' => 'SN',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['message', 'user', 'token']);
});
