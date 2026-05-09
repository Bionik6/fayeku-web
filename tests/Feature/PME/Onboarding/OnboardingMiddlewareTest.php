<?php

use App\Models\Auth\Company;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeSme(?array $userAttrs = null, ?array $companyAttrs = null): array
{
    $user = User::factory()->create(array_merge(['profile_type' => 'sme'], $userAttrs ?? []));
    // Par défaut, on simule un onboarding en cours (setup_completed_at = null)
    // pour ce fichier qui teste justement la redirection.
    $company = Company::factory()
        ->pendingOnboarding()
        ->create(array_merge(['type' => 'sme'], $companyAttrs ?? []));
    $company->users()->attach($user->id, ['role' => 'owner']);

    return [$user, $company];
}

test('un PME avec onboarding incomplet est redirigé du dashboard vers /pme/onboarding', function () {
    [$user, $company] = makeSme();
    expect($company->setup_completed_at)->toBeNull();

    $this->actingAs($user)
        ->get('/pme/dashboard')
        ->assertRedirect(route('pme.onboarding'));
});

test('un PME avec onboarding terminé peut accéder au dashboard', function () {
    [$user] = makeSme(companyAttrs: ['setup_completed_at' => now()]);

    $this->actingAs($user)
        ->get('/pme/dashboard')
        ->assertOk();
});

test('un PME avec onboarding incomplet peut accéder à /pme/onboarding sans boucle', function () {
    [$user] = makeSme();

    $this->actingAs($user)
        ->get('/pme/onboarding')
        ->assertOk();
});

test('un PME ayant terminé son onboarding qui visite /pme/onboarding est renvoyé au dashboard', function () {
    [$user] = makeSme(companyAttrs: ['setup_completed_at' => now()]);

    $this->actingAs($user)
        ->get('/pme/onboarding')
        ->assertRedirect(route('pme.dashboard'));
});

test('un cabinet comptable n\'est jamais redirigé vers l\'onboarding PME', function () {
    $user = User::factory()->accountantFirm()->create();
    $firm = Company::factory()->accountantFirm()->create();
    $firm->users()->attach($user->id, ['role' => 'owner']);

    $this->actingAs($user)
        ->get('/compta/dashboard')
        ->assertOk();
});

test('la route auth.logout reste accessible pendant l\'onboarding', function () {
    [$user] = makeSme();

    $this->actingAs($user)
        ->post(route('auth.logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('email verification redirige un PME sans onboarding vers /pme/onboarding', function () {
    $user = User::factory()->unverified()->create(['email' => 'sme@example.com', 'profile_type' => 'sme']);
    $company = Company::factory()->pendingOnboarding()->create(['type' => 'sme']);
    $company->users()->attach($user->id, ['role' => 'owner']);
    createOtpCode('sme@example.com', '123456');

    $this->actingAs($user)
        ->withSession(['verification_email' => 'sme@example.com'])
        ->post(route('auth.verify-email.verify'), ['code' => '123456'])
        ->assertRedirect(route('pme.onboarding'));
});

test('email verification redirige un PME avec onboarding terminé vers le dashboard', function () {
    $user = User::factory()->unverified()->create(['email' => 'sme@example.com', 'profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme', 'setup_completed_at' => now()]);
    $company->users()->attach($user->id, ['role' => 'owner']);
    createOtpCode('sme@example.com', '123456');

    $this->actingAs($user)
        ->withSession(['verification_email' => 'sme@example.com'])
        ->post(route('auth.verify-email.verify'), ['code' => '123456'])
        ->assertRedirect(route('pme.dashboard'));
});

test('les autres routes PME (clients, factures, settings) sont aussi protégées', function () {
    [$user] = makeSme();

    $this->actingAs($user)
        ->get('/pme/clients')
        ->assertRedirect(route('pme.onboarding'));

    $this->actingAs($user)
        ->get('/pme/invoices')
        ->assertRedirect(route('pme.onboarding'));

    $this->actingAs($user)
        ->get('/pme/settings')
        ->assertRedirect(route('pme.onboarding'));
});
