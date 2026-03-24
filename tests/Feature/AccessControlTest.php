<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Shared\Models\User;

uses(RefreshDatabase::class);

// ─── Compta routes : accès réservé aux comptables ─────────────────────────────

test('sme user gets 403 on compta dashboard', function () {
    $user = User::factory()->create(['profile_type' => 'sme']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertForbidden();
});

test('sme user gets 403 on compta clients', function () {
    $user = User::factory()->create(['profile_type' => 'sme']);

    $this->actingAs($user)
        ->get(route('clients.index'))
        ->assertForbidden();
});

test('sme user gets 403 on compta alerts', function () {
    $user = User::factory()->create(['profile_type' => 'sme']);

    $this->actingAs($user)
        ->get(route('alerts.index'))
        ->assertForbidden();
});

test('sme user gets 403 on compta exports', function () {
    $user = User::factory()->create(['profile_type' => 'sme']);

    $this->actingAs($user)
        ->get(route('export.index'))
        ->assertForbidden();
});

test('sme user gets 403 on compta commissions', function () {
    $user = User::factory()->create(['profile_type' => 'sme']);

    $this->actingAs($user)
        ->get(route('commissions.index'))
        ->assertForbidden();
});

test('sme user gets 403 on compta invitations', function () {
    $user = User::factory()->create(['profile_type' => 'sme']);

    $this->actingAs($user)
        ->get(route('invitations.index'))
        ->assertForbidden();
});

test('sme user gets 403 on compta support', function () {
    $user = User::factory()->create(['profile_type' => 'sme']);

    $this->actingAs($user)
        ->get(route('support.index'))
        ->assertForbidden();
});

test('sme user gets 403 on compta settings', function () {
    $user = User::factory()->create(['profile_type' => 'sme']);

    $this->actingAs($user)
        ->get(route('settings.index'))
        ->assertForbidden();
});

// ─── PME routes : accès réservé aux PMEs ─────────────────────────────────────

test('accountant user gets 403 on pme dashboard', function () {
    $user = User::factory()->accountantFirm()->create();

    $this->actingAs($user)
        ->get(route('pme.dashboard'))
        ->assertForbidden();
});

test('accountant user gets 403 on pme invoices', function () {
    $user = User::factory()->accountantFirm()->create();

    $this->actingAs($user)
        ->get(route('pme.invoices.index'))
        ->assertForbidden();
});

test('accountant user gets 403 on pme clients', function () {
    $user = User::factory()->accountantFirm()->create();

    $this->actingAs($user)
        ->get(route('pme.clients.index'))
        ->assertForbidden();
});

test('accountant user gets 403 on pme collection', function () {
    $user = User::factory()->accountantFirm()->create();

    $this->actingAs($user)
        ->get(route('pme.collection.index'))
        ->assertForbidden();
});

test('accountant user gets 403 on pme treasury', function () {
    $user = User::factory()->accountantFirm()->create();

    $this->actingAs($user)
        ->get(route('pme.treasury.index'))
        ->assertForbidden();
});

test('accountant user gets 403 on pme support', function () {
    $user = User::factory()->accountantFirm()->create();

    $this->actingAs($user)
        ->get(route('pme.support.index'))
        ->assertForbidden();
});

test('accountant user gets 403 on pme settings', function () {
    $user = User::factory()->accountantFirm()->create();

    $this->actingAs($user)
        ->get(route('pme.settings.index'))
        ->assertForbidden();
});

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
    'pme.support.index',
    'pme.settings.index',
]);
