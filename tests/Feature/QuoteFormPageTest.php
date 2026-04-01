<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Auth\Models\Company;
use Modules\PME\Clients\Models\Client;
use Modules\PME\Invoicing\Enums\QuoteStatus;
use Modules\PME\Invoicing\Models\Quote;
use Modules\PME\Invoicing\Models\QuoteLine;
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

function createDraftQuote(Company $company): Quote
{
    $client = Client::factory()->create(['company_id' => $company->id]);

    $quote = Quote::unguarded(fn () => Quote::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'DEV-TEST-'.fake()->unique()->numerify('###'),
        'currency' => 'XOF',
        'status' => QuoteStatus::Draft->value,
        'issued_at' => now(),
        'valid_until' => now()->addDays(30),
        'subtotal' => 50_000,
        'tax_amount' => 9_000,
        'total' => 59_000,
        'discount' => 0,
    ]));

    QuoteLine::query()->create([
        'quote_id' => $quote->id,
        'description' => 'Prestation devis',
        'quantity' => 1,
        'unit_price' => 50_000,
        'tax_rate' => 18,
        'total' => 50_000,
    ]);

    return $quote;
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

test('un utilisateur ne peut pas éditer le devis d\'une autre entreprise', function () {
    ['user' => $user] = createSmeUserForQuoteForm();
    $otherCompany = Company::factory()->create(['type' => 'sme']);
    $quote = createDraftQuote($otherCompany);

    $this->actingAs($user)
        ->get(route('pme.quotes.edit', $quote))
        ->assertForbidden();
});

// ─── Create page ─────────────────────────────────────────────────────────────

test('la page de création de devis initialise isEditing à false', function () {
    ['user' => $user] = createSmeUserForQuoteForm();

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.form')
        ->assertSet('isEditing', false);
});

test('la page de création de devis génère une référence automatique', function () {
    ['user' => $user] = createSmeUserForQuoteForm();

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.form')
        ->assertNotSet('reference', '');
});

test('la page de création de devis initialise les dates par défaut', function () {
    ['user' => $user] = createSmeUserForQuoteForm();

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.form')
        ->assertSet('issuedAt', now()->format('Y-m-d'))
        ->assertSet('validUntil', now()->addDays(30)->format('Y-m-d'));
});

test('la page de création de devis a une ligne vide par défaut', function () {
    ['user' => $user] = createSmeUserForQuoteForm();

    $component = Livewire::actingAs($user)
        ->test('pages::pme.quotes.form');

    expect($component->get('lines'))->toHaveCount(1);
});

// ─── Edit page ───────────────────────────────────────────────────────────────

test('la page d\'édition de devis charge les données existantes', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForQuoteForm();
    $quote = createDraftQuote($company);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.form', ['quote' => $quote])
        ->assertSet('isEditing', true)
        ->assertSet('reference', $quote->reference)
        ->assertSet('clientId', $quote->client_id);
});

test('la page d\'édition de devis charge les lignes existantes', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForQuoteForm();
    $quote = createDraftQuote($company);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.quotes.form', ['quote' => $quote]);

    expect($component->get('lines'))->toHaveCount(1)
        ->and($component->get('lines.0.description'))->toBe('Prestation devis');
});

test('on peut mettre à jour un devis brouillon existant', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForQuoteForm();
    $quote = createDraftQuote($company);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.form', ['quote' => $quote])
        ->set('lines.0.description', 'Service modifié')
        ->set('lines.0.quantity', 2)
        ->set('lines.0.unit_price', 30_000)
        ->call('saveDraft')
        ->assertHasNoErrors();

    $quote->refresh();

    expect($quote->lines->first()->description)->toBe('Service modifié')
        ->and($quote->subtotal)->toBe(60_000);
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
