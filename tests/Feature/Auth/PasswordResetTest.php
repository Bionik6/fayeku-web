<?php

use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('sme forgot password page can be rendered', function () {
    $this->get(route('sme.auth.forgot-password'))
        ->assertOk();
});

test('sme user can request password reset otp', function () {
    User::factory()->create(['phone' => '+221771234567']);

    $response = $this->post(route('sme.auth.forgot-password.submit'), [
        'phone' => '771234567',
        'country_code' => 'SN',
    ]);

    $response->assertRedirect(route('sme.auth.reset-password'));
    $this->assertDatabaseHas('otp_codes', [
        'phone' => '+221771234567',
        'purpose' => 'password_reset',
    ]);
});

test('sme reset password page can be rendered with session', function () {
    $response = $this->withSession(['reset_phone' => '+221771234567'])
        ->get(route('sme.auth.reset-password'));

    $response->assertOk();
});

test('sme reset password page redirects without session', function () {
    $this->get(route('sme.auth.reset-password'))
        ->assertRedirect(route('sme.auth.forgot-password'));
});

test('sme user can reset password with valid otp', function () {
    $user = User::factory()->create(['phone' => '+221771234567', 'profile_type' => 'sme']);

    DB::table('otp_codes')->insert([
        'id' => (string) Str::ulid(),
        'phone' => '+221771234567',
        'code' => hash('sha256', '123456'),
        'purpose' => 'password_reset',
        'expires_at' => now()->addMinutes(10),
        'attempts' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->withSession(['reset_phone' => '+221771234567'])
        ->post(route('sme.auth.reset-password.submit'), [
            'code' => '123456',
            'password' => 'NewP@ssword123!',
            'password_confirmation' => 'NewP@ssword123!',
        ]);

    $response->assertRedirect(route('pme.dashboard'));
    $this->assertAuthenticated();
    expect(Hash::check('NewP@ssword123!', $user->fresh()->password))->toBeTrue();
});

test('sme password reset fails with invalid otp', function () {
    User::factory()->create(['phone' => '+221771234567']);

    $response = $this->withSession(['reset_phone' => '+221771234567'])
        ->post(route('sme.auth.reset-password.submit'), [
            'code' => '999999',
            'password' => 'NewP@ssword123!',
            'password_confirmation' => 'NewP@ssword123!',
        ]);

    $response->assertSessionHasErrors('code');
});

test('api password reset returns json', function () {
    User::factory()->create(['phone' => '+221771234567']);

    DB::table('otp_codes')->insert([
        'id' => (string) Str::ulid(),
        'phone' => '+221771234567',
        'code' => hash('sha256', '123456'),
        'purpose' => 'password_reset',
        'expires_at' => now()->addMinutes(10),
        'attempts' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->postJson(route('api.auth.reset-password'), [
        'phone' => '+221771234567',
        'code' => '123456',
        'password' => 'NewP@ssword123!',
        'password_confirmation' => 'NewP@ssword123!',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['message', 'token']);
});
