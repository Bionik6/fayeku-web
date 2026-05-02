<?php

use App\Mail\Auth\MagicLinkMail;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

test('GET /auth/magic-link renders the request page', function () {
    $this->get(route('auth.magic-link.request'))
        ->assertOk()
        ->assertSee('lien magique')
        ->assertSee('Email');
});

test('submitting a known email queues a magic link email', function () {
    Mail::fake();

    $user = User::factory()->create(['email' => 'sme@example.com']);

    $this->post(route('auth.magic-link.send'), ['email' => 'sme@example.com'])
        ->assertRedirect();

    Mail::assertSent(MagicLinkMail::class, function (MagicLinkMail $mail) use ($user) {
        return $mail->hasTo($user->email)
            && str_contains($mail->magicUrl, '/auth/magic-link/consume/');
    });
});

test('submitting an unknown email returns the generic message without sending', function () {
    Mail::fake();

    $this->post(route('auth.magic-link.send'), ['email' => 'unknown@example.com'])
        ->assertRedirect();

    Mail::assertNothingSent();
});

test('inactive users do not receive a magic link', function () {
    Mail::fake();

    User::factory()->inactive()->create(['email' => 'inactive@example.com']);

    $this->post(route('auth.magic-link.send'), ['email' => 'inactive@example.com'])
        ->assertRedirect();

    Mail::assertNothingSent();
});

test('a valid signed magic link logs the user in and lands on the right dashboard', function () {
    $user = User::factory()->create([
        'email' => 'sme@example.com',
        'profile_type' => 'sme',
    ]);

    $signedUrl = URL::temporarySignedRoute(
        'auth.magic-link.consume',
        now()->addMinutes(15),
        ['user' => $user->id],
    );

    $this->get($signedUrl)
        ->assertRedirect(route('pme.dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('a magic link consumption marks email_verified_at when null', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'sme@example.com',
        'profile_type' => 'sme',
    ]);

    $signedUrl = URL::temporarySignedRoute(
        'auth.magic-link.consume',
        now()->addMinutes(15),
        ['user' => $user->id],
    );

    $this->get($signedUrl);

    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

test('a tampered magic link is rejected', function () {
    $user = User::factory()->create(['email' => 'sme@example.com']);

    $signedUrl = URL::temporarySignedRoute(
        'auth.magic-link.consume',
        now()->addMinutes(15),
        ['user' => $user->id],
    );

    // Strip the signature.
    $tampered = preg_replace('/(\?|&)signature=[^&]+/', '', $signedUrl);

    $this->get($tampered)
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('an expired magic link is rejected', function () {
    $user = User::factory()->create(['email' => 'sme@example.com']);

    $signedUrl = URL::temporarySignedRoute(
        'auth.magic-link.consume',
        now()->subMinutes(1),
        ['user' => $user->id],
    );

    $this->get($signedUrl)
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('magic link rate limiter blocks the 4th send within 15 minutes', function () {
    Mail::fake();

    User::factory()->create(['email' => 'sme@example.com']);

    foreach (range(1, 3) as $_) {
        $this->post(route('auth.magic-link.send'), ['email' => 'sme@example.com'])
            ->assertRedirect();
    }

    $this->post(route('auth.magic-link.send'), ['email' => 'sme@example.com'])
        ->assertStatus(429);
});
