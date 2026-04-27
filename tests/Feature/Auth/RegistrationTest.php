<?php

use App\Models\Auth\Company;
use App\Models\Auth\Subscription;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('sme registration page can be rendered', function () {
    $this->get(route('sme.auth.register'))
        ->assertOk();
});

test('user can register as sme', function () {
    $response = $this->post(route('sme.auth.register.submit'), [
        'first_name' => 'Amadou',
        'last_name' => 'Diallo',
        'phone' => '771234567',
        'password' => 'P@ssword123!',
        'password_confirmation' => 'P@ssword123!',
        'country_code' => 'SN',
    ]);

    $response->assertRedirect(route('sme.auth.otp'));
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

test('sme registration ignores attempt to set accountant_firm profile type', function () {
    $this->post(route('sme.auth.register.submit'), [
        'first_name' => 'Fatou',
        'last_name' => 'Sow',
        'phone' => '781234567',
        'password' => 'P@ssword123!',
        'password_confirmation' => 'P@ssword123!',
        'profile_type' => 'accountant_firm',
        'country_code' => 'CI',
    ]);

    $this->assertDatabaseHas('users', [
        'phone' => '+225781234567',
        'profile_type' => 'sme',
    ]);
    $this->assertDatabaseMissing('users', [
        'profile_type' => 'accountant_firm',
    ]);
});

test('registration fails with missing fields', function () {
    $response = $this->post(route('sme.auth.register.submit'), []);

    $response->assertSessionHasErrors([
        'first_name', 'last_name', 'phone', 'password', 'country_code',
    ]);
});

test('registration fails with duplicate phone', function () {
    User::factory()->create(['phone' => '+221771234567']);

    $response = $this->post(route('sme.auth.register.submit'), [
        'first_name' => 'Amadou',
        'last_name' => 'Diallo',
        'phone' => '771234567',
        'password' => 'P@ssword123!',
        'password_confirmation' => 'P@ssword123!',
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
        'country_code' => 'SN',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['message', 'user', 'token']);
});
