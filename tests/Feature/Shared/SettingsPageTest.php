<?php

use App\Models\Auth\Company;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->accountantFirm()->create([
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
        ->test('pages::compta.settings.index')
        ->call('setSection', 'account')
        ->assertSet('activeSection', 'account')
        ->assertSee('Compte & sécurité');
});

// ─── Firm profile ────────────────────────────────────────────────────────

it('saves firm profile information', function () {
    Livewire::actingAs($this->user)
        ->test('pages::compta.settings.index')
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
        ->test('pages::compta.settings.index')
        ->set('firmName', '')
        ->call('saveFirmProfile')
        ->assertHasErrors(['firmName']);
});

// ─── User account ────────────────────────────────────────────────────────

it('saves user account information', function () {
    Livewire::actingAs($this->user)
        ->test('pages::compta.settings.index')
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
        ->test('pages::compta.settings.index')
        ->call('setSection', 'account')
        ->set('currentPassword', 'password')
        ->set('newPassword', 'new-secure-password-123')
        ->set('newPasswordConfirmation', 'new-secure-password-123')
        ->call('updatePassword')
        ->assertHasNoErrors();
});

it('rejects wrong current password', function () {
    Livewire::actingAs($this->user)
        ->test('pages::compta.settings.index')
        ->call('setSection', 'account')
        ->set('currentPassword', 'wrong-password')
        ->set('newPassword', 'new-secure-password-123')
        ->set('newPasswordConfirmation', 'new-secure-password-123')
        ->call('updatePassword')
        ->assertHasErrors(['currentPassword']);
});

// ─── Billing section ─────────────────────────────────────────────────────

// ─── URL structure ───────────────────────────────────────────────────────

it('generates settings URL with compta prefix', function () {
    expect(route('settings.index'))->toContain('/compta/settings');
});

it('returns 404 for old settings/profile URL', function () {
    $this->actingAs($this->user)
        ->get('/compta/settings/profile')
        ->assertNotFound();
});

// ─── Demo mode (FAYEKU_DEMO_MODE) ────────────────────────────────────────

it('bloque la modification du profil du cabinet en mode démo', function () {
    config()->set('fayeku.demo', true);
    $originalName = $this->firm->name;

    Livewire::actingAs($this->user)
        ->test('pages::compta.settings.index')
        ->set('firmName', 'Mutation interdite')
        ->call('saveFirmProfile');

    expect($this->firm->fresh()->name)->toBe($originalName);
});

it('bloque la modification du compte utilisateur en mode démo (compta)', function () {
    config()->set('fayeku.demo', true);
    $originalFirstName = $this->user->first_name;

    Livewire::actingAs($this->user)
        ->test('pages::compta.settings.index')
        ->call('setSection', 'account')
        ->set('firstName', 'Mutation')
        ->set('lastName', 'Interdite')
        ->call('saveAccount');

    expect($this->user->fresh()->first_name)->toBe($originalFirstName);
});

it('bloque la modification du mot de passe en mode démo (compta)', function () {
    config()->set('fayeku.demo', true);
    $originalHash = $this->user->password;

    Livewire::actingAs($this->user)
        ->test('pages::compta.settings.index')
        ->call('setSection', 'account')
        ->set('currentPassword', 'password')
        ->set('newPassword', 'New-Secure-Pass-123!')
        ->set('newPasswordConfirmation', 'New-Secure-Pass-123!')
        ->call('updatePassword')
        ->assertSet('currentPassword', '')
        ->assertSet('newPassword', '')
        ->assertSet('newPasswordConfirmation', '');

    expect($this->user->fresh()->password)->toBe($originalHash);
});

it('affiche le bandeau lecture seule dans les deux sections compta en mode démo', function () {
    config()->set('fayeku.demo', true);

    foreach (['profile', 'account'] as $section) {
        Livewire::actingAs($this->user)
            ->test('pages::compta.settings.index')
            ->call('setSection', $section)
            ->assertSee('Édition désactivée en mode démonstration');
    }
});

it('n\'affiche pas le bandeau lecture seule hors mode démo (compta)', function () {
    config()->set('fayeku.demo', false);

    Livewire::actingAs($this->user)
        ->test('pages::compta.settings.index')
        ->assertDontSee('Édition désactivée en mode démonstration');
});
