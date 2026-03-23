<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\Company;
use Modules\Shared\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'phone_verified_at' => now(),
    ]);

    $this->firm = Company::factory()->accountantFirm()->create();
    $this->firm->users()->attach($this->user->id, ['role' => 'owner']);
});

// ─── URL prefix verification ───────────────────────────────────────────────

it('generates dashboard URL with compta prefix', function () {
    expect(route('dashboard'))->toContain('/compta/dashboard');
});

it('generates clients index URL with compta prefix', function () {
    expect(route('clients.index'))->toContain('/compta/clients');
});

it('generates clients show URL with compta prefix', function () {
    $sme = Company::factory()->create();
    expect(route('clients.show', $sme))->toContain('/compta/clients/');
});

it('generates alerts index URL with compta prefix', function () {
    expect(route('alerts.index'))->toContain('/compta/alertes');
});

it('generates export index URL with compta prefix', function () {
    expect(route('export.index'))->toContain('/compta/exports');
});

it('generates commissions index URL with compta prefix', function () {
    expect(route('commissions.index'))->toContain('/compta/commissions');
});

it('generates invitations index URL with compta prefix', function () {
    expect(route('invitations.index'))->toContain('/compta/invitations');
});

it('generates profile edit URL with compta prefix', function () {
    expect(route('profile.edit'))->toContain('/compta/settings/profile');
});

it('generates appearance edit URL with compta prefix', function () {
    expect(route('appearance.edit'))->toContain('/compta/settings/appearance');
});

it('generates security edit URL with compta prefix', function () {
    expect(route('security.edit'))->toContain('/compta/settings/security');
});

// ─── Route accessibility (authenticated + verified) ────────────────────────

it('serves compta/dashboard for authenticated users', function () {
    $this->actingAs($this->user)
        ->get('/compta/dashboard')
        ->assertOk();
});

it('serves compta/clients for authenticated users', function () {
    $this->actingAs($this->user)
        ->get('/compta/clients')
        ->assertOk();
});

it('serves compta/alertes for authenticated users', function () {
    $this->actingAs($this->user)
        ->get('/compta/alertes')
        ->assertOk();
});

it('serves compta/exports for authenticated users', function () {
    $this->actingAs($this->user)
        ->get('/compta/exports')
        ->assertOk();
});

it('serves compta/commissions for authenticated users', function () {
    $this->actingAs($this->user)
        ->get('/compta/commissions')
        ->assertOk();
});

it('serves compta/invitations for authenticated users', function () {
    $this->actingAs($this->user)
        ->get('/compta/invitations')
        ->assertOk();
});

it('serves compta/settings/profile for authenticated users', function () {
    $this->actingAs($this->user)
        ->get('/compta/settings/profile')
        ->assertOk();
});

it('serves compta/settings/appearance for authenticated users', function () {
    $this->actingAs($this->user)
        ->get('/compta/settings/appearance')
        ->assertOk();
});

it('serves compta/settings/security for authenticated users', function () {
    $this->actingAs($this->user)
        ->get('/compta/settings/security')
        ->assertOk();
});

it('redirects compta/settings to compta/settings/profile', function () {
    $this->actingAs($this->user)
        ->get('/compta/settings')
        ->assertRedirect('/compta/settings/profile');
});

// ─── Old URLs without prefix should return 404 ────────────────────────────

it('returns 404 for old /dashboard URL', function () {
    $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertNotFound();
});

it('returns 404 for old /clients URL', function () {
    $this->actingAs($this->user)
        ->get('/clients')
        ->assertNotFound();
});

it('returns 404 for old /alertes URL', function () {
    $this->actingAs($this->user)
        ->get('/alertes')
        ->assertNotFound();
});

it('returns 404 for old /export-groupe URL', function () {
    $this->actingAs($this->user)
        ->get('/export-groupe')
        ->assertNotFound();
});

it('returns 404 for old /commissions URL', function () {
    $this->actingAs($this->user)
        ->get('/commissions')
        ->assertNotFound();
});

it('returns 404 for old /invitations URL', function () {
    $this->actingAs($this->user)
        ->get('/invitations')
        ->assertNotFound();
});

it('returns 404 for old /settings/profile URL', function () {
    $this->actingAs($this->user)
        ->get('/settings/profile')
        ->assertNotFound();
});

// ─── Authentication required ───────────────────────────────────────────────

it('redirects unauthenticated users from compta routes to login', function () {
    $this->get('/compta/dashboard')->assertRedirect(route('login'));
    $this->get('/compta/clients')->assertRedirect(route('login'));
    $this->get('/compta/alertes')->assertRedirect(route('login'));
    $this->get('/compta/exports')->assertRedirect(route('login'));
    $this->get('/compta/commissions')->assertRedirect(route('login'));
    $this->get('/compta/invitations')->assertRedirect(route('login'));
});

// ─── Fortify home config ───────────────────────────────────────────────────

it('has fortify home configured to compta/dashboard', function () {
    expect(config('fortify.home'))->toBe('/compta/dashboard');
});
