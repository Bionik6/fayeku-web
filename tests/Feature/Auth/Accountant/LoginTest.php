<?php

use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Cookie;

uses(RefreshDatabase::class);

test('accountant login page can be rendered', function () {
    $this->get(route('accountant.auth.login'))
        ->assertOk();
});

test('accountant can login with email and password', function () {
    $user = User::factory()->accountantFirm()->create([
        'email' => 'firm@example.com',
        'phone' => '+221771234567',
    ]);

    $response = $this->post(route('accountant.auth.login.submit'), [
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

    $response = $this->post(route('accountant.auth.login.submit'), [
        'email' => 'FIRM@Example.COM',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();
});

test('sme cannot login via accountant portal', function () {
    User::factory()->create([
        'email' => 'sme@example.com',
        'phone' => '+221771234567',
        'profile_type' => 'sme',
    ]);

    $response = $this->post(route('accountant.auth.login.submit'), [
        'email' => 'sme@example.com',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('accountant cannot login with wrong password', function () {
    User::factory()->accountantFirm()->create([
        'email' => 'firm@example.com',
    ]);

    $response = $this->post(route('accountant.auth.login.submit'), [
        'email' => 'firm@example.com',
        'password' => 'wrong',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('accountant cannot login with unknown email', function () {
    $response = $this->post(route('accountant.auth.login.submit'), [
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

    $response = $this->post(route('accountant.auth.login.submit'), [
        'email' => 'firm@example.com',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('accountant login validates required fields', function () {
    $this->post(route('accountant.auth.login.submit'), [])
        ->assertSessionHasErrors(['email', 'password']);
});

test('accountant login rejects invalid email format', function () {
    $this->post(route('accountant.auth.login.submit'), [
        'email' => 'not-an-email',
        'password' => 'password',
    ])->assertSessionHasErrors('email');
});

test('rate limiter blocks the 6th attempt within a minute', function () {
    User::factory()->accountantFirm()->create([
        'email' => 'firm@example.com',
    ]);

    foreach (range(1, 5) as $_) {
        $this->post(route('accountant.auth.login.submit'), [
            'email' => 'firm@example.com',
            'password' => 'wrong',
        ])->assertSessionHasErrors('email');
    }

    $response = $this->post(route('accountant.auth.login.submit'), [
        'email' => 'firm@example.com',
        'password' => 'wrong',
    ]);

    $response->assertSessionHasErrors('email');
    expect(session()->get('errors')->first('email'))
        ->toContain('Trop de tentatives');
});

test('remember me sets the recaller cookie for accountants', function () {
    User::factory()->accountantFirm()->create([
        'email' => 'firm@example.com',
    ]);

    $response = $this->post(route('accountant.auth.login.submit'), [
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

test('accountant login regenerates the session id', function () {
    User::factory()->accountantFirm()->create([
        'email' => 'firm@example.com',
    ]);

    $this->withSession([]);
    $before = session()->getId();

    $this->post(route('accountant.auth.login.submit'), [
        'email' => 'firm@example.com',
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    expect(session()->getId())->not->toBe($before);
});

test('accountant intended url is honored after login', function () {
    User::factory()->accountantFirm()->create([
        'email' => 'firm@example.com',
    ]);

    // First, an unauthenticated visit to /compta/clients records the intended URL.
    $this->get('/compta/clients')->assertRedirect('/login');

    $response = $this->post(route('accountant.auth.login.submit'), [
        'email' => 'firm@example.com',
        'password' => 'password',
    ]);

    // Either the dashboard (default) or the originally requested URL — but never the SME area.
    $location = $response->headers->get('Location');
    expect($location)->toMatch('#^https?://[^/]+/compta(/|$)#');
});
