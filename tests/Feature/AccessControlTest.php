<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Shared\Models\User;

uses(RefreshDatabase::class);

// ─── Compta routes : un SME est redirigé vers son dashboard ─────────────────

test('sme user is redirected to pme dashboard from compta routes', function (string $routeName) {
    $user = User::factory()->create(['profile_type' => 'sme']);

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertRedirect(route('pme.dashboard'));
})->with([
    'dashboard',
    'clients.index',
    'alerts.index',
    'export.index',
    'commissions.index',
    'invitations.index',
    'support.index',
    'settings.index',
]);

// ─── PME routes : un comptable est redirigé vers son dashboard ──────────────

test('accountant user is redirected to compta dashboard from pme routes', function (string $routeName) {
    $user = User::factory()->accountantFirm()->create();

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertRedirect(route('dashboard'));
})->with([
    'pme.dashboard',
    'pme.invoices.index',
    'pme.clients.index',
    'pme.collection.index',
    'pme.treasury.index',
    'pme.treasury.export',
    'pme.support.index',
    'pme.settings.index',
]);

// ─── Chaque type accède à ses propres routes ─────────────────────────────────

test('accountant user can access compta dashboard', function () {
    $user = User::factory()->accountantFirm()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('sme user can access pme dashboard', function () {
    $user = User::factory()->create(['profile_type' => 'sme']);

    $this->actingAs($user)
        ->get(route('pme.dashboard'))
        ->assertOk();
});

// ─── Utilisateurs non authentifiés ───────────────────────────────────────────

test('unauthenticated user is redirected to login from compta routes', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with([
    'dashboard',
    'alerts.index',
    'clients.index',
    'export.index',
    'commissions.index',
    'invitations.index',
    'support.index',
    'settings.index',
]);

test('unauthenticated user is redirected to login from pme routes', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with([
    'pme.dashboard',
    'pme.invoices.index',
    'pme.clients.index',
    'pme.collection.index',
    'pme.treasury.index',
    'pme.treasury.export',
    'pme.support.index',
    'pme.settings.index',
]);
