<?php

use App\Mail\Compta\AccountantActivationLinkMail;
use App\Models\Compta\AccountantLead;
use App\Services\Compta\AccountantLeadActivator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function activateLead(): array
{
    Mail::fake();

    $lead = AccountantLead::create([
        'first_name' => 'Mamadou',
        'last_name' => 'Diallo',
        'firm' => 'Cabinet Diallo & Associés',
        'email' => 'mamadou@diallo-compta.sn',
        'country_code' => 'SN',
        'phone' => '+221771234567',
        'region' => 'Dakar',
        'portfolio_size' => '1 à 20 dossiers',
        'message' => 'Centraliser les factures de mes clients PME.',
    ]);

    app(AccountantLeadActivator::class)->activate($lead);

    /** @var AccountantActivationLinkMail $captured */
    $captured = null;
    Mail::assertSent(AccountantActivationLinkMail::class, function ($mail) use (&$captured) {
        $captured = $mail;

        return true;
    });

    return [$lead->fresh(), $captured->token];
}

test('GET activation page renders with valid token', function () {
    [$lead, $token] = activateLead();

    test()->get(route('accountant.activation', $token))
        ->assertOk()
        ->assertSee($lead->firm)
        ->assertSee($lead->email);
});

test('CGU checkbox uses the shared auth-checkbox classes (admin pattern)', function () {
    [, $token] = activateLead();

    $response = test()->get(route('accountant.activation', $token))->assertOk();

    // Le checkbox CGU doit suivre le même pattern que login.blade.php :
    // wrapper auth-checkbox-row + auth-checkbox-wrap + input.auth-checkbox + svg.auth-checkbox-icon.
    // Sans ces classes, on retombe sur un input checkbox natif moche.
    $response
        ->assertSee('class="auth-checkbox-row', false)
        ->assertSee('class="auth-checkbox-wrap', false)
        ->assertSee('class="auth-checkbox"', false)
        ->assertSee('class="auth-checkbox-icon"', false);
});

test('submit button uses the shared auth-button class', function () {
    [, $token] = activateLead();

    test()->get(route('accountant.activation', $token))
        ->assertOk()
        // Le bouton "Activer mon compte" doit utiliser .auth-button — qui porte
        // cursor-pointer + le branding Fayeku partagé avec le reste de l'auth.
        ->assertSee('<button type="submit" class="auth-button">', false);
});

test('auth-button definition keeps cursor-pointer', function () {
    // Garde-fou côté source : cursor-pointer doit rester appliqué à .auth-button
    // (sinon le navigateur affiche le curseur par défaut sur le <button>, ce
    // qui déroute les utilisateurs).
    $css = file_get_contents(resource_path('css/app.css'));
    expect($css)->toMatch('/\.auth-button\s*\{[^}]*cursor-pointer[^}]*\}/s');
});

test('GET with invalid token redirects to login with friendly flash', function () {
    test()->get(route('accountant.activation', 'totally-bogus-token'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', fn ($msg) => str_contains($msg, "n'est plus valide"));
});

test('GET with expired token redirects to login with friendly flash', function () {
    [$lead, $token] = activateLead();
    $lead->forceFill(['activation_token_expires_at' => now()->subMinute()])->save();

    test()->get(route('accountant.activation', $token))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', fn ($msg) => str_contains($msg, "n'est plus valide"));
});

test('GET with already-used token redirects to login (no hard 404 for the cabinet)', function () {
    // Scénario réel : le cabinet ré-ouvre l'email et reclique sur le lien après
    // avoir activé son compte ailleurs (autre device, cookies expirés). On ne
    // lui montre pas un 404 brutal — on le guide vers /login.
    [$lead, $token] = activateLead();

    test()->post(route('accountant.activation.process', $token), [
        'password' => 'P@ssword123!',
        'password_confirmation' => 'P@ssword123!',
        'cgu_accepted' => '1',
    ])->assertRedirect(route('dashboard'));

    test()->post('/logout');

    // Maintenant déconnecté, on revisite l'URL avec le même token (déjà utilisé).
    test()->get(route('accountant.activation', $token))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', fn ($msg) => str_contains($msg, "n'est plus valide"));
});

test('POST activates the user, logs them in, and redirects to compta dashboard', function () {
    [$lead, $token] = activateLead();

    $response = test()->post(route('accountant.activation.process', $token), [
        'password' => 'P@ssword123!',
        'password_confirmation' => 'P@ssword123!',
        'cgu_accepted' => '1',
    ]);

    $response->assertRedirect(route('dashboard'));
    test()->assertAuthenticatedAs($lead->user);

    $user = $lead->user->fresh();
    expect($user->is_active)->toBeTrue();
    expect($user->email_verified_at)->not->toBeNull();
    expect($user->phone_verified_at)->not->toBeNull();
    expect(Hash::check('P@ssword123!', $user->password))->toBeTrue();

    $lead->refresh();
    expect($lead->activation_token_hash)->toBeNull();
    expect($lead->activation_token_expires_at)->toBeNull();
});

test('POST without accepting CGU fails validation', function () {
    [, $token] = activateLead();

    test()->post(route('accountant.activation.process', $token), [
        'password' => 'P@ssword123!',
        'password_confirmation' => 'P@ssword123!',
    ])->assertSessionHasErrors(['cgu_accepted']);

    test()->assertGuest();
});

test('POST with too short password fails validation', function () {
    [, $token] = activateLead();

    test()->post(route('accountant.activation.process', $token), [
        'password' => 'short',
        'password_confirmation' => 'short',
        'cgu_accepted' => '1',
    ])->assertSessionHasErrors(['password']);
});

test('POSTing again with a used token redirects to login (no second activation)', function () {
    [, $token] = activateLead();

    test()->post(route('accountant.activation.process', $token), [
        'password' => 'P@ssword123!',
        'password_confirmation' => 'P@ssword123!',
        'cgu_accepted' => '1',
    ])->assertRedirect(route('dashboard'));

    test()->post('/logout');

    test()->post(route('accountant.activation.process', $token), [
        'password' => 'AnotherP@ss123!',
        'password_confirmation' => 'AnotherP@ss123!',
        'cgu_accepted' => '1',
    ])
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', fn ($msg) => str_contains($msg, "n'est plus valide"));
});
