<?php

use App\Models\Shared\User;
use App\Notifications\AccountantPasswordResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ─── Rendu de la page unifiée ─────────────────────────────────────────────────

test('GET /forgot-password renders the unified page with both profile cards', function () {
    $this->get(route('password.request'))
        ->assertOk()
        ->assertSee('Entreprise')
        ->assertSee('Cabinet')
        ->assertSee('Je facture mes clients')
        ->assertSee('Je gère mes clients PME');
});

test('GET /forgot-password does not mark the phone field with a static HTML required attribute (would block accountant submit)', function () {
    $content = $this->get(route('password.request'))->getContent();

    expect($content)
        ->not->toMatch('/<input[^>]*name="phone"[^>]*\srequired(\s|>|=)/s');
});

test('GET /forgot-password does not mark the email field with a static HTML required attribute (would block sme submit)', function () {
    $content = $this->get(route('password.request'))->getContent();

    expect($content)
        ->not->toMatch('/<input[^>]*name="email"[^>]*\srequired(\s|>|=)/s');
});

test('GET /forgot-password binds the phone required attribute to the active profile via Alpine', function () {
    $content = $this->get(route('password.request'))->getContent();

    expect($content)->toContain('x-bind:required="profile === &#039;sme&#039;"');
});

test('GET /forgot-password binds the email required attribute to the active profile via Alpine', function () {
    $content = $this->get(route('password.request'))->getContent();

    expect($content)->toContain('x-bind:required="profile === \'accountant\'"');
});

test('accountant can submit forgot-password without any phone or country_code in the payload', function () {
    Notification::fake();

    User::factory()->accountantFirm()->create(['email' => 'firm@example.com']);

    // Simulates the browser submission when "Cabinet Comptable" is selected:
    // the phone block is hidden so phone/country_code are absent from the payload.
    $this->post(route('password.email'), [
        'profile' => 'accountant',
        'email' => 'firm@example.com',
    ])->assertRedirect();

    Notification::assertSentTo(
        User::where('email', 'firm@example.com')->first(),
        AccountantPasswordResetNotification::class
    );
});

test('sme can submit forgot-password without any email in the payload', function () {
    User::factory()->create(['phone' => '+221771234567', 'profile_type' => 'sme']);

    // Simulates the browser submission when "Espace PME" is selected:
    // the email block is hidden so email is absent from the payload.
    $this->post(route('password.email'), [
        'profile' => 'sme',
        'phone' => '771234567',
        'country_code' => 'SN',
    ])->assertRedirect(route('sme.auth.reset-password'));

    $this->assertDatabaseHas('otp_codes', [
        'phone' => '+221771234567',
        'purpose' => 'password_reset',
    ]);
});

test('legacy /sme/forgot-password is gone (404)', function () {
    $this->get('/sme/forgot-password')->assertNotFound();
});

test('legacy /accountant/forgot-password is gone (404)', function () {
    $this->get('/accountant/forgot-password')->assertNotFound();
});

// ─── Profil PME : OTP par téléphone ──────────────────────────────────────────

test('sme user can request password reset otp', function () {
    User::factory()->create(['phone' => '+221771234567', 'profile_type' => 'sme']);

    $response = $this->post(route('password.email'), [
        'profile' => 'sme',
        'phone' => '771234567',
        'country_code' => 'SN',
    ]);

    $response->assertRedirect(route('sme.auth.reset-password'));
    $this->assertDatabaseHas('otp_codes', [
        'phone' => '+221771234567',
        'purpose' => 'password_reset',
    ]);
});

test('sme forgot password does nothing for an unknown phone (no enumeration)', function () {
    $response = $this->post(route('password.email'), [
        'profile' => 'sme',
        'phone' => '770000000',
        'country_code' => 'SN',
    ]);

    $response->assertRedirect(route('sme.auth.reset-password'));
    $this->assertDatabaseMissing('otp_codes', [
        'phone' => '+221770000000',
        'purpose' => 'password_reset',
    ]);
});

test('sme forgot password ignores accountant accounts even if phone matches', function () {
    User::factory()->accountantFirm()->create(['phone' => '+221771234567']);

    $this->post(route('password.email'), [
        'profile' => 'sme',
        'phone' => '771234567',
        'country_code' => 'SN',
    ])->assertRedirect(route('sme.auth.reset-password'));

    $this->assertDatabaseMissing('otp_codes', [
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
        ->assertRedirect(route('password.request'));
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
    User::factory()->create(['phone' => '+221771234567', 'profile_type' => 'sme']);

    $response = $this->withSession(['reset_phone' => '+221771234567'])
        ->post(route('sme.auth.reset-password.submit'), [
            'code' => '999999',
            'password' => 'NewP@ssword123!',
            'password_confirmation' => 'NewP@ssword123!',
        ]);

    $response->assertSessionHasErrors('code');
});

test('api password reset returns json', function () {
    User::factory()->create(['phone' => '+221771234567', 'profile_type' => 'sme']);

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

// ─── Profil Cabinet : lien e-mail ────────────────────────────────────────────

test('accountant forgot password sends a reset notification', function () {
    Notification::fake();

    $user = User::factory()->accountantFirm()->create(['email' => 'firm@example.com']);

    $this->post(route('password.email'), [
        'profile' => 'accountant',
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

test('accountant forgot password silently succeeds for unknown email (no enumeration)', function () {
    Notification::fake();

    $this->post(route('password.email'), [
        'profile' => 'accountant',
        'email' => 'unknown@example.com',
    ])->assertRedirect();

    Notification::assertNothingSent();
});

test('accountant forgot password does not target sme users', function () {
    Notification::fake();

    User::factory()->create([
        'email' => 'sme@example.com',
        'profile_type' => 'sme',
    ]);

    $this->post(route('password.email'), [
        'profile' => 'accountant',
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

    $this->post(route('password.email'), [
        'profile' => 'accountant',
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

test('accountant reset password form can be rendered with token and email', function () {
    $this->get(route('accountant.auth.reset-password', ['token' => 'sometoken']).'?email=firm@example.com')
        ->assertOk()
        ->assertSee('sometoken', false)
        ->assertSee('firm@example.com', false);
});

test('accountant can reset password with valid token', function () {
    $user = User::factory()->accountantFirm()->create(['email' => 'firm@example.com']);

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

test('accountant reset password rejects an invalid token', function () {
    User::factory()->accountantFirm()->create(['email' => 'firm@example.com']);

    $response = $this->post(route('accountant.auth.reset-password.submit'), [
        'token' => 'totally-bogus-token',
        'email' => 'firm@example.com',
        'password' => 'BrandNew@Pass123!',
        'password_confirmation' => 'BrandNew@Pass123!',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('accountant reset password rejects sme accounts even with a valid token', function () {
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

test('accountant reset password token cannot be reused', function () {
    $user = User::factory()->accountantFirm()->create(['email' => 'firm@example.com']);

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

test('accountant reset password token expires after the configured TTL', function () {
    $user = User::factory()->accountantFirm()->create(['email' => 'firm@example.com']);

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

test('accountant reset password rejects an email mismatch with token owner', function () {
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

test('accountant reset password rejects mismatched confirmation', function () {
    $user = User::factory()->accountantFirm()->create(['email' => 'firm@example.com']);
    $token = Password::broker()->createToken($user);

    $this->post(route('accountant.auth.reset-password.submit'), [
        'token' => $token,
        'email' => 'firm@example.com',
        'password' => 'BrandNew@Pass123!',
        'password_confirmation' => 'Different@P4ss!',
    ])->assertSessionHasErrors('password');
});

test('accountant reset password rejects too-short password', function () {
    $user = User::factory()->accountantFirm()->create(['email' => 'firm@example.com']);
    $token = Password::broker()->createToken($user);

    $this->post(route('accountant.auth.reset-password.submit'), [
        'token' => $token,
        'email' => 'firm@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');
});

test('accountant reset password is case-insensitive on email lookup', function () {
    $user = User::factory()->accountantFirm()->create(['email' => 'firm@example.com']);

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

test('accountant forgot-password is rate-limited via throttle config', function () {
    Notification::fake();

    User::factory()->accountantFirm()->create(['email' => 'firm@example.com']);

    $this->post(route('password.email'), [
        'profile' => 'accountant',
        'email' => 'firm@example.com',
    ])->assertRedirect();

    $this->post(route('password.email'), [
        'profile' => 'accountant',
        'email' => 'firm@example.com',
    ])->assertRedirect();

    Notification::assertSentToTimes(
        User::where('email', 'firm@example.com')->first(),
        AccountantPasswordResetNotification::class,
        1
    );
});

// ─── Validation du toggle ─────────────────────────────────────────────────────

test('forgot password fails when profile is missing', function () {
    $this->post(route('password.email'), [
        'phone' => '771234567',
        'country_code' => 'SN',
    ])->assertSessionHasErrors('profile');
});

test('forgot password fails when profile=sme but phone is missing', function () {
    $this->post(route('password.email'), [
        'profile' => 'sme',
        'email' => 'foo@bar.com',
    ])->assertSessionHasErrors(['phone', 'country_code']);
});

test('forgot password fails when profile=accountant but email is missing', function () {
    $this->post(route('password.email'), [
        'profile' => 'accountant',
        'phone' => '771234567',
        'country_code' => 'SN',
    ])->assertSessionHasErrors('email');
});
