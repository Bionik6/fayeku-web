<?php

use App\Models\Auth\Company;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('verify-email page renders for an authenticated user with email in session', function () {
    $user = User::factory()->unverified()->create(['email' => 'sme@example.com']);

    $this->actingAs($user)
        ->withSession(['verification_email' => $user->email])
        ->get(route('auth.verify-email'))
        ->assertOk()
        ->assertSee('Code de vérification');
});

test('verify-email page redirects to login when no session and no auth user', function () {
    $this->get(route('auth.verify-email'))
        ->assertRedirect(route('login'));
});

test('valid code marks email_verified_at and redirects sme to company-setup when company not set up', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'sme@example.com',
        'profile_type' => 'sme',
    ]);
    $company = Company::factory()->create(['type' => 'sme', 'setup_completed_at' => null]);
    $user->companies()->attach($company->id, ['role' => 'owner']);
    createOtpCode('sme@example.com', '123456');

    $response = $this->actingAs($user)
        ->withSession(['verification_email' => 'sme@example.com'])
        ->post(route('auth.verify-email.verify'), ['code' => '123456']);

    $response->assertRedirect(route('auth.company-setup'));
    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

test('valid code redirects sme directly to pme dashboard when no company yet (defensive)', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'sme@example.com',
        'profile_type' => 'sme',
    ]);
    createOtpCode('sme@example.com', '123456');

    $response = $this->actingAs($user)
        ->withSession(['verification_email' => 'sme@example.com'])
        ->post(route('auth.verify-email.verify'), ['code' => '123456']);

    $response->assertRedirect(route('pme.dashboard'));
});

test('valid code redirects accountant to compta dashboard', function () {
    $user = User::factory()->accountantFirm()->unverified()->create(['email' => 'firm@example.com']);
    createOtpCode('firm@example.com', '123456');

    $response = $this->actingAs($user)
        ->withSession(['verification_email' => 'firm@example.com'])
        ->post(route('auth.verify-email.verify'), ['code' => '123456']);

    $response->assertRedirect(route('dashboard'));
    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

test('invalid code is rejected', function () {
    $user = User::factory()->unverified()->create(['email' => 'sme@example.com']);
    createOtpCode('sme@example.com', '123456');

    $response = $this->actingAs($user)
        ->withSession(['verification_email' => 'sme@example.com'])
        ->post(route('auth.verify-email.verify'), ['code' => '999999']);

    $response->assertSessionHasErrors('code');
    expect($user->fresh()->email_verified_at)->toBeNull();
});

test('expired code is rejected', function () {
    $user = User::factory()->unverified()->create(['email' => 'sme@example.com']);
    createOtpCode('sme@example.com', '123456', 'email_verification', now()->subMinutes(15)->toDateTimeString());

    $response = $this->actingAs($user)
        ->withSession(['verification_email' => 'sme@example.com'])
        ->post(route('auth.verify-email.verify'), ['code' => '123456']);

    $response->assertSessionHasErrors('code');
});

test('resend creates a new otp_codes row keyed by email', function () {
    $user = User::factory()->unverified()->create(['email' => 'sme@example.com']);

    $response = $this->actingAs($user)
        ->withSession(['verification_email' => 'sme@example.com'])
        ->post(route('auth.verify-email.resend'));

    $response->assertSessionHas('status');
    $this->assertDatabaseHas('otp_codes', [
        'identifier' => 'sme@example.com',
        'purpose' => 'email_verification',
    ]);
});

test('verified.email middleware blocks unverified users from /pme/dashboard', function () {
    $user = User::factory()->unverified()->create(['profile_type' => 'sme']);
    $user->companies()->attach(
        Company::factory()->create(['type' => 'sme'])->id,
        ['role' => 'owner']
    );

    $this->actingAs($user)
        ->get('/pme/dashboard')
        ->assertRedirect(route('auth.verify-email'));
});

test('verified.email middleware lets verified users through', function () {
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create([
        'type' => 'sme',
        'setup_completed_at' => now(),
    ]);
    $user->companies()->attach($company->id, ['role' => 'owner']);

    $this->actingAs($user)
        ->get('/pme/dashboard')
        ->assertOk();
});

test('api verify-email returns json on success', function () {
    $user = User::factory()->unverified()->create(['email' => 'sme@example.com']);
    createOtpCode('sme@example.com', '123456');
    $token = $user->createToken('auth')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson(route('api.auth.verify-email'), ['code' => '123456']);

    $response->assertOk()
        ->assertJson(['message' => 'Email vérifié avec succès.']);
});
