<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use App\Models\Auth\Company;
use App\Models\Shared\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->accountantFirm()->create([
        'phone_verified_at' => now(),
    ]);

    $firm = Company::factory()->accountantFirm()->create();
    $firm->users()->attach($this->user->id, ['role' => 'owner']);
});

// ─── Navigation & rendering ──────────────────────────────────────────────

it('redirects unauthenticated users to login', function () {
    $this->get(route('support.index'))->assertRedirect(route('login'));
});

it('renders the support page for authenticated users', function () {
    $this->actingAs($this->user)
        ->get(route('support.index'))
        ->assertOk()
        ->assertSee('Aide & Support');
});

it('generates support URL with compta prefix', function () {
    expect(route('support.index'))->toContain('/compta/support');
});

// ─── Guide accordion ─────────────────────────────────────────────────────

it('opens a guide when toggled', function () {
    Livewire::actingAs($this->user)
        ->test('pages::compta.support.index')
        ->assertSet('openGuide', null)
        ->call('toggleGuide', 0)
        ->assertSet('openGuide', 0);
});

it('closes an open guide when toggled again', function () {
    Livewire::actingAs($this->user)
        ->test('pages::compta.support.index')
        ->call('toggleGuide', 1)
        ->assertSet('openGuide', 1)
        ->call('toggleGuide', 1)
        ->assertSet('openGuide', null);
});

// ─── FAQ accordion ───────────────────────────────────────────────────────

it('opens a FAQ item when toggled', function () {
    Livewire::actingAs($this->user)
        ->test('pages::compta.support.index')
        ->assertSet('openFaq', null)
        ->call('toggleFaq', 0)
        ->assertSet('openFaq', 0);
});

it('closes an open FAQ item when toggled again', function () {
    Livewire::actingAs($this->user)
        ->test('pages::compta.support.index')
        ->call('toggleFaq', 2)
        ->assertSet('openFaq', 2)
        ->call('toggleFaq', 2)
        ->assertSet('openFaq', null);
});

it('switches to a different FAQ item when another is toggled', function () {
    Livewire::actingAs($this->user)
        ->test('pages::compta.support.index')
        ->call('toggleFaq', 0)
        ->assertSet('openFaq', 0)
        ->call('toggleFaq', 3)
        ->assertSet('openFaq', 3);
});

// ─── Contact form ─────────────────────────────────────────────────────────

it('validates required contact form fields', function () {
    Livewire::actingAs($this->user)
        ->test('pages::compta.support.index')
        ->call('submitContact')
        ->assertHasErrors(['subject', 'category', 'message']);
});

it('validates minimum message length', function () {
    Livewire::actingAs($this->user)
        ->test('pages::compta.support.index')
        ->set('subject', 'Mon sujet')
        ->set('category', 'Clients')
        ->set('message', 'Court')
        ->call('submitContact')
        ->assertHasErrors(['message']);
});

it('sets contactSent to true after valid submission', function () {
    Livewire::actingAs($this->user)
        ->test('pages::compta.support.index')
        ->set('subject', 'Problème avec mon export')
        ->set('category', 'Export comptable')
        ->set('message', 'Bonjour, je rencontre une erreur lors de la génération de mon export Sage 100.')
        ->call('submitContact')
        ->assertHasNoErrors()
        ->assertSet('contactSent', true);
});

it('adds the request to the requests list after submission', function () {
    Livewire::actingAs($this->user)
        ->test('pages::compta.support.index')
        ->assertSet('requests', [])
        ->set('subject', 'Problème avec mon export')
        ->set('category', 'Export comptable')
        ->set('message', 'Bonjour, je rencontre une erreur lors de la génération de mon export Sage 100.')
        ->call('submitContact')
        ->assertCount('requests', 1);
});

it('prepends new requests so the most recent appears first', function () {
    $component = Livewire::actingAs($this->user)->test('pages::compta.support.index');

    $component
        ->set('subject', 'Première demande')
        ->set('category', 'Clients')
        ->set('message', 'Un message suffisamment long pour passer la validation.')
        ->call('submitContact')
        ->call('resetContact')
        ->set('subject', 'Deuxième demande')
        ->set('category', 'Alertes')
        ->set('message', 'Un autre message suffisamment long pour passer la validation.')
        ->call('submitContact');

    expect($component->get('requests')[0]['subject'])->toBe('Deuxième demande');
    expect($component->get('requests')[1]['subject'])->toBe('Première demande');
});

it('resets form fields after valid submission', function () {
    Livewire::actingAs($this->user)
        ->test('pages::compta.support.index')
        ->set('subject', 'Mon sujet de test')
        ->set('category', 'Clients')
        ->set('message', 'Un message suffisamment long pour passer la validation.')
        ->call('submitContact')
        ->assertSet('subject', '')
        ->assertSet('category', '')
        ->assertSet('message', '');
});

it('resets contactSent flag when resetContact is called', function () {
    Livewire::actingAs($this->user)
        ->test('pages::compta.support.index')
        ->set('subject', 'Mon sujet de test')
        ->set('category', 'Clients')
        ->set('message', 'Un message suffisamment long pour passer la validation.')
        ->call('submitContact')
        ->assertSet('contactSent', true)
        ->call('resetContact')
        ->assertSet('contactSent', false);
});
