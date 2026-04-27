<?php

use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Cookie;

uses(RefreshDatabase::class);

test('/login redirects to /sme/login', function () {
    $this->get('/login')->assertRedirect('/sme/login');
});

test('the named login route resolves to /login (Laravel auth middleware target)', function () {
    expect(route('login', [], false))->toBe('/login');
});

test('the auth middleware redirects guests to /login → /sme/login', function () {
    $this->get('/pme/dashboard')
        ->assertRedirect('/login');

    $this->get('/login')
        ->assertRedirect('/sme/login');
});

test('chooser blade is gone', function () {
    expect(view()->exists('pages.auth.login-chooser'))->toBeFalse();
});

test('sme login page can be rendered', function () {
    $this->get(route('sme.auth.login'))
        ->assertOk();
});

test('sme user is redirected to pme dashboard after login', function () {
    $user = User::factory()->create([
        'phone' => '+221771234567',
        'profile_type' => 'sme',
    ]);

    $response = $this->post(route('sme.auth.login.submit'), [
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

    $response = $this->post(route('sme.auth.login.submit'), [
        'phone' => '+221 77 123 45 67',
        'password' => 'password',
        'country_code' => 'SN',
    ]);

    $response->assertRedirect(route('pme.dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('accountant cannot login via sme portal', function () {
    User::factory()->accountantFirm()->create([
        'phone' => '+221771234567',
    ]);

    $response = $this->post(route('sme.auth.login.submit'), [
        'phone' => '771234567',
        'password' => 'password',
        'country_code' => 'SN',
    ]);

    $response->assertSessionHasErrors('phone');
    $this->assertGuest();
});

test('user cannot login with wrong password', function () {
    User::factory()->create([
        'phone' => '+221771234567',
        'profile_type' => 'sme',
    ]);

    $response = $this->post(route('sme.auth.login.submit'), [
        'phone' => '771234567',
        'password' => 'wrong-password',
        'country_code' => 'SN',
    ]);

    $response->assertSessionHasErrors('phone');
    $this->assertGuest();
});

test('user cannot login with non-existent phone', function () {
    $response = $this->post(route('sme.auth.login.submit'), [
        'phone' => '999999999',
        'password' => 'password',
        'country_code' => 'SN',
    ]);

    $response->assertSessionHasErrors('phone');
    $this->assertGuest();
});

test('inactive user cannot login', function () {
    User::factory()->inactive()->create([
        'phone' => '+221771234567',
        'profile_type' => 'sme',
    ]);

    $response = $this->post(route('sme.auth.login.submit'), [
        'phone' => '771234567',
        'password' => 'password',
        'country_code' => 'SN',
    ]);

    $response->assertSessionHasErrors('phone');
    $this->assertGuest();
});

test('unverified user is redirected to otp page', function () {
    User::factory()->unverified()->create([
        'phone' => '+221771234567',
        'profile_type' => 'sme',
    ]);

    $response = $this->post(route('sme.auth.login.submit'), [
        'phone' => '771234567',
        'password' => 'password',
        'country_code' => 'SN',
    ]);

    $response->assertRedirect(route('sme.auth.otp'));
    $this->assertAuthenticated();
});

test('api login returns json with token', function () {
    User::factory()->create([
        'phone' => '+221771234567',
        'profile_type' => 'sme',
    ]);

    $response = $this->postJson(route('api.auth.login'), [
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
        'phone' => '771234567',
        'password' => 'wrong',
        'country_code' => 'SN',
    ]);

    $response->assertUnauthorized();
});

test('legacy /forgot-password redirects to /sme/forgot-password', function () {
    $this->get('/forgot-password')->assertRedirect('/sme/forgot-password');
});

test('legacy /register redirects to /sme/register', function () {
    $this->get('/register')->assertRedirect('/sme/register');
});

test('legacy /reset-password redirects to /sme/reset-password', function () {
    $this->get('/reset-password')->assertRedirect('/sme/reset-password');
});

test('legacy /otp redirects to /sme/otp', function () {
    $this->get('/otp')->assertRedirect('/sme/otp');
});

test('rate limiter blocks the 6th attempt within a minute', function () {
    User::factory()->create(['phone' => '+221771234567', 'profile_type' => 'sme']);

    foreach (range(1, 5) as $_) {
        $this->post(route('sme.auth.login.submit'), [
            'phone' => '771234567',
            'password' => 'wrong',
            'country_code' => 'SN',
        ])->assertSessionHasErrors('phone');
    }

    $response = $this->post(route('sme.auth.login.submit'), [
        'phone' => '771234567',
        'password' => 'wrong',
        'country_code' => 'SN',
    ]);

    $response->assertSessionHasErrors('phone');
    expect(session()->get('errors')->first('phone'))
        ->toContain('Trop de tentatives');
});

test('successful login clears the rate limiter', function () {
    User::factory()->create(['phone' => '+221771234567', 'profile_type' => 'sme']);

    foreach (range(1, 4) as $_) {
        $this->post(route('sme.auth.login.submit'), [
            'phone' => '771234567',
            'password' => 'wrong',
            'country_code' => 'SN',
        ]);
    }

    $this->post(route('sme.auth.login.submit'), [
        'phone' => '771234567',
        'password' => 'password',
        'country_code' => 'SN',
    ])->assertRedirect(route('pme.dashboard'));

    auth()->logout();

    foreach (range(1, 5) as $_) {
        $this->post(route('sme.auth.login.submit'), [
            'phone' => '771234567',
            'password' => 'wrong',
            'country_code' => 'SN',
        ])->assertSessionHasErrors('phone');
    }
});

test('remember me sets the recaller cookie', function () {
    User::factory()->create(['phone' => '+221771234567', 'profile_type' => 'sme']);

    $response = $this->post(route('sme.auth.login.submit'), [
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

test('login regenerates the session id', function () {
    User::factory()->create(['phone' => '+221771234567', 'profile_type' => 'sme']);

    $this->withSession(['_token' => 'fake-csrf']);
    $before = session()->getId();

    $this->post(route('sme.auth.login.submit'), [
        'phone' => '771234567',
        'password' => 'password',
        'country_code' => 'SN',
    ])->assertRedirect(route('pme.dashboard'));

    expect(session()->getId())->not->toBe($before);
});
