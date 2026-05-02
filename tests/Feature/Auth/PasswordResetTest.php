<?php

use App\Models\Shared\User;
use App\Notifications\PasswordResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

// ─── Rendu de la page unifiée ─────────────────────────────────────────────────

test('GET /forgot-password renders the email-only page', function () {
    $this->get(route('password.request'))
        ->assertOk()
        ->assertSee('Mot de passe oublié')
        ->assertSee('Email');
});

test('legacy /sme/forgot-password is gone (404)', function () {
    $this->get('/sme/forgot-password')->assertNotFound();
});

test('legacy /accountant/forgot-password is gone (404)', function () {
    $this->get('/accountant/forgot-password')->assertNotFound();
});

// ─── Forgot password : envoi du lien ──────────────────────────────────────────

test('forgot-password sends a reset notification to a sme user', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'sme@example.com',
        'profile_type' => 'sme',
    ]);

    $this->post(route('password.email'), [
        'email' => 'sme@example.com',
    ])->assertRedirect();

    Notification::assertSentTo($user, PasswordResetNotification::class);
});

test('forgot-password sends a reset notification to an accountant user', function () {
    Notification::fake();

    $user = User::factory()->accountantFirm()->create(['email' => 'firm@example.com']);

    $this->post(route('password.email'), [
        'email' => 'firm@example.com',
    ])->assertRedirect();

    Notification::assertSentTo($user, PasswordResetNotification::class);
});

test('forgot-password silently succeeds for unknown email (no enumeration)', function () {
    Notification::fake();

    $this->post(route('password.email'), [
        'email' => 'unknown@example.com',
    ])->assertRedirect();

    Notification::assertNothingSent();
});

test('forgot-password requires a valid email', function () {
    $this->post(route('password.email'), [])
        ->assertSessionHasErrors('email');

    $this->post(route('password.email'), ['email' => 'not-an-email'])
        ->assertSessionHasErrors('email');
});

test('reset notification url points at the unified auth.reset-password route', function () {
    Notification::fake();

    $user = User::factory()->create([
        'first_name' => 'Mamadou',
        'email' => 'firm@example.com',
        'profile_type' => 'sme',
    ]);

    $this->post(route('password.email'), ['email' => 'firm@example.com'])->assertRedirect();

    Notification::assertSentTo(
        $user,
        PasswordResetNotification::class,
        function (PasswordResetNotification $notification) {
            $mail = $notification->toMail((object) ['first_name' => 'Mamadou', 'email' => 'firm@example.com']);

            return str_contains($mail->resetUrl, '/auth/reset-password/')
                && str_contains($mail->resetUrl, 'email=firm');
        }
    );
});

// ─── Reset password : flow unifié ─────────────────────────────────────────────

test('reset password form renders with token and email', function () {
    $this->get(route('auth.reset-password', ['token' => 'sometoken']).'?email=firm@example.com')
        ->assertOk()
        ->assertSee('sometoken', false)
        ->assertSee('firm@example.com', false);
});

test('accountant can reset password with valid token and lands on compta dashboard', function () {
    $user = User::factory()->accountantFirm()->create(['email' => 'firm@example.com']);

    $token = Password::broker()->createToken($user);

    $response = $this->post(route('auth.reset-password.submit'), [
        'token' => $token,
        'email' => 'firm@example.com',
        'password' => 'BrandNew@Pass123!',
        'password_confirmation' => 'BrandNew@Pass123!',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
    expect(Hash::check('BrandNew@Pass123!', $user->fresh()->password))->toBeTrue();
    expect(DB::table('password_reset_tokens')->where('email', 'firm@example.com')->count())->toBe(0);
});

test('sme can reset password with valid token and lands on pme dashboard', function () {
    $user = User::factory()->create([
        'email' => 'sme@example.com',
        'profile_type' => 'sme',
    ]);

    $token = Password::broker()->createToken($user);

    $response = $this->post(route('auth.reset-password.submit'), [
        'token' => $token,
        'email' => 'sme@example.com',
        'password' => 'BrandNew@Pass123!',
        'password_confirmation' => 'BrandNew@Pass123!',
    ]);

    $response->assertRedirect(route('pme.dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('reset password rejects an invalid token', function () {
    User::factory()->accountantFirm()->create(['email' => 'firm@example.com']);

    $response = $this->post(route('auth.reset-password.submit'), [
        'token' => 'totally-bogus-token',
        'email' => 'firm@example.com',
        'password' => 'BrandNew@Pass123!',
        'password_confirmation' => 'BrandNew@Pass123!',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('reset password token cannot be reused', function () {
    $user = User::factory()->accountantFirm()->create(['email' => 'firm@example.com']);

    $token = Password::broker()->createToken($user);

    $this->post(route('auth.reset-password.submit'), [
        'token' => $token,
        'email' => 'firm@example.com',
        'password' => 'FirstReset@P4ssw0rd!',
        'password_confirmation' => 'FirstReset@P4ssw0rd!',
    ])->assertRedirect(route('dashboard'));

    auth()->logout();

    $second = $this->post(route('auth.reset-password.submit'), [
        'token' => $token,
        'email' => 'firm@example.com',
        'password' => 'SecondReset@P4ssw0rd!',
        'password_confirmation' => 'SecondReset@P4ssw0rd!',
    ]);

    $second->assertSessionHasErrors('email');
    $this->assertGuest();
    expect(Hash::check('SecondReset@P4ssw0rd!', $user->fresh()->password))->toBeFalse();
    expect(Hash::check('FirstReset@P4ssw0rd!', $user->fresh()->password))->toBeTrue();
});

test('reset password token expires after the configured TTL', function () {
    $user = User::factory()->accountantFirm()->create(['email' => 'firm@example.com']);

    $token = Password::broker()->createToken($user);

    DB::table('password_reset_tokens')
        ->where('email', 'firm@example.com')
        ->update(['created_at' => now()->subMinutes(config('auth.passwords.users.expire') + 5)]);

    $response = $this->post(route('auth.reset-password.submit'), [
        'token' => $token,
        'email' => 'firm@example.com',
        'password' => 'BrandNew@Pass123!',
        'password_confirmation' => 'BrandNew@Pass123!',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('reset password rejects email mismatch with token owner', function () {
    $owner = User::factory()->accountantFirm()->create(['email' => 'owner@example.com']);
    User::factory()->accountantFirm()->create(['email' => 'other@example.com']);

    $token = Password::broker()->createToken($owner);

    $response = $this->post(route('auth.reset-password.submit'), [
        'token' => $token,
        'email' => 'other@example.com',
        'password' => 'BrandNew@Pass123!',
        'password_confirmation' => 'BrandNew@Pass123!',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('reset password rejects mismatched confirmation', function () {
    $user = User::factory()->accountantFirm()->create(['email' => 'firm@example.com']);
    $token = Password::broker()->createToken($user);

    $this->post(route('auth.reset-password.submit'), [
        'token' => $token,
        'email' => 'firm@example.com',
        'password' => 'BrandNew@Pass123!',
        'password_confirmation' => 'Different@P4ss!',
    ])->assertSessionHasErrors('password');
});

test('reset password rejects too-short password', function () {
    $user = User::factory()->accountantFirm()->create(['email' => 'firm@example.com']);
    $token = Password::broker()->createToken($user);

    $this->post(route('auth.reset-password.submit'), [
        'token' => $token,
        'email' => 'firm@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');
});

test('reset password is case-insensitive on email lookup', function () {
    $user = User::factory()->accountantFirm()->create(['email' => 'firm@example.com']);

    $token = Password::broker()->createToken($user);

    $response = $this->post(route('auth.reset-password.submit'), [
        'token' => $token,
        'email' => 'FIRM@Example.COM',
        'password' => 'BrandNew@Pass123!',
        'password_confirmation' => 'BrandNew@Pass123!',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});
