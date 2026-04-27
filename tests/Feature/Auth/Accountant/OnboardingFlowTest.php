<?php

use App\Mail\Compta\AccountantActivationLinkMail;
use App\Models\Compta\AccountantLead;
use App\Models\Shared\User;
use App\Notifications\AccountantPasswordResetNotification;
use App\Services\Compta\AccountantLeadActivator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

/**
 * Battle-test : un cabinet suit l'intégralité du parcours
 * lead → activation magic link → définition mot de passe → dashboard →
 * logout → forgot password (email) → reset → dashboard. Vérifie aussi
 * qu'aucune étape ne se laisse contourner.
 */
test('full accountant onboarding: lead → activation → dashboard → logout → reset password → re-login', function () {
    Mail::fake();

    // --- 1. Lead créé puis activé par admin ---
    $lead = AccountantLead::create([
        'first_name' => 'Mamadou',
        'last_name' => 'Diallo',
        'firm' => 'Cabinet Diallo & Associés',
        'email' => 'cabinet@diallo.sn',
        'country_code' => 'SN',
        'phone' => '+221770000088',
        'region' => 'Dakar',
        'portfolio_size' => '1 à 20 dossiers',
        'message' => 'Test',
    ]);

    app(AccountantLeadActivator::class)->activate($lead);

    /** @var AccountantActivationLinkMail|null $captured */
    $captured = null;
    Mail::assertSent(AccountantActivationLinkMail::class, function ($mail) use (&$captured) {
        $captured = $mail;

        return true;
    });

    // --- 2. Le cabinet définit son mot de passe via le lien ---
    $this->post(route('accountant.activation.process', $captured->token), [
        'password' => 'Init@P4ssw0rd!',
        'password_confirmation' => 'Init@P4ssw0rd!',
        'cgu_accepted' => '1',
    ])->assertRedirect(route('dashboard'));

    $user = User::where('email', 'cabinet@diallo.sn')->firstOrFail();
    expect($user->is_active)->toBeTrue();
    expect($user->profile_type)->toBe('accountant_firm');
    expect($user->email_verified_at)->not->toBeNull();
    expect($user->phone_verified_at)->not->toBeNull();
    expect(Hash::check('Init@P4ssw0rd!', $user->password))->toBeTrue();

    // --- 3. Dashboard accessible ---
    $this->actingAs($user)->get('/compta/dashboard')->assertOk();

    // --- 4. Logout → redirige vers /accountant/login ---
    $this->post(route('auth.logout'))->assertRedirect(route('accountant.auth.login'));
    $this->assertGuest();

    // --- 5. Connexion avec email + password ---
    $this->post(route('accountant.auth.login.submit'), [
        'email' => 'cabinet@diallo.sn',
        'password' => 'Init@P4ssw0rd!',
    ])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);

    // --- 6. Logout, puis demande de reset par email ---
    $this->post(route('auth.logout'));

    Notification::fake();
    $this->post(route('accountant.auth.forgot-password.submit'), [
        'email' => 'cabinet@diallo.sn',
    ])->assertRedirect();

    /** @var AccountantPasswordResetNotification|null $sent */
    $sent = null;
    Notification::assertSentTo(
        $user,
        AccountantPasswordResetNotification::class,
        function (AccountantPasswordResetNotification $n) use (&$sent) {
            $sent = $n;

            return true;
        }
    );

    // Le lien envoyé pointe vers /accountant/reset-password/{token}
    expect($sent->resetUrl)->toContain('/accountant/reset-password/');
    expect($sent->resetUrl)->toContain('email='.urlencode('cabinet@diallo.sn'));

    // --- 7. Reset avec un nouveau token (Password::broker pour reproduire) ---
    $token = Password::broker()->createToken($user);

    $this->post(route('accountant.auth.reset-password.submit'), [
        'token' => $token,
        'email' => 'cabinet@diallo.sn',
        'password' => 'NewSecure@P4ssw0rd!',
        'password_confirmation' => 'NewSecure@P4ssw0rd!',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect(Hash::check('NewSecure@P4ssw0rd!', $user->fresh()->password))->toBeTrue();

    // --- 8. Old password no longer works ---
    $this->post(route('auth.logout'));
    $this->post(route('accountant.auth.login.submit'), [
        'email' => 'cabinet@diallo.sn',
        'password' => 'Init@P4ssw0rd!',
    ])->assertSessionHasErrors('email');
    $this->assertGuest();

    // --- 9. New password works ---
    $this->post(route('accountant.auth.login.submit'), [
        'email' => 'cabinet@diallo.sn',
        'password' => 'NewSecure@P4ssw0rd!',
    ])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('accountant is redirected to /compta/dashboard if they try to access /pme/*', function () {
    $user = User::factory()->accountantFirm()->create();

    $this->actingAs($user)->get('/pme/dashboard')->assertRedirect(route('dashboard'));
});

test('accountant activation flow rejects accountant from logging in via sme portal afterwards', function () {
    Mail::fake();

    $lead = AccountantLead::create([
        'first_name' => 'Awa',
        'last_name' => 'Sow',
        'firm' => 'Sow Conseil',
        'email' => 'awa@sow.sn',
        'country_code' => 'SN',
        'phone' => '+221770000077',
        'region' => 'Thiès',
        'portfolio_size' => '1 à 20 dossiers',
        'message' => 'Test',
    ]);

    app(AccountantLeadActivator::class)->activate($lead);

    /** @var AccountantActivationLinkMail $captured */
    $captured = null;
    Mail::assertSent(AccountantActivationLinkMail::class, function ($mail) use (&$captured) {
        $captured = $mail;

        return true;
    });

    $this->post(route('accountant.activation.process', $captured->token), [
        'password' => 'Init@P4ssw0rd!',
        'password_confirmation' => 'Init@P4ssw0rd!',
        'cgu_accepted' => '1',
    ])->assertRedirect(route('dashboard'));

    $this->post(route('auth.logout'));

    // Tentative de login via le portail PME avec le téléphone du cabinet → refusé.
    $this->post(route('sme.auth.login.submit'), [
        'phone' => '770000077',
        'password' => 'Init@P4ssw0rd!',
        'country_code' => 'SN',
    ])->assertSessionHasErrors('phone');
    $this->assertGuest();

    // Login via le portail comptable → OK.
    $this->post(route('accountant.auth.login.submit'), [
        'email' => 'awa@sow.sn',
        'password' => 'Init@P4ssw0rd!',
    ])->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();
});
