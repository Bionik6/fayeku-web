<?php

use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('otp page can be rendered for authenticated user', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->withSession(['otp_phone' => $user->phone])
        ->get(route('sme.auth.otp'))
        ->assertOk();
});

test('otp page redirects to login if no phone in session', function () {
    $this->get(route('sme.auth.otp'))
        ->assertRedirect(route('login'));
});

test('valid otp code verifies phone and redirects sme to pme dashboard', function () {
    $user = User::factory()->unverified()->create(['phone' => '+221771234567', 'profile_type' => 'sme']);
    createOtpCode('+221771234567', '123456');

    $response = $this->actingAs($user)
        ->withSession(['otp_phone' => '+221771234567'])
        ->post(route('sme.auth.otp.verify'), ['code' => '123456']);

    $response->assertRedirect(route('pme.dashboard'));
    expect($user->fresh()->phone_verified_at)->not->toBeNull();
});

test('valid otp code verifies phone and redirects accountant to compta dashboard', function () {
    $user = User::factory()->accountantFirm()->unverified()->create(['phone' => '+221771234567']);
    createOtpCode('+221771234567', '123456');

    $response = $this->actingAs($user)
        ->withSession(['otp_phone' => '+221771234567'])
        ->post(route('sme.auth.otp.verify'), ['code' => '123456']);

    $response->assertRedirect(route('dashboard'));
    expect($user->fresh()->phone_verified_at)->not->toBeNull();
});

test('invalid otp code is rejected', function () {
    $user = User::factory()->unverified()->create(['phone' => '+221771234567']);
    createOtpCode('+221771234567', '123456');

    $response = $this->actingAs($user)
        ->withSession(['otp_phone' => '+221771234567'])
        ->post(route('sme.auth.otp.verify'), ['code' => '999999']);

    $response->assertSessionHasErrors('code');
});

test('expired otp code is rejected', function () {
    $user = User::factory()->unverified()->create(['phone' => '+221771234567']);
    createOtpCode('+221771234567', '123456', 'verification', now()->subMinutes(15)->toDateTimeString());

    $response = $this->actingAs($user)
        ->withSession(['otp_phone' => '+221771234567'])
        ->post(route('sme.auth.otp.verify'), ['code' => '123456']);

    $response->assertSessionHasErrors('code');
});

test('otp resend works', function () {
    $user = User::factory()->unverified()->create(['phone' => '+221771234567']);

    $response = $this->actingAs($user)
        ->withSession(['otp_phone' => '+221771234567'])
        ->post(route('sme.auth.otp.resend'));

    $response->assertSessionHas('status');
    $this->assertDatabaseHas('otp_codes', ['phone' => '+221771234567']);
});

test('api otp verification returns json', function () {
    $user = User::factory()->unverified()->create(['phone' => '+221771234567']);
    createOtpCode('+221771234567', '123456');
    $token = $user->createToken('auth')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson(route('api.auth.otp.verify'), ['code' => '123456']);

    $response->assertOk()
        ->assertJson(['message' => 'Téléphone vérifié avec succès.']);
});
