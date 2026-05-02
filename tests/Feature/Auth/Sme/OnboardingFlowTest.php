<?php

use App\Models\Auth\Company;
use App\Models\Shared\User;
use App\Notifications\PasswordResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

/**
 * Battle-test : un nouveau PME suit l'intégralité du parcours
 * inscription → vérification email → company-setup → dashboard → logout
 * → forgot-password → reset (lien email) → login. Aucune étape ne doit
 * être sautable.
 */
test('full sme onboarding: register, verify-email, company-setup, dashboard, logout, reset password, login again', function () {
    Notification::fake();

    // --- 1. Inscription ---
    $this->post(route('register.submit'), [
        'first_name' => 'Awa',
        'last_name' => 'Ndiaye',
        'email' => 'awa@example.com',
        'phone' => '770000099',
        'password' => 'P@ssword123!',
        'password_confirmation' => 'P@ssword123!',
        'country_code' => 'SN',
    ])->assertRedirect(route('auth.verify-email'));

    $this->assertAuthenticated();
    $user = User::where('email', 'awa@example.com')->firstOrFail();
    expect($user->profile_type)->toBe('sme');
    expect($user->phone)->toBe('+221770000099');
    expect($user->email_verified_at)->toBeNull();

    // --- 2. Tentative d'accéder au dashboard avant vérification : refusé ---
    $this->get('/pme/dashboard')->assertRedirect(route('auth.verify-email'));

    // --- 3. Vérification email (replace generated OTP with a known code) ---
    DB::table('otp_codes')->where('identifier', 'awa@example.com')->update([
        'code' => hash('sha256', '123456'),
    ]);

    $this->withSession(['verification_email' => 'awa@example.com'])
        ->post(route('auth.verify-email.verify'), ['code' => '123456'])
        ->assertRedirect(route('auth.company-setup'));

    expect($user->fresh()->email_verified_at)->not->toBeNull();

    // --- 4. Company setup ---
    $this->post(route('auth.company-setup.submit'), [
        'company_name' => 'Awa Ndiaye SARL',
        'sector' => 'Commerce général',
    ])->assertRedirect(route('pme.dashboard'));

    expect(Company::where('type', 'sme')->first()->setup_completed_at)->not->toBeNull();

    // --- 5. Dashboard accessible ---
    $this->get('/pme/dashboard')->assertOk();

    // --- 6. Logout : redirige vers /login (unifié) ---
    $this->post(route('auth.logout'))->assertRedirect(route('login'));
    $this->assertGuest();

    // --- 7. Forgot password (email) ---
    $this->post(route('password.email'), [
        'email' => 'awa@example.com',
    ])->assertRedirect();

    Notification::assertSentTo($user, PasswordResetNotification::class);

    // --- 8. Reset password via lien email ---
    $token = Password::broker()->createToken($user->fresh());

    $this->post(route('auth.reset-password.submit'), [
        'token' => $token,
        'email' => 'awa@example.com',
        'password' => 'NewP@ssword456!',
        'password_confirmation' => 'NewP@ssword456!',
    ])->assertRedirect(route('pme.dashboard'));

    $this->assertAuthenticated();
    expect(Hash::check('NewP@ssword456!', $user->fresh()->password))->toBeTrue();

    // --- 9. Re-logout, re-login with new password (par email) ---
    $this->post(route('auth.logout'));

    $this->post(route('login'), [
        'email' => 'awa@example.com',
        'password' => 'NewP@ssword456!',
    ])->assertRedirect(route('pme.dashboard'));

    $this->assertAuthenticatedAs($user);

    // --- 10. Old password no longer works ---
    $this->post(route('auth.logout'));

    $this->post(route('login'), [
        'email' => 'awa@example.com',
        'password' => 'P@ssword123!',
    ])->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('sme is redirected to /pme/dashboard if they try to access /compta/*', function () {
    $user = User::factory()->create(['profile_type' => 'sme']);

    $this->actingAs($user)->get('/compta/dashboard')->assertRedirect(route('pme.dashboard'));
});
