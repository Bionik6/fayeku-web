<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Shared\Models\User;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->accountantFirm()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard shell renders the compta navigation', function () {
    $user = User::factory()->accountantFirm()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertOk();
    $response->assertSeeInOrder([
        'Dashboard',
        'Clients',
        'Export Groupé',
        'Commissions',
        'Invitations',
        'Paramètres',
        'Aide & Support',
        'Déconnexion',
    ]);
    $response->assertSee('aria-current="page"', false);
    $response->assertSee('data-test="logout-button"', false);
});

test('settings entry is highlighted on the settings screen', function () {
    $user = User::factory()->accountantFirm()->create();
    $this->actingAs($user);

    $response = $this->get(route('settings.index'));

    $response->assertOk();
    $response->assertSee('Paramètres', false);
    $response->assertSee('aria-current="page"', false);
});
