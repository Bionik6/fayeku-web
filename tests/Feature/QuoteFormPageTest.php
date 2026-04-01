<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Auth\Models\Company;
use Modules\PME\Clients\Models\Client;
use Modules\PME\Invoicing\Enums\QuoteStatus;
use Modules\PME\Invoicing\Models\Quote;
use Modules\Shared\Models\User;

uses(RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function createSmeUserForQuoteForm(): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme']);
    $company->users()->attach($user->id, ['role' => 'owner']);

    return compact('user', 'company');
}

// ─── Accès & sécurité ─────────────────────────────────────────────────────────

test('un visiteur non authentifié est redirigé vers la connexion depuis la page création devis', function () {
    $this->get(route('pme.quotes.create'))
        ->assertRedirect(route('login'));
});

test('un utilisateur SME peut accéder à la page de création de devis', function () {
    ['user' => $user] = createSmeUserForQuoteForm();

    $this->actingAs($user)
        ->get(route('pme.quotes.create'))
        ->assertOk();
});

// ─── openSaveDraftModal ──────────────────────────────────────────────────────

test('openSaveDraftModal ouvre la modale si le formulaire est valide', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForQuoteForm();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.form')
        ->set('clientId', $client->id)
        ->set('lines.0.description', 'Prestation')
        ->set('lines.0.quantity', 1)
        ->set('lines.0.unit_price', 10_000)
        ->call('openSaveDraftModal')
        ->assertSet('showSaveDraftModal', true)
        ->assertHasNoErrors();
});

test('openSaveDraftModal ne ouvre pas la modale si le formulaire est invalide', function () {
    ['user' => $user] = createSmeUserForQuoteForm();

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.form')
        ->call('openSaveDraftModal')
        ->assertSet('showSaveDraftModal', false)
        ->assertHasErrors(['clientId']);
});

test('confirmSaveDraft sauvegarde le devis et redirige vers la liste en brouillon', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForQuoteForm();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.form')
        ->set('clientId', $client->id)
        ->set('lines.0.description', 'Prestation')
        ->set('lines.0.quantity', 1)
        ->set('lines.0.unit_price', 10_000)
        ->call('openSaveDraftModal')
        ->call('confirmSaveDraft')
        ->assertRedirect(route('pme.quotes.index').'?statut=draft');

    expect(Quote::query()->where('company_id', $company->id)->first())
        ->not->toBeNull()
        ->status->toBe(QuoteStatus::Draft);
});

test('confirmSaveDraft flash un message de succès pour le toaster', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForQuoteForm();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.form')
        ->set('clientId', $client->id)
        ->set('lines.0.description', 'Prestation')
        ->set('lines.0.quantity', 1)
        ->set('lines.0.unit_price', 10_000)
        ->call('openSaveDraftModal')
        ->call('confirmSaveDraft');

    expect(session('success'))->toBe('Brouillon enregistré avec succès.');
});

test('saveDraft direct (sans modale) reste accessible pour les appels internes', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForQuoteForm();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.form')
        ->set('clientId', $client->id)
        ->set('lines.0.description', 'Prestation')
        ->set('lines.0.quantity', 1)
        ->set('lines.0.unit_price', 10_000)
        ->call('saveDraft')
        ->assertHasNoErrors()
        ->assertNoRedirect();

    expect(Quote::query()->where('company_id', $company->id)->first())->not->toBeNull();
});
