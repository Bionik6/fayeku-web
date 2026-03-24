<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\Company;
use Modules\Shared\Models\User;

uses(RefreshDatabase::class);

test('guests are redirected to login when accessing pme dashboard', function () {
    $this->get(route('pme.dashboard'))
        ->assertRedirect(route('login'));
});

test('sme user can visit the pme dashboard', function () {
    $user = User::factory()->create(['profile_type' => 'sme']);

    $this->actingAs($user)
        ->get(route('pme.dashboard'))
        ->assertOk();
});

test('accountant user cannot access pme routes', function () {
    $user = User::factory()->accountantFirm()->create();

    $this->actingAs($user)
        ->get(route('pme.dashboard'))
        ->assertForbidden();
});

test('sme user cannot access compta routes', function () {
    $user = User::factory()->create(['profile_type' => 'sme']);

    $this->actingAs($user)
        ->get(route('pme.dashboard'))
        ->assertOk();
});

test('pme dashboard sidebar renders pme navigation', function () {
    $user = User::factory()->create(['profile_type' => 'sme']);

    $response = $this->actingAs($user)
        ->get(route('pme.dashboard'));

    $response->assertOk();
    $response->assertSeeInOrder([
        'Tableau de bord',
        'Factures et devis',
        'Clients',
        'Recouvrement et relance',
        'Trésorerie',
        'Paramètres',
        'Aide & Support',
        'Déconnexion',
    ]);
    $response->assertSee('data-test="logout-button"', false);
});

test('pme sidebar shows the company name in header', function () {
    $user = User::factory()->create(['profile_type' => 'sme']);

    $company = Company::factory()->create(['type' => 'sme', 'name' => 'Ma Super PME']);
    $company->users()->attach($user->id, ['role' => 'owner']);

    $response = $this->actingAs($user)
        ->get(route('pme.dashboard'));

    $response->assertOk();
    $response->assertSee('Ma Super PME');
});

test('pme sidebar shows fayeku pme logo label', function () {
    $user = User::factory()->create(['profile_type' => 'sme']);

    $response = $this->actingAs($user)
        ->get(route('pme.dashboard'));

    $response->assertOk();
    $response->assertSee('PME');
});

test('sme user is redirected to pme dashboard after otp verification', function () {
    $user = User::factory()->unverified()->create([
        'phone' => '+221771234567',
        'profile_type' => 'sme',
    ]);

    createOtpCode('+221771234567', '654321');

    $this->actingAs($user)
        ->withSession(['otp_phone' => '+221771234567'])
        ->post(route('auth.otp.verify'), ['code' => '654321'])
        ->assertRedirect(route('pme.dashboard'));
});

test('all pme pages are accessible to sme user', function (string $routeName) {
    $user = User::factory()->create(['profile_type' => 'sme']);

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertOk();
})->with([
    'pme.dashboard',
    'pme.invoices.index',
    'pme.clients.index',
    'pme.collection.index',
    'pme.treasury.index',
    'pme.support.index',
    'pme.settings.index',
]);
