<?php

use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Cookie;

uses(RefreshDatabase::class);

// ─── Rendu de la page unifiée ─────────────────────────────────────────────────

test('the named login route resolves to /login', function () {
    expect(route('login', [], false))->toBe('/login');
});

test('GET /login renders the unified login page with both profile cards', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Entreprise')
        ->assertSee('Cabinet')
        ->assertSee('Je facture mes clients')
        ->assertSee('Je gère mes clients PME');
});

test('GET /login does not mark the phone field with a static HTML required attribute (would block accountant submit)', function () {
    $content = $this->get(route('login'))->getContent();

    expect($content)
        ->not->toMatch('/<input[^>]*name="phone"[^>]*\srequired(\s|>|=)/s');
});

test('GET /login does not mark the email field with a static HTML required attribute (would block sme submit)', function () {
    $content = $this->get(route('login'))->getContent();

    expect($content)
        ->not->toMatch('/<input[^>]*name="email"[^>]*\srequired(\s|>|=)/s');
});

test('GET /login binds the phone required attribute to the active profile via Alpine', function () {
    $content = $this->get(route('login'))->getContent();

    // The phone input lives inside <x-phone-input>, where Blade escapes apostrophes.
    expect($content)->toContain('x-bind:required="profile === &#039;sme&#039;"');
});

test('GET /login binds the email required attribute to the active profile via Alpine', function () {
    $content = $this->get(route('login'))->getContent();

    // The email input is rendered inline in the view, so apostrophes stay raw.
    expect($content)->toContain('x-bind:required="profile === \'accountant\'"');
});

test('accountant can submit login without any phone or country_code in the payload', function () {
    $user = User::factory()->accountantFirm()->create(['email' => 'firm@example.com']);

    // Simulates the browser submission when "Cabinet Comptable" is selected:
    // the phone block is hidden so phone/country_code are absent from the payload.
    $response = $this->post(route('login'), [
        'profile' => 'accountant',
        'email' => 'firm@example.com',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('sme can submit login without any email in the payload', function () {
    $user = User::factory()->create([
        'phone' => '+221771234567',
        'profile_type' => 'sme',
    ]);

    // Simulates the browser submission when "Espace PME" is selected:
    // the email block is hidden so email is absent from the payload.
    $response = $this->post(route('login'), [
        'profile' => 'sme',
        'phone' => '771234567',
        'country_code' => 'SN',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('pme.dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('the auth middleware redirects guests to /login', function () {
    $this->get('/pme/dashboard')->assertRedirect('/login');
    $this->get('/compta/clients')->assertRedirect('/login');
});

test('legacy /sme/login is gone (404)', function () {
    $this->get('/sme/login')->assertNotFound();
});

test('legacy /accountant/login is gone (404)', function () {
    $this->get('/accountant/login')->assertNotFound();
});

// ─── Profil PME ───────────────────────────────────────────────────────────────

test('sme user is redirected to pme dashboard after login', function () {
    $user = User::factory()->create([
        'phone' => '+221771234567',
        'profile_type' => 'sme',
    ]);

    $response = $this->post(route('login'), [
        'profile' => 'sme',
        'phone' => '771234567',
        'password' => 'password',
        'country_code' => 'SN',
    ]);

    $response->assertRedirect(route('pme.dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('sme user can login with an already international formatted phone number', function () {
    $user = User::factory()->create([
        'phone' => '+221771234567',
        'profile_type' => 'sme',
    ]);

    $response = $this->post(route('login'), [
        'profile' => 'sme',
        'phone' => '+221 77 123 45 67',
        'password' => 'password',
        'country_code' => 'SN',
    ]);

    $response->assertRedirect(route('pme.dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('accountant cannot login via the sme profile', function () {
    User::factory()->accountantFirm()->create([
        'phone' => '+221771234567',
    ]);

    $response = $this->post(route('login'), [
        'profile' => 'sme',
        'phone' => '771234567',
        'password' => 'password',
        'country_code' => 'SN',
    ]);

    $response->assertSessionHasErrors('phone');
    $this->assertGuest();
});

test('sme login fails with wrong password', function () {
    User::factory()->create([
        'phone' => '+221771234567',
        'profile_type' => 'sme',
    ]);

    $response = $this->post(route('login'), [
        'profile' => 'sme',
        'phone' => '771234567',
        'password' => 'wrong-password',
        'country_code' => 'SN',
    ]);

    $response->assertSessionHasErrors('phone');
    $this->assertGuest();
});

test('sme login fails with non-existent phone', function () {
    $response = $this->post(route('login'), [
        'profile' => 'sme',
        'phone' => '999999999',
        'password' => 'password',
        'country_code' => 'SN',
    ]);

    $response->assertSessionHasErrors('phone');
    $this->assertGuest();
});

test('inactive sme user cannot login', function () {
    User::factory()->inactive()->create([
        'phone' => '+221771234567',
        'profile_type' => 'sme',
    ]);

    $response = $this->post(route('login'), [
        'profile' => 'sme',
        'phone' => '771234567',
        'password' => 'password',
        'country_code' => 'SN',
    ]);

    $response->assertSessionHasErrors('phone');
    $this->assertGuest();
});

test('unverified sme user is redirected to otp page', function () {
    User::factory()->unverified()->create([
        'phone' => '+221771234567',
        'profile_type' => 'sme',
    ]);

    $response = $this->post(route('login'), [
        'profile' => 'sme',
        'phone' => '771234567',
        'password' => 'password',
        'country_code' => 'SN',
    ]);

    $response->assertRedirect(route('sme.auth.otp'));
    $this->assertAuthenticated();
});

// ─── Profil Cabinet ───────────────────────────────────────────────────────────

test('accountant can login with email and password', function () {
    $user = User::factory()->accountantFirm()->create([
        'email' => 'firm@example.com',
        'phone' => '+221771234567',
    ]);

    $response = $this->post(route('login'), [
        'profile' => 'accountant',
        'email' => 'firm@example.com',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('accountant login is case-insensitive on email', function () {
    User::factory()->accountantFirm()->create([
        'email' => 'firm@example.com',
        'phone' => '+221771234567',
    ]);

    $response = $this->post(route('login'), [
        'profile' => 'accountant',
        'email' => 'FIRM@Example.COM',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();
});

test('sme cannot login via the accountant profile', function () {
    User::factory()->create([
        'email' => 'sme@example.com',
        'phone' => '+221771234567',
        'profile_type' => 'sme',
    ]);

    $response = $this->post(route('login'), [
        'profile' => 'accountant',
        'email' => 'sme@example.com',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('accountant login fails with wrong password', function () {
    User::factory()->accountantFirm()->create([
        'email' => 'firm@example.com',
    ]);

    $response = $this->post(route('login'), [
        'profile' => 'accountant',
        'email' => 'firm@example.com',
        'password' => 'wrong',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('accountant login fails with unknown email', function () {
    $response = $this->post(route('login'), [
        'profile' => 'accountant',
        'email' => 'unknown@example.com',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('inactive accountant cannot login', function () {
    User::factory()->accountantFirm()->inactive()->create([
        'email' => 'firm@example.com',
    ]);

    $response = $this->post(route('login'), [
        'profile' => 'accountant',
        'email' => 'firm@example.com',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('accountant login rejects invalid email format', function () {
    $this->post(route('login'), [
        'profile' => 'accountant',
        'email' => 'not-an-email',
        'password' => 'password',
    ])->assertSessionHasErrors('email');
});

// ─── Validation du toggle ─────────────────────────────────────────────────────

test('login fails when profile is missing', function () {
    $this->post(route('login'), [
        'phone' => '771234567',
        'country_code' => 'SN',
        'password' => 'password',
    ])->assertSessionHasErrors('profile');
});

test('login fails when profile value is invalid', function () {
    $this->post(route('login'), [
        'profile' => 'admin',
        'email' => 'foo@bar.com',
        'password' => 'password',
    ])->assertSessionHasErrors('profile');
});

test('login with profile=sme but missing phone fails validation', function () {
    $this->post(route('login'), [
        'profile' => 'sme',
        'email' => 'foo@bar.com',
        'password' => 'password',
    ])->assertSessionHasErrors(['phone', 'country_code']);
});

test('login with profile=accountant but missing email fails validation', function () {
    $this->post(route('login'), [
        'profile' => 'accountant',
        'phone' => '771234567',
        'country_code' => 'SN',
        'password' => 'password',
    ])->assertSessionHasErrors('email');
});

test('login validates required password regardless of profile', function () {
    $this->post(route('login'), [
        'profile' => 'sme',
        'phone' => '771234567',
        'country_code' => 'SN',
    ])->assertSessionHasErrors('password');

    $this->post(route('login'), [
        'profile' => 'accountant',
        'email' => 'firm@example.com',
    ])->assertSessionHasErrors('password');
});

// ─── API ──────────────────────────────────────────────────────────────────────

test('api login returns json with token (sme profile)', function () {
    User::factory()->create([
        'phone' => '+221771234567',
        'profile_type' => 'sme',
    ]);

    $response = $this->postJson(route('api.auth.login'), [
        'profile' => 'sme',
        'phone' => '771234567',
        'password' => 'password',
        'country_code' => 'SN',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['message', 'user', 'token', 'phone_verified']);
});

test('api login fails with wrong credentials', function () {
    User::factory()->create([
        'phone' => '+221771234567',
        'profile_type' => 'sme',
    ]);

    $response = $this->postJson(route('api.auth.login'), [
        'profile' => 'sme',
        'phone' => '771234567',
        'password' => 'wrong',
        'country_code' => 'SN',
    ]);

    $response->assertUnauthorized();
});

// ─── Sécurité : rate limit, remember, session ────────────────────────────────

test('rate limiter blocks the 6th sme attempt within a minute', function () {
    User::factory()->create(['phone' => '+221771234567', 'profile_type' => 'sme']);

    foreach (range(1, 5) as $_) {
        $this->post(route('login'), [
            'profile' => 'sme',
            'phone' => '771234567',
            'password' => 'wrong',
            'country_code' => 'SN',
        ])->assertSessionHasErrors('phone');
    }

    $response = $this->post(route('login'), [
        'profile' => 'sme',
        'phone' => '771234567',
        'password' => 'wrong',
        'country_code' => 'SN',
    ]);

    $response->assertSessionHasErrors('phone');
    expect(session()->get('errors')->first('phone'))
        ->toContain('Trop de tentatives');
});

test('rate limiter blocks the 6th accountant attempt within a minute', function () {
    User::factory()->accountantFirm()->create(['email' => 'firm@example.com']);

    foreach (range(1, 5) as $_) {
        $this->post(route('login'), [
            'profile' => 'accountant',
            'email' => 'firm@example.com',
            'password' => 'wrong',
        ])->assertSessionHasErrors('email');
    }

    $response = $this->post(route('login'), [
        'profile' => 'accountant',
        'email' => 'firm@example.com',
        'password' => 'wrong',
    ]);

    $response->assertSessionHasErrors('email');
    expect(session()->get('errors')->first('email'))
        ->toContain('Trop de tentatives');
});

test('successful login clears the rate limiter', function () {
    User::factory()->create(['phone' => '+221771234567', 'profile_type' => 'sme']);

    foreach (range(1, 4) as $_) {
        $this->post(route('login'), [
            'profile' => 'sme',
            'phone' => '771234567',
            'password' => 'wrong',
            'country_code' => 'SN',
        ]);
    }

    $this->post(route('login'), [
        'profile' => 'sme',
        'phone' => '771234567',
        'password' => 'password',
        'country_code' => 'SN',
    ])->assertRedirect(route('pme.dashboard'));

    auth()->logout();

    foreach (range(1, 5) as $_) {
        $this->post(route('login'), [
            'profile' => 'sme',
            'phone' => '771234567',
            'password' => 'wrong',
            'country_code' => 'SN',
        ])->assertSessionHasErrors('phone');
    }
});

test('remember me sets the recaller cookie for sme profile', function () {
    User::factory()->create(['phone' => '+221771234567', 'profile_type' => 'sme']);

    $response = $this->post(route('login'), [
        'profile' => 'sme',
        'phone' => '771234567',
        'password' => 'password',
        'country_code' => 'SN',
        'remember' => '1',
    ]);

    $response->assertRedirect(route('pme.dashboard'));

    $cookieNames = array_map(
        fn (Cookie $c) => $c->getName(),
        $response->headers->getCookies()
    );

    expect($cookieNames)->toContain(Auth::guard('web')->getRecallerName());
});

test('remember me sets the recaller cookie for accountant profile', function () {
    User::factory()->accountantFirm()->create(['email' => 'firm@example.com']);

    $response = $this->post(route('login'), [
        'profile' => 'accountant',
        'email' => 'firm@example.com',
        'password' => 'password',
        'remember' => '1',
    ]);

    $response->assertRedirect(route('dashboard'));

    $cookieNames = array_map(
        fn (Cookie $c) => $c->getName(),
        $response->headers->getCookies()
    );

    expect($cookieNames)->toContain(Auth::guard('web')->getRecallerName());
});

test('login regenerates the session id', function () {
    User::factory()->create(['phone' => '+221771234567', 'profile_type' => 'sme']);

    $this->withSession(['_token' => 'fake-csrf']);
    $before = session()->getId();

    $this->post(route('login'), [
        'profile' => 'sme',
        'phone' => '771234567',
        'password' => 'password',
        'country_code' => 'SN',
    ])->assertRedirect(route('pme.dashboard'));

    expect(session()->getId())->not->toBe($before);
});

test('accountant intended url is honored after login', function () {
    User::factory()->accountantFirm()->create(['email' => 'firm@example.com']);

    $this->get('/compta/clients')->assertRedirect('/login');

    $response = $this->post(route('login'), [
        'profile' => 'accountant',
        'email' => 'firm@example.com',
        'password' => 'password',
    ]);

    $location = $response->headers->get('Location');
    expect($location)->toMatch('#^https?://[^/]+/compta(/|$)#');
});
