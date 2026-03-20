<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Shared\Models\User;

uses(RefreshDatabase::class);

test('login page can be rendered', function () {
    $this->get(route('login'))
        ->assertOk();
});

test('user can login with correct credentials', function () {
    $user = User::factory()->create([
        'phone' => '+221771234567',
    ]);

    $response = $this->post(route('auth.login.submit'), [
        'phone' => '771234567',
        'password' => 'password',
        'country_code' => 'SN',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('user cannot login with wrong password', function () {
    User::factory()->create([
        'phone' => '+221771234567',
    ]);

    $response = $this->post(route('auth.login.submit'), [
        'phone' => '771234567',
        'password' => 'wrong-password',
        'country_code' => 'SN',
    ]);

    $response->assertSessionHasErrors('phone');
    $this->assertGuest();
});

test('user cannot login with non-existent phone', function () {
    $response = $this->post(route('auth.login.submit'), [
        'phone' => '999999999',
        'password' => 'password',
        'country_code' => 'SN',
    ]);

    $response->assertSessionHasErrors('phone');
    $this->assertGuest();
});

test('inactive user cannot login', function () {
    User::factory()->inactive()->create([
        'phone' => '+221771234567',
    ]);

    $response = $this->post(route('auth.login.submit'), [
        'phone' => '771234567',
        'password' => 'password',
        'country_code' => 'SN',
    ]);

    $response->assertSessionHasErrors('phone');
    $this->assertGuest();
});

test('unverified user is redirected to otp page', function () {
    User::factory()->unverified()->create([
        'phone' => '+221771234567',
    ]);

    $response = $this->post(route('auth.login.submit'), [
        'phone' => '771234567',
        'password' => 'password',
        'country_code' => 'SN',
    ]);

    $response->assertRedirect(route('auth.otp'));
    $this->assertAuthenticated();
});

test('api login returns json with token', function () {
    User::factory()->create([
        'phone' => '+221771234567',
    ]);

    $response = $this->postJson(route('api.auth.login'), [
        'phone' => '771234567',
        'password' => 'password',
        'country_code' => 'SN',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['message', 'user', 'token', 'phone_verified']);
});

test('api login fails with wrong credentials', function () {
    User::factory()->create([
        'phone' => '+221771234567',
    ]);

    $response = $this->postJson(route('api.auth.login'), [
        'phone' => '771234567',
        'password' => 'wrong',
        'country_code' => 'SN',
    ]);

    $response->assertUnauthorized();
});
