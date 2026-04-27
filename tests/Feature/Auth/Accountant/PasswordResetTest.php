<?php

use App\Models\Shared\User;
use App\Notifications\AccountantPasswordResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

test('accountant forgot password page can be rendered', function () {
    $this->get(route('accountant.auth.forgot-password'))
        ->assertOk();
});

test('forgot password sends a notification with reset url for an accountant', function () {
    Notification::fake();

    $user = User::factory()->accountantFirm()->create([
        'email' => 'firm@example.com',
    ]);

    $this->post(route('accountant.auth.forgot-password.submit'), [
        'email' => 'firm@example.com',
    ])->assertRedirect();

    Notification::assertSentTo(
        $user,
        AccountantPasswordResetNotification::class,
        function (AccountantPasswordResetNotification $notification) {
            return str_contains($notification->resetUrl, '/accountant/reset-password/')
                && str_contains($notification->resetUrl, 'email=firm');
        }
    );
});

test('forgot password silently succeeds for unknown email (no enumeration)', function () {
    Notification::fake();

    $this->post(route('accountant.auth.forgot-password.submit'), [
        'email' => 'unknown@example.com',
    ])->assertRedirect();

    Notification::assertNothingSent();
});

test('forgot password does not target sme users', function () {
    Notification::fake();

    User::factory()->create([
        'email' => 'sme@example.com',
        'profile_type' => 'sme',
    ]);

    $this->post(route('accountant.auth.forgot-password.submit'), [
        'email' => 'sme@example.com',
    ])->assertRedirect();

    Notification::assertNothingSent();
});

test('accountant password reset notification carries first name and reset url', function () {
    Notification::fake();

    $user = User::factory()->accountantFirm()->create([
        'first_name' => 'Mamadou',
        'email' => 'firm@example.com',
    ]);

    $this->post(route('accountant.auth.forgot-password.submit'), [
        'email' => 'firm@example.com',
    ])->assertRedirect();

    Notification::assertSentTo(
        $user,
        AccountantPasswordResetNotification::class,
        function (AccountantPasswordResetNotification $notification) use ($user) {
            $mailable = $notification->toMail($user);

            return $mailable->firstName === 'Mamadou'
                && str_contains($mailable->resetUrl, '/accountant/reset-password/')
                && $mailable->expiresInMinutes === (int) config('auth.passwords.users.expire');
        }
    );
});

test('reset password form can be rendered with token and email', function () {
    $this->get(route('accountant.auth.reset-password', ['token' => 'sometoken']).'?email=firm@example.com')
        ->assertOk()
        ->assertSee('sometoken', false)
        ->assertSee('firm@example.com', false);
});

test('accountant can reset password with valid token', function () {
    $user = User::factory()->accountantFirm()->create([
        'email' => 'firm@example.com',
    ]);

    $token = Password::broker()->createToken($user);

    $response = $this->post(route('accountant.auth.reset-password.submit'), [
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

test('reset password rejects an invalid token', function () {
    User::factory()->accountantFirm()->create([
        'email' => 'firm@example.com',
    ]);

    $response = $this->post(route('accountant.auth.reset-password.submit'), [
        'token' => 'totally-bogus-token',
        'email' => 'firm@example.com',
        'password' => 'BrandNew@Pass123!',
        'password_confirmation' => 'BrandNew@Pass123!',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('reset password rejects sme accounts even with a valid token', function () {
    $sme = User::factory()->create([
        'email' => 'sme@example.com',
        'profile_type' => 'sme',
    ]);

    $token = Password::broker()->createToken($sme);

    $response = $this->post(route('accountant.auth.reset-password.submit'), [
        'token' => $token,
        'email' => 'sme@example.com',
        'password' => 'BrandNew@Pass123!',
        'password_confirmation' => 'BrandNew@Pass123!',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('reset password token cannot be reused', function () {
    $user = User::factory()->accountantFirm()->create([
        'email' => 'firm@example.com',
    ]);

    $token = Password::broker()->createToken($user);

    $this->post(route('accountant.auth.reset-password.submit'), [
        'token' => $token,
        'email' => 'firm@example.com',
        'password' => 'FirstReset@P4ssw0rd!',
        'password_confirmation' => 'FirstReset@P4ssw0rd!',
    ])->assertRedirect(route('dashboard'));

    auth()->logout();

    $second = $this->post(route('accountant.auth.reset-password.submit'), [
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
    $user = User::factory()->accountantFirm()->create([
        'email' => 'firm@example.com',
    ]);

    $token = Password::broker()->createToken($user);

    DB::table('password_reset_tokens')
        ->where('email', 'firm@example.com')
        ->update(['created_at' => now()->subMinutes(config('auth.passwords.users.expire') + 5)]);

    $response = $this->post(route('accountant.auth.reset-password.submit'), [
        'token' => $token,
        'email' => 'firm@example.com',
        'password' => 'BrandNew@Pass123!',
        'password_confirmation' => 'BrandNew@Pass123!',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('reset password rejects an email that does not match the tokens owner', function () {
    $owner = User::factory()->accountantFirm()->create(['email' => 'owner@example.com']);
    User::factory()->accountantFirm()->create(['email' => 'other@example.com']);

    $token = Password::broker()->createToken($owner);

    $response = $this->post(route('accountant.auth.reset-password.submit'), [
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

    $this->post(route('accountant.auth.reset-password.submit'), [
        'token' => $token,
        'email' => 'firm@example.com',
        'password' => 'BrandNew@Pass123!',
        'password_confirmation' => 'Different@P4ss!',
    ])->assertSessionHasErrors('password');
});

test('reset password rejects too-short password', function () {
    $user = User::factory()->accountantFirm()->create(['email' => 'firm@example.com']);
    $token = Password::broker()->createToken($user);

    $this->post(route('accountant.auth.reset-password.submit'), [
        'token' => $token,
        'email' => 'firm@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');
});

test('reset password is case-insensitive on email lookup', function () {
    $user = User::factory()->accountantFirm()->create([
        'email' => 'firm@example.com',
    ]);

    $token = Password::broker()->createToken($user);

    $response = $this->post(route('accountant.auth.reset-password.submit'), [
        'token' => $token,
        'email' => 'FIRM@Example.COM',
        'password' => 'BrandNew@Pass123!',
        'password_confirmation' => 'BrandNew@Pass123!',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('forgot-password is rate-limited via throttle config', function () {
    Notification::fake();

    User::factory()->accountantFirm()->create([
        'email' => 'firm@example.com',
    ]);

    $this->post(route('accountant.auth.forgot-password.submit'), [
        'email' => 'firm@example.com',
    ])->assertRedirect();

    // Second immediate attempt is throttled by Password::broker()
    // (60 seconds by default) — no new notification is sent.
    $this->post(route('accountant.auth.forgot-password.submit'), [
        'email' => 'firm@example.com',
    ])->assertRedirect();

    Notification::assertSentToTimes(
        User::where('email', 'firm@example.com')->first(),
        AccountantPasswordResetNotification::class,
        1
    );
});
