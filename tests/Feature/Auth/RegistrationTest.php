<?php

use App\Models\Auth\Company;
use App\Models\Auth\Subscription;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('sme registration page can be rendered', function () {
    $this->get(route('register'))->assertOk();
});

test('user can register as sme with email and phone', function () {
    $response = $this->post(route('register.submit'), [
        'first_name' => 'Amadou',
        'last_name' => 'Diallo',
        'email' => 'amadou@example.com',
        'phone' => '771234567',
        'password' => 'P@ssword123!',
        'password_confirmation' => 'P@ssword123!',
        'country_code' => 'SN',
    ]);

    $response->assertRedirect(route('auth.verify-email'));
    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
        'phone' => '+221771234567',
        'email' => 'amadou@example.com',
        'profile_type' => 'sme',
    ]);
    expect(Company::count())->toBe(1);
    expect(Company::first()->name)->toBe('Amadou Diallo');
    expect(Company::first()->setup_completed_at)->toBeNull();
    expect(Subscription::count())->toBe(1);
});

test('sme registration ignores attempt to set accountant_firm profile type', function () {
    $this->post(route('register.submit'), [
        'first_name' => 'Fatou',
        'last_name' => 'Sow',
        'email' => 'fatou@example.com',
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
    $response = $this->post(route('register.submit'), []);

    $response->assertSessionHasErrors([
        'first_name', 'last_name', 'email', 'phone', 'password', 'country_code',
    ]);
});

test('registration fails with duplicate phone', function () {
    User::factory()->create([
        'phone' => '+221771234567',
        'email' => 'existing@example.com',
    ]);

    $response = $this->post(route('register.submit'), [
        'first_name' => 'Amadou',
        'last_name' => 'Diallo',
        'email' => 'new@example.com',
        'phone' => '771234567',
        'password' => 'P@ssword123!',
        'password_confirmation' => 'P@ssword123!',
        'country_code' => 'SN',
    ]);

    $response->assertSessionHasErrors('phone');
});

test('registration fails with duplicate email', function () {
    User::factory()->create([
        'phone' => '+221770000000',
        'email' => 'taken@example.com',
    ]);

    $response = $this->post(route('register.submit'), [
        'first_name' => 'Amadou',
        'last_name' => 'Diallo',
        'email' => 'taken@example.com',
        'phone' => '771234567',
        'password' => 'P@ssword123!',
        'password_confirmation' => 'P@ssword123!',
        'country_code' => 'SN',
    ]);

    $response->assertSessionHasErrors('email');
});

test('registration normalizes email to lowercase', function () {
    $this->post(route('register.submit'), [
        'first_name' => 'Amadou',
        'last_name' => 'Diallo',
        'email' => 'Amadou@Example.COM',
        'phone' => '771234567',
        'password' => 'P@ssword123!',
        'password_confirmation' => 'P@ssword123!',
        'country_code' => 'SN',
    ])->assertRedirect(route('auth.verify-email'));

    $this->assertDatabaseHas('users', [
        'email' => 'amadou@example.com',
    ]);
});

test('registration generates an email-verification otp keyed by the new email', function () {
    $this->post(route('register.submit'), [
        'first_name' => 'Amadou',
        'last_name' => 'Diallo',
        'email' => 'amadou@example.com',
        'phone' => '771234567',
        'password' => 'P@ssword123!',
        'password_confirmation' => 'P@ssword123!',
        'country_code' => 'SN',
    ]);

    $this->assertDatabaseHas('otp_codes', [
        'identifier' => 'amadou@example.com',
        'purpose' => 'email_verification',
    ]);
});

test('api registration returns json with token', function () {
    $response = $this->postJson(route('api.auth.register'), [
        'first_name' => 'Amadou',
        'last_name' => 'Diallo',
        'email' => 'amadou@example.com',
        'phone' => '771234567',
        'password' => 'P@ssword123!',
        'password_confirmation' => 'P@ssword123!',
        'country_code' => 'SN',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['message', 'user', 'token']);
});
