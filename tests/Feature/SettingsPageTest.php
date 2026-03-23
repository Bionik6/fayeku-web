<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Auth\Models\Company;
use Modules\Auth\Models\Subscription;
use Modules\Shared\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'phone_verified_at' => now(),
    ]);

    $this->firm = Company::factory()->accountantFirm()->create([
        'email' => 'contact@test-firm.sn',
        'address' => '10 Rue de Test',
        'city' => 'Dakar',
        'ninea' => 'SN999999999',
        'rccm' => 'SN-DKR-2024-B-99999',
    ]);
    $this->firm->users()->attach($this->user->id, ['role' => 'owner']);
});

// ─── Navigation & rendering ──────────────────────────────────────────────

it('redirects unauthenticated users to login', function () {
    $this->get(route('settings.index'))->assertRedirect(route('login'));
});

it('renders settings page with profile section by default', function () {
    $this->actingAs($this->user)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertSee('Profil du cabinet');
});

it('switches to account section', function () {
    Livewire::actingAs($this->user)
        ->test('pages::settings.index')
        ->call('setSection', 'account')
        ->assertSet('activeSection', 'account')
        ->assertSee('Informations du compte');
});

it('switches to notifications section', function () {
    Livewire::actingAs($this->user)
        ->test('pages::settings.index')
        ->call('setSection', 'notifications')
        ->assertSet('activeSection', 'notifications')
        ->assertSee('Notifications');
});

it('switches to export section', function () {
    Livewire::actingAs($this->user)
        ->test('pages::settings.index')
        ->call('setSection', 'export')
        ->assertSet('activeSection', 'export')
        ->assertSee('Export comptable');
});

it('switches to billing section', function () {
    Livewire::actingAs($this->user)
        ->test('pages::settings.index')
        ->call('setSection', 'billing')
        ->assertSet('activeSection', 'billing')
        ->assertSee('Facturation');
});

// ─── Firm profile ────────────────────────────────────────────────────────

it('saves firm profile information', function () {
    Livewire::actingAs($this->user)
        ->test('pages::settings.index')
        ->set('firmName', 'Cabinet Modifié')
        ->set('firmEmail', 'new@firm.sn')
        ->set('firmCity', 'Saint-Louis')
        ->call('saveFirmProfile')
        ->assertHasNoErrors();

    $this->firm->refresh();

    expect($this->firm->name)->toBe('Cabinet Modifié');
    expect($this->firm->email)->toBe('new@firm.sn');
    expect($this->firm->city)->toBe('Saint-Louis');
});

it('validates required firm profile fields', function () {
    Livewire::actingAs($this->user)
        ->test('pages::settings.index')
        ->set('firmName', '')
        ->call('saveFirmProfile')
        ->assertHasErrors(['firmName']);
});

// ─── User account ────────────────────────────────────────────────────────

it('saves user account information', function () {
    Livewire::actingAs($this->user)
        ->test('pages::settings.index')
        ->call('setSection', 'account')
        ->set('firstName', 'Amadou')
        ->set('lastName', 'Diop')
        ->set('userEmail', 'amadou@test.sn')
        ->call('saveAccount')
        ->assertHasNoErrors();

    $this->user->refresh();

    expect($this->user->first_name)->toBe('Amadou');
    expect($this->user->last_name)->toBe('Diop');
    expect($this->user->email)->toBe('amadou@test.sn');
});

it('updates password successfully', function () {
    Livewire::actingAs($this->user)
        ->test('pages::settings.index')
        ->call('setSection', 'account')
        ->set('currentPassword', 'password')
        ->set('newPassword', 'new-secure-password-123')
        ->set('newPasswordConfirmation', 'new-secure-password-123')
        ->call('updatePassword')
        ->assertHasNoErrors();
});

it('rejects wrong current password', function () {
    Livewire::actingAs($this->user)
        ->test('pages::settings.index')
        ->call('setSection', 'account')
        ->set('currentPassword', 'wrong-password')
        ->set('newPassword', 'new-secure-password-123')
        ->set('newPasswordConfirmation', 'new-secure-password-123')
        ->call('updatePassword')
        ->assertHasErrors(['currentPassword']);
});

// ─── Billing section ─────────────────────────────────────────────────────

it('shows subscription info in billing section', function () {
    Subscription::create([
        'company_id' => $this->firm->id,
        'plan_slug' => 'basique',
        'price_paid' => 10_000,
        'billing_cycle' => 'monthly',
        'status' => 'active',
        'current_period_start' => now()->startOfMonth(),
        'current_period_end' => now()->startOfMonth()->addMonth(),
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::settings.index')
        ->call('setSection', 'billing')
        ->assertSet('activeSection', 'billing')
        ->assertSee('Basique');
});

// ─── URL structure ───────────────────────────────────────────────────────

it('generates settings URL with compta prefix', function () {
    expect(route('settings.index'))->toContain('/compta/settings');
});

it('returns 404 for old settings/profile URL', function () {
    $this->actingAs($this->user)
        ->get('/compta/settings/profile')
        ->assertNotFound();
});
