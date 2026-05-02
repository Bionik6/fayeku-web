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

test('GET /login renders the email-only login page', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Email')
        ->assertSee('Mot de passe')
        ->assertSee('Recevoir un lien de connexion par email');
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

// ─── Login par email ──────────────────────────────────────────────────────────

test('sme can login with email and lands on the pme dashboard', function () {
    $user = User::factory()->create([
        'email' => 'sme@example.com',
        'profile_type' => 'sme',
    ]);

    $response = $this->post(route('login'), [
        'email' => 'sme@example.com',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('pme.dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('accountant can login with email and lands on the compta dashboard', function () {
    $user = User::factory()->accountantFirm()->create([
        'email' => 'firm@example.com',
        'phone' => '+221771234567',
    ]);

    $response = $this->post(route('login'), [
        'email' => 'firm@example.com',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('login is case-insensitive on email', function () {
    User::factory()->accountantFirm()->create([
        'email' => 'firm@example.com',
    ]);

    $response = $this->post(route('login'), [
        'email' => 'FIRM@Example.COM',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();
});

// ─── Échecs ────────────────────────────────────────────────────────────────────

test('login fails with wrong password', function () {
    User::factory()->create([
        'email' => 'sme@example.com',
        'profile_type' => 'sme',
    ]);

    $response = $this->post(route('login'), [
        'email' => 'sme@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('login fails with non-existent email', function () {
    $response = $this->post(route('login'), [
        'email' => 'unknown@example.com',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('login rejects a phone-shaped identifier (email-only for now)', function () {
    User::factory()->create([
        'phone' => '+221771234567',
        'email' => 'sme@example.com',
        'profile_type' => 'sme',
    ]);

    $response = $this->post(route('login'), [
        'email' => '771234567',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('inactive user cannot login', function () {
    User::factory()->inactive()->create([
        'email' => 'sme@example.com',
        'profile_type' => 'sme',
    ]);

    $response = $this->post(route('login'), [
        'email' => 'sme@example.com',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('login lets unverified email users in (no OTP gate)', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'sme@example.com',
        'profile_type' => 'sme',
    ]);

    $response = $this->post(route('login'), [
        'email' => 'sme@example.com',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('pme.dashboard'));
    $this->assertAuthenticatedAs($user);
});

// ─── Validation ───────────────────────────────────────────────────────────────

test('login fails when email is missing', function () {
    $this->post(route('login'), [
        'password' => 'password',
    ])->assertSessionHasErrors('email');
});

test('login fails when password is missing', function () {
    $this->post(route('login'), [
        'email' => 'foo@bar.com',
    ])->assertSessionHasErrors('password');
});

// ─── API ──────────────────────────────────────────────────────────────────────

test('api login returns json with token', function () {
    User::factory()->create([
        'email' => 'sme@example.com',
        'profile_type' => 'sme',
    ]);

    $response = $this->postJson(route('api.auth.login'), [
        'email' => 'sme@example.com',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['message', 'user', 'token', 'email_verified']);
});

test('api login returns 401 with wrong credentials', function () {
    User::factory()->create([
        'email' => 'sme@example.com',
        'profile_type' => 'sme',
    ]);

    $response = $this->postJson(route('api.auth.login'), [
        'email' => 'sme@example.com',
        'password' => 'wrong',
    ]);

    $response->assertUnauthorized();
});

// ─── Rate limit, remember, session ────────────────────────────────────────────

test('rate limiter blocks the 6th attempt within a minute', function () {
    User::factory()->create(['email' => 'sme@example.com', 'profile_type' => 'sme']);

    foreach (range(1, 5) as $_) {
        $this->post(route('login'), [
            'email' => 'sme@example.com',
            'password' => 'wrong',
        ])->assertSessionHasErrors('email');
    }

    $response = $this->post(route('login'), [
        'email' => 'sme@example.com',
        'password' => 'wrong',
    ]);

    $response->assertSessionHasErrors('email');
    expect(session()->get('errors')->first('email'))
        ->toContain('Trop de tentatives');
});

test('successful login clears the rate limiter', function () {
    User::factory()->create(['email' => 'sme@example.com', 'profile_type' => 'sme']);

    foreach (range(1, 4) as $_) {
        $this->post(route('login'), [
            'email' => 'sme@example.com',
            'password' => 'wrong',
        ]);
    }

    $this->post(route('login'), [
        'email' => 'sme@example.com',
        'password' => 'password',
    ])->assertRedirect(route('pme.dashboard'));

    auth()->logout();

    foreach (range(1, 5) as $_) {
        $this->post(route('login'), [
            'email' => 'sme@example.com',
            'password' => 'wrong',
        ])->assertSessionHasErrors('email');
    }
});

test('remember me sets the recaller cookie', function () {
    User::factory()->create(['email' => 'sme@example.com', 'profile_type' => 'sme']);

    $response = $this->post(route('login'), [
        'email' => 'sme@example.com',
        'password' => 'password',
        'remember' => '1',
    ]);

    $response->assertRedirect(route('pme.dashboard'));

    $cookieNames = array_map(
        fn (Cookie $c) => $c->getName(),
        $response->headers->getCookies()
    );

    expect($cookieNames)->toContain(Auth::guard('web')->getRecallerName());
});

test('login regenerates the session id', function () {
    User::factory()->create(['email' => 'sme@example.com', 'profile_type' => 'sme']);

    $this->withSession(['_token' => 'fake-csrf']);
    $before = session()->getId();

    $this->post(route('login'), [
        'email' => 'sme@example.com',
        'password' => 'password',
    ])->assertRedirect(route('pme.dashboard'));

    expect(session()->getId())->not->toBe($before);
});

test('accountant intended url is honored after login', function () {
    User::factory()->accountantFirm()->create(['email' => 'firm@example.com']);

    $this->get('/compta/clients')->assertRedirect('/login');

    $response = $this->post(route('login'), [
        'email' => 'firm@example.com',
        'password' => 'password',
    ]);

    $location = $response->headers->get('Location');
    expect($location)->toMatch('#^https?://[^/]+/compta(/|$)#');
});
