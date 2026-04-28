<?php

use App\Models\Auth\Company;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Battle-test : un nouveau PME suit l'intégralité du parcours
 * inscription → OTP → company-setup → dashboard → logout → forgot-password
 * → reset OTP → dashboard. Aucune étape ne doit être sautable.
 */
test('full sme onboarding: register, otp, company-setup, dashboard, logout, reset password, login again', function () {
    // --- 1. Inscription ---
    $this->post(route('register.submit'), [
        'first_name' => 'Awa',
        'last_name' => 'Ndiaye',
        'phone' => '770000099',
        'password' => 'P@ssword123!',
        'password_confirmation' => 'P@ssword123!',
        'country_code' => 'SN',
    ])->assertRedirect(route('sme.auth.otp'));

    $this->assertAuthenticated();
    $user = User::where('phone', '+221770000099')->firstOrFail();
    expect($user->profile_type)->toBe('sme');
    expect($user->phone_verified_at)->toBeNull();

    // --- 2. Tentative d'accéder au dashboard avant OTP : refusé ---
    $this->get('/pme/dashboard')->assertRedirect(route('sme.auth.otp'));

    // --- 3. Vérification OTP ---
    DB::table('otp_codes')->where('phone', '+221770000099')->update([
        'code' => hash('sha256', '123456'),
    ]);

    $this->withSession(['otp_phone' => '+221770000099'])
        ->post(route('sme.auth.otp.verify'), ['code' => '123456'])
        ->assertRedirect(route('auth.company-setup'));

    expect($user->fresh()->phone_verified_at)->not->toBeNull();

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

    // --- 7. Forgot password (profile=sme) ---
    $this->post(route('password.email'), [
        'profile' => 'sme',
        'phone' => '770000099',
        'country_code' => 'SN',
    ])->assertRedirect(route('sme.auth.reset-password'));

    // Replace the auto-generated OTP with one whose code we know.
    DB::table('otp_codes')->where('phone', '+221770000099')->delete();
    DB::table('otp_codes')->insert([
        'id' => (string) Str::ulid(),
        'phone' => '+221770000099',
        'code' => hash('sha256', '654321'),
        'purpose' => 'password_reset',
        'expires_at' => now()->addMinutes(10),
        'attempts' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // --- 8. Reset password ---
    $resetResponse = $this->withSession(['reset_phone' => '+221770000099'])
        ->post(route('sme.auth.reset-password.submit'), [
            'code' => '654321',
            'password' => 'NewP@ssword456!',
            'password_confirmation' => 'NewP@ssword456!',
        ]);

    $resetResponse->assertRedirect(route('pme.dashboard'));

    $this->assertAuthenticated();
    expect(Hash::check('NewP@ssword456!', $user->fresh()->password))->toBeTrue();

    // --- 9. Re-logout, re-login with new password (profile=sme) ---
    $this->post(route('auth.logout'));

    $this->post(route('login'), [
        'profile' => 'sme',
        'phone' => '770000099',
        'password' => 'NewP@ssword456!',
        'country_code' => 'SN',
    ])->assertRedirect(route('pme.dashboard'));

    $this->assertAuthenticatedAs($user);

    // --- 10. Old password no longer works ---
    $this->post(route('auth.logout'));

    $this->post(route('login'), [
        'profile' => 'sme',
        'phone' => '770000099',
        'password' => 'P@ssword123!',
        'country_code' => 'SN',
    ])->assertSessionHasErrors('phone');
    $this->assertGuest();
});

test('sme is redirected to /pme/dashboard if they try to access /compta/*', function () {
    $user = User::factory()->create(['profile_type' => 'sme']);

    $this->actingAs($user)->get('/compta/dashboard')->assertRedirect(route('pme.dashboard'));
});
