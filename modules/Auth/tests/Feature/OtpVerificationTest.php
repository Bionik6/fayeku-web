<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Shared\Models\User;

uses(RefreshDatabase::class);

function createOtpCode(string $phone, string $code, string $purpose = 'verification', ?string $expiresAt = null): void
{
    DB::table('otp_codes')->insert([
        'id' => (string) Str::ulid(),
        'phone' => $phone,
        'code' => hash('sha256', $code),
        'purpose' => $purpose,
        'expires_at' => $expiresAt ?? now()->addMinutes(10),
        'attempts' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('otp page can be rendered for authenticated user', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->withSession(['otp_phone' => $user->phone])
        ->get(route('auth.otp'))
        ->assertOk();
});

test('otp page redirects to login if no phone in session', function () {
    $this->get(route('auth.otp'))
        ->assertRedirect(route('login'));
});

test('valid otp code verifies phone', function () {
    $user = User::factory()->unverified()->create(['phone' => '+221771234567']);
    createOtpCode('+221771234567', '123456');

    $response = $this->actingAs($user)
        ->withSession(['otp_phone' => '+221771234567'])
        ->post(route('auth.otp.verify'), ['code' => '123456']);

    $response->assertRedirect(route('dashboard'));
    expect($user->fresh()->phone_verified_at)->not->toBeNull();
});

test('invalid otp code is rejected', function () {
    $user = User::factory()->unverified()->create(['phone' => '+221771234567']);
    createOtpCode('+221771234567', '123456');

    $response = $this->actingAs($user)
        ->withSession(['otp_phone' => '+221771234567'])
        ->post(route('auth.otp.verify'), ['code' => '999999']);

    $response->assertSessionHasErrors('code');
});

test('expired otp code is rejected', function () {
    $user = User::factory()->unverified()->create(['phone' => '+221771234567']);
    createOtpCode('+221771234567', '123456', 'verification', now()->subMinutes(15)->toDateTimeString());

    $response = $this->actingAs($user)
        ->withSession(['otp_phone' => '+221771234567'])
        ->post(route('auth.otp.verify'), ['code' => '123456']);

    $response->assertSessionHasErrors('code');
});

test('otp resend works', function () {
    $user = User::factory()->unverified()->create(['phone' => '+221771234567']);

    $response = $this->actingAs($user)
        ->withSession(['otp_phone' => '+221771234567'])
        ->post(route('auth.otp.resend'));

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
