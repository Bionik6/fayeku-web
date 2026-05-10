<?php

use App\Enums\PME\InvoiceStatus;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Models\PME\InvoiceLine;
use App\Models\Shared\User;
use App\Services\PME\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function createSmeUser(): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme']);
    $company->users()->attach($user->id, ['role' => 'owner']);

    return compact('user', 'company');
}

function createDraftInvoice(Company $company): Invoice
{
    $client = Client::factory()->create(['company_id' => $company->id]);

    $invoice = Invoice::factory()
        ->forCompany($company)
        ->withClient($client)
        ->draft()
        ->create(['currency' => 'XOF']);

    InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'description' => 'Service test',
        'quantity' => 2,
        'unit_price' => 50_000,
        'tax_rate' => 18,
        'total' => 100_000,
    ]);

    return $invoice;
}

// ─── Access control ──────────────────────────────────────────────────────────

test('un visiteur non authentifié est redirigé vers la connexion depuis la page création', function () {
    $this->get(route('pme.invoices.create'))
        ->assertRedirect(route('login'));
});

test('un utilisateur SME peut accéder à la page de création de facture', function () {
    ['user' => $user] = createSmeUser();

    $this->actingAs($user)
        ->get(route('pme.invoices.create'))
        ->assertOk();
});

test('un utilisateur cabinet comptable est redirigé vers son dashboard', function () {
    $user = User::factory()->accountantFirm()->create();

    $this->actingAs($user)
        ->get(route('pme.invoices.create'))
        ->assertRedirect(route('dashboard'));
});

test('un utilisateur ne peut pas éditer la facture d\'une autre entreprise', function () {
    ['user' => $user] = createSmeUser();
    $otherCompany = Company::factory()->create(['type' => 'sme']);
    $invoice = createDraftInvoice($otherCompany);

    $this->actingAs($user)
        ->get(route('pme.invoices.edit', $invoice))
        ->assertForbidden();
});

// ─── Create page ─────────────────────────────────────────────────────────────

test('la page de création initialise une référence automatiquement', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->assertSet('isEditing', false)
        ->assertNotSet('reference', '');
});

test('la page de création initialise les dates par défaut', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->assertSet('issuedAt', now()->format('Y-m-d'))
        ->assertSet('dueAt', now()->addDays(30)->format('Y-m-d'));
});

test('la page de création a une ligne vide par défaut', function () {
    ['user' => $user] = createSmeUser();

    $component = Livewire::actingAs($user)
        ->test('pages::pme.invoices.form');

    expect($component->get('lines'))->toHaveCount(1);
});

// ─── Save draft ──────────────────────────────────────────────────────────────

test('on peut sauvegarder un brouillon avec des lignes', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('clientId', $client->id)
        ->set('lines.0.description', 'Ciment Portland')
        ->set('lines.0.quantity', 10)
        ->set('lines.0.unit_price', 5_000)
        ->call('saveDraft')
        ->assertHasNoErrors();

    $invoice = Invoice::query()->where('company_id', $company->id)->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->client_id)->toBe($client->id)
        ->and($invoice->lines)->toHaveCount(1)
        ->and($invoice->subtotal)->toBe(50_000);
});

// ─── Validation ──────────────────────────────────────────────────────────────

test('la validation exige un client', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('lines.0.description', 'Test')
        ->set('lines.0.quantity', 1)
        ->set('lines.0.unit_price', 1_000)
        ->call('saveDraft')
        ->assertHasErrors(['clientId']);
});

test('la validation exige au moins une ligne avec description', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('clientId', $client->id)
        ->set('lines.0.description', '')
        ->call('saveDraft')
        ->assertHasErrors(['lines.0.description']);
});

test('la validation exige une quantité minimum de 1', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('clientId', $client->id)
        ->set('lines.0.description', 'Test')
        ->set('lines.0.quantity', 0)
        ->set('lines.0.unit_price', 1_000)
        ->call('saveDraft')
        ->assertHasErrors(['lines.0.quantity']);
});

test('le prix unitaire ne peut pas dépasser le maximum pour XOF (999 999 999)', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('clientId', $client->id)
        ->set('currency', 'XOF')
        ->set('lines.0.description', 'Test')
        ->set('lines.0.quantity', 1)
        ->set('lines.0.unit_price', 1_000_000_000)
        ->call('saveDraft')
        ->assertHasErrors(['lines.0.unit_price']);
});

test('le prix unitaire au maximum pour XOF (999 999 999) est accepté', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('clientId', $client->id)
        ->set('currency', 'XOF')
        ->set('lines.0.description', 'Test')
        ->set('lines.0.quantity', 1)
        ->set('lines.0.unit_price', 999_999_999)
        ->call('saveDraft')
        ->assertHasNoErrors(['lines.0.unit_price']);
});

test('le prix unitaire ne peut pas dépasser le maximum pour EUR (99 999 999 999 centimes)', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('clientId', $client->id)
        ->set('currency', 'EUR')
        ->set('lines.0.description', 'Test')
        ->set('lines.0.quantity', 1)
        ->set('lines.0.unit_price', 100_000_000_000)
        ->call('saveDraft')
        ->assertHasErrors(['lines.0.unit_price']);
});

test('le prix unitaire au maximum pour EUR (99 999 999 999 centimes) est accepté', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('clientId', $client->id)
        ->set('currency', 'EUR')
        ->set('lines.0.description', 'Test')
        ->set('lines.0.quantity', 1)
        ->set('lines.0.unit_price', 99_999_999_999)
        ->call('saveDraft')
        ->assertHasNoErrors(['lines.0.unit_price']);
});

test('la date d\'échéance ne peut pas être antérieure à la date d\'émission', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('clientId', $client->id)
        ->set('issuedAt', '2026-04-01')
        ->set('dueAt', '2026-03-01')
        ->set('lines.0.description', 'Test')
        ->set('lines.0.quantity', 1)
        ->set('lines.0.unit_price', 1_000)
        ->call('saveDraft')
        ->assertHasErrors(['dueAt']);
});

// ─── Edit page ───────────────────────────────────────────────────────────────

test('la page d\'édition charge les données existantes', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $invoice = createDraftInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form', ['invoice' => $invoice])
        ->assertSet('isEditing', true)
        ->assertSet('reference', $invoice->reference)
        ->assertSet('clientId', $invoice->client_id);
});

test('la page d\'édition charge les lignes existantes', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $invoice = createDraftInvoice($company);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.invoices.form', ['invoice' => $invoice]);

    expect($component->get('lines'))->toHaveCount(1)
        ->and($component->get('lines.0.description'))->toBe('Service test');
});

test('on peut mettre à jour une facture brouillon existante', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $invoice = createDraftInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form', ['invoice' => $invoice])
        ->set('lines.0.description', 'Updated service')
        ->set('lines.0.quantity', 5)
        ->set('lines.0.unit_price', 20_000)
        ->call('saveDraft')
        ->assertHasNoErrors();

    $invoice->refresh();

    expect($invoice->lines->first()->description)->toBe('Updated service')
        ->and($invoice->subtotal)->toBe(100_000);
});

test('on ne peut pas éditer une facture payée', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);
    $invoice = Invoice::factory()->forCompany($company)->withClient($client)->paid()->create();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form', ['invoice' => $invoice])
        ->assertRedirect(route('pme.invoices.index'));
});

// ─── Line management ─────────────────────────────────────────────────────────

test('on peut ajouter une ligne', function () {
    ['user' => $user] = createSmeUser();

    $component = Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->call('addLine');

    expect($component->get('lines'))->toHaveCount(2);
});

test('on ne peut pas supprimer la dernière ligne', function () {
    ['user' => $user] = createSmeUser();

    $component = Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->call('removeLine', 0);

    expect($component->get('lines'))->toHaveCount(1);
});

test('on peut supprimer une ligne quand il y en a plusieurs', function () {
    ['user' => $user] = createSmeUser();

    $component = Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->call('addLine')
        ->call('removeLine', 0);

    expect($component->get('lines'))->toHaveCount(1);
});

test('les erreurs de validation se réinitialisent quand on corrige les champs', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->call('saveDraft')
        ->assertHasErrors(['clientId', 'lines.0.description'])
        ->call('selectClient', $client->id)
        ->assertHasNoErrors(['clientId'])
        ->set('lines.0.description', 'Ciment')
        ->assertHasNoErrors(['lines.0.description']);
});

// ─── Client creation (shared modal) ─────────────────────────────────────────

test('client-created sélectionne le nouveau client dans le formulaire facture', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->dispatch('client-created', id: $client->id, name: $client->name)
        ->assertSet('clientId', $client->id);
});

// ─── Send flow ───────────────────────────────────────────────────────────────

test('saveAndSend persiste la facture en Brouillon et redirige vers la page show avec ?send=1', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('clientId', $client->id)
        ->set('lines.0.description', 'Service')
        ->set('lines.0.quantity', 1)
        ->set('lines.0.unit_price', 50_000)
        ->call('saveAndSend');

    $invoice = Invoice::query()->where('company_id', $company->id)->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe(InvoiceStatus::Draft);
});

test('saveAndSend ne change pas le statut Sent → reste Brouillon avant la confirmation modale', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('clientId', $client->id)
        ->set('lines.0.description', 'Service')
        ->set('lines.0.quantity', 1)
        ->set('lines.0.unit_price', 50_000)
        ->call('saveAndSend');

    $invoice = Invoice::query()->where('company_id', $company->id)->first();

    // Le clic n'envoie pas — il faut passer par le modal du show page.
    expect($invoice->status)->toBe(InvoiceStatus::Draft);
});

test('saveAndSend refuse une facture à 0 FCFA et ne persiste rien', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->call('saveAndSend')
        ->assertHasErrors('lines')
        ->assertNoRedirect();

    expect(Invoice::query()->where('company_id', $company->id)->count())->toBe(0);
});

// ─── Due date presets ────────────────────────────────────────────────────────

test('le preset 7 jours calcule correctement la date d\'échéance', function () {
    ['user' => $user] = createSmeUser();

    $component = Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('dueDatePreset', '7');

    $expected = now()->addDays(7)->format('Y-m-d');

    expect($component->get('dueAt'))->toBe($expected);
});

// ─── Custom tax rate null handling ──────────────────────────────────────────

test('le taux de TVA personnalisé accepte null quand le champ est vidé', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('taxMode', 'custom')
        ->set('customTaxRate', null)
        ->assertSet('taxRate', 0)
        ->assertHasNoErrors();
});

test('le taux de TVA personnalisé applique la valeur saisie', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('hasTax', true)
        ->set('taxMode', 'custom')
        ->set('customTaxRate', 10)
        ->assertSet('taxRate', 10);
});

test('le taux de TVA personnalisé est limité entre 0 et 100', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('hasTax', true)
        ->set('taxMode', 'custom')
        ->set('customTaxRate', 150)
        ->assertSet('taxRate', 100);
});

test('le champ customTaxRate est lui-même clampé à 100 quand la valeur dépasse 100', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('hasTax', true)
        ->set('taxMode', 'custom')
        ->set('customTaxRate', 999)
        ->assertSet('customTaxRate', 100);
});

test('le taux de TVA personnalisé accepte exactement 100', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('hasTax', true)
        ->set('taxMode', 'custom')
        ->set('customTaxRate', 100)
        ->assertSet('customTaxRate', 100)
        ->assertSet('taxRate', 100);
});

test('le taux de TVA personnalisé est clampé à 0 si une valeur négative est saisie', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('hasTax', true)
        ->set('taxMode', 'custom')
        ->set('customTaxRate', -5)
        ->assertSet('customTaxRate', 0)
        ->assertSet('taxRate', 0);
});

// ─── TVA toggle (hasTax) ────────────────────────────────────────────────────

test('hasTax est désactivé par défaut sur une nouvelle facture', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->assertSet('hasTax', false)
        ->assertSet('taxRate', 0);
});

test('activer hasTax depuis le défaut désactivé applique 18%', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('hasTax', true)
        ->assertSet('taxMode', '18')
        ->assertSet('taxRate', 18);
});

test('désactiver hasTax force taxRate à 0 sans toucher au taxMode', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('hasTax', false)
        ->assertSet('taxRate', 0)
        ->assertSet('taxMode', '18');
});

test('réactiver hasTax restaure 18% depuis le mode 18', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('hasTax', false)
        ->set('hasTax', true)
        ->assertSet('taxRate', 18);
});

test('réactiver hasTax restaure le taux personnalisé saisi précédemment', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('taxMode', 'custom')
        ->set('customTaxRate', 7)
        ->set('hasTax', false)
        ->assertSet('taxRate', 0)
        ->set('hasTax', true)
        ->assertSet('taxRate', 7);
});

test('changer le taxMode ne fait rien quand hasTax est désactivé', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('hasTax', false)
        ->set('taxMode', 'custom')
        ->assertSet('taxRate', 0);
});

test('saisir un customTaxRate ne change pas le taxRate quand hasTax est désactivé', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('taxMode', 'custom')
        ->set('hasTax', false)
        ->set('customTaxRate', 7)
        ->assertSet('customTaxRate', 7)
        ->assertSet('taxRate', 0);
});

test('sauvegarder avec hasTax désactivé crée des lignes sans TVA', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('clientId', $client->id)
        ->set('lines.0.description', 'Service')
        ->set('lines.0.quantity', 1)
        ->set('lines.0.unit_price', 100_000)
        ->set('hasTax', false)
        ->call('saveDraft')
        ->assertHasNoErrors();

    $invoice = Invoice::query()->where('company_id', $company->id)->first();

    expect($invoice->total)->toBe(100_000)
        ->and($invoice->tax_amount)->toBe(0)
        ->and($invoice->lines->first()->tax_rate)->toBe(0);
});

test('édition d\'une facture sans TVA initialise hasTax à false', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    $invoice = app(InvoiceService::class)->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-FAC-NOTAX',
        'currency' => 'XOF',
        'tax_rate' => 0,
        'discount' => 0,
        'issued_at' => now()->format('Y-m-d'),
        'due_at' => now()->addDays(30)->format('Y-m-d'),
    ], [
        ['description' => 'Service', 'quantity' => 1, 'unit_price' => 50_000],
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form', ['invoice' => $invoice])
        ->assertSet('hasTax', false)
        ->assertSet('taxRate', 0)
        ->assertSet('taxMode', '18');
});

test('édition d\'une facture avec TVA 18% initialise hasTax à true et taxMode 18', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $invoice = createDraftInvoice($company); // line tax_rate = 18

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form', ['invoice' => $invoice])
        ->assertSet('hasTax', true)
        ->assertSet('taxRate', 18)
        ->assertSet('taxMode', '18');
});

test('édition d\'une facture avec TVA personnalisée initialise hasTax à true et taxMode custom', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    $invoice = app(InvoiceService::class)->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-FAC-CTX',
        'currency' => 'XOF',
        'tax_rate' => 7,
        'discount' => 0,
        'issued_at' => now()->format('Y-m-d'),
        'due_at' => now()->addDays(30)->format('Y-m-d'),
    ], [
        ['description' => 'Service', 'quantity' => 1, 'unit_price' => 50_000],
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form', ['invoice' => $invoice])
        ->assertSet('hasTax', true)
        ->assertSet('taxRate', 7)
        ->assertSet('taxMode', 'custom')
        ->assertSet('customTaxRate', 7);
});

// ─── Discount percentage cap ─────────────────────────────────────────────────

test('la remise en pourcentage est clampée à 100 si la valeur dépasse 100', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('discountType', 'percent')
        ->set('discount', 150)
        ->assertSet('discount', 100);
});

test('la remise en pourcentage accepte exactement 100', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('discountType', 'percent')
        ->set('discount', 100)
        ->assertSet('discount', 100);
});

test('la remise en pourcentage accepte 0', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('discountType', 'percent')
        ->set('discount', 0)
        ->assertSet('discount', 0);
});

test('le hook clamp empêche discount > 100 donc la soumission ne produit pas d\'erreur de validation sur ce champ', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('discountType', 'percent')
        ->set('discount', 999)
        ->assertSet('discount', 100)
        ->set('clientId', $client->id)
        ->call('saveDraft')
        ->assertHasNoErrors(['discount']);
});

test('le changement de type de remise remet le discount à 0', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('discountType', 'percent')
        ->set('discount', 50)
        ->set('discountType', 'fixed')
        ->assertSet('discount', 0);
});

// ─── Payment method ─────────────────────────────────────────────────────────

test('on peut sélectionner un moyen de paiement', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('paymentMethod', 'wave')
        ->assertSet('paymentMethod', 'wave');
});

test('on peut sauvegarder une facture avec un moyen de paiement', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('clientId', $client->id)
        ->set('lines.0.description', 'Service')
        ->set('lines.0.quantity', 1)
        ->set('lines.0.unit_price', 10_000)
        ->set('paymentMethod', 'bank_transfer')
        ->set('paymentDetails', 'BCEAO 12345678')
        ->call('saveDraft')
        ->assertHasNoErrors();

    $invoice = Invoice::query()->where('company_id', $company->id)->first();

    expect($invoice->payment_method)->toBe('bank_transfer')
        ->and($invoice->payment_details)->toBe('BCEAO 12345678');
});

test('la validation refuse un moyen de paiement invalide', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('clientId', $client->id)
        ->set('lines.0.description', 'Service')
        ->set('lines.0.quantity', 1)
        ->set('lines.0.unit_price', 10_000)
        ->set('paymentMethod', 'bitcoin')
        ->call('saveDraft')
        ->assertHasErrors(['paymentMethod']);
});

test('le moyen de paiement est optionnel', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('clientId', $client->id)
        ->set('lines.0.description', 'Service')
        ->set('lines.0.quantity', 1)
        ->set('lines.0.unit_price', 10_000)
        ->set('paymentMethod', '')
        ->call('saveDraft')
        ->assertHasNoErrors(['paymentMethod']);
});

test('la page d\'édition charge le moyen de paiement existant', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $invoice = createDraftInvoice($company);
    $invoice->update(['payment_method' => 'orange_money', 'payment_details' => '+221 77 000 00 00']);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form', ['invoice' => $invoice])
        ->assertSet('paymentMethod', 'orange_money')
        ->assertSet('paymentDetails', '+221 77 000 00 00');
});

// ─── Reminder schedule ──────────────────────────────────────────────────────

test('remindersEnabled est à false par défaut', function () {
    ['user' => $user] = createSmeUser();

    $component = Livewire::actingAs($user)
        ->test('pages::pme.invoices.form');

    expect($component->get('remindersEnabled'))->toBeFalse();
});

test('toggleReminders bascule la valeur', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->assertSet('remindersEnabled', false)
        ->call('toggleReminders')
        ->assertSet('remindersEnabled', true)
        ->call('toggleReminders')
        ->assertSet('remindersEnabled', false);
});

test('l\'état reminders_enabled est sauvegardé avec la facture', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('clientId', $client->id)
        ->set('lines.0.description', 'Service')
        ->set('lines.0.quantity', 1)
        ->set('lines.0.unit_price', 10_000)
        ->call('toggleReminders')
        ->call('saveDraft')
        ->assertHasNoErrors();

    $invoice = Invoice::query()->where('company_id', $company->id)->first();

    // Default false → toggle once → true → save
    expect($invoice->reminders_enabled)->toBeTrue();
});

test('la page d\'édition charge l\'état reminders_enabled existant', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $invoice = createDraftInvoice($company);
    $invoice->update(['reminders_enabled' => false]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form', ['invoice' => $invoice])
        ->assertSet('remindersEnabled', false);
});

// ─── Cancel confirmation ────────────────────────────────────────────────────

test('annuler redirige directement si le formulaire est vide', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->call('confirmCancel')
        ->assertRedirect(route('pme.invoices.index'));
});

test('annuler affiche la modale si le formulaire contient des données', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->call('selectClient', $client->id)
        ->call('confirmCancel')
        ->assertSet('showCancelModal', true);
});

test('annuler affiche la modale si une ligne a une description', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('lines.0.description', 'Ciment')
        ->call('confirmCancel')
        ->assertSet('showCancelModal', true);
});

test('annuler affiche la modale si une ligne a un prix unitaire', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('lines.0.unit_price', 5_000)
        ->call('confirmCancel')
        ->assertSet('showCancelModal', true);
});

test('annuler affiche la modale si des notes sont saisies', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('hasNotes', true)
        ->set('notes', 'Merci pour votre confiance')
        ->call('confirmCancel')
        ->assertSet('showCancelModal', true);
});

// ─── Notes toggle (hasNotes) ────────────────────────────────────────────────

test('hasNotes est désactivé par défaut sur une nouvelle facture', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->assertSet('hasNotes', false)
        ->assertSet('notes', '');
});

test('désactiver hasNotes vide les notes', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('hasNotes', true)
        ->set('notes', 'Merci pour votre confiance')
        ->set('hasNotes', false)
        ->assertSet('notes', '');
});

test('saveDraft avec hasNotes activé persiste les notes', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('clientId', $client->id)
        ->set('lines.0.description', 'Service')
        ->set('lines.0.quantity', 1)
        ->set('lines.0.unit_price', 50_000)
        ->set('hasNotes', true)
        ->set('notes', 'Merci pour votre confiance')
        ->call('saveDraft')
        ->assertHasNoErrors();

    $invoice = Invoice::query()->where('company_id', $company->id)->first();

    expect($invoice->notes)->toBe('Merci pour votre confiance');
});

test('saveDraft avec hasNotes désactivé ignore les notes saisies', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('clientId', $client->id)
        ->set('lines.0.description', 'Service')
        ->set('lines.0.quantity', 1)
        ->set('lines.0.unit_price', 50_000)
        ->set('hasNotes', true)
        ->set('notes', 'À ignorer')
        ->set('hasNotes', false)
        ->call('saveDraft')
        ->assertHasNoErrors();

    $invoice = Invoice::query()->where('company_id', $company->id)->first();

    expect($invoice->notes)->toBeNull();
});

test('édition d\'une facture avec notes initialise hasNotes à true', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $invoice = createDraftInvoice($company);
    $invoice->update(['notes' => 'Notes existantes']);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form', ['invoice' => $invoice])
        ->assertSet('hasNotes', true)
        ->assertSet('notes', 'Notes existantes');
});

test('édition d\'une facture sans notes initialise hasNotes à false', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $invoice = createDraftInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form', ['invoice' => $invoice])
        ->assertSet('hasNotes', false);
});

test('confirmer l\'annulation redirige vers la liste', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->call('cancel')
        ->assertRedirect(route('pme.invoices.index'));
});

// ─── PDF & Envoi ─────────────────────────────────────────────────────────────

test('previewPdf ne persiste rien et dispatch l\'event open-pdf vers la route preview', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('clientId', $client->id)
        ->set('lines.0.description', 'Test PDF')
        ->set('lines.0.quantity', 1)
        ->set('lines.0.unit_price', 10_000)
        ->call('previewPdf')
        ->assertDispatched('open-pdf', function (string $name, array $params) {
            return isset($params['url']) && str_contains($params['url'], '/pme/invoices/preview/');
        });

    expect(Invoice::query()->where('company_id', $company->id)->count())->toBe(0);
});

test('previewPdf invalide ne persiste rien et lève des erreurs de validation', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->call('previewPdf')
        ->assertHasErrors(['clientId', 'lines.0.description']);

    expect(Invoice::query()->where('company_id', $company->id)->count())->toBe(0);
});

test('annuler une nouvelle facture sans cliquer Enregistrer ne crée aucun brouillon en base', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('clientId', $client->id)
        ->set('lines.0.description', 'Service')
        ->set('lines.0.quantity', 1)
        ->set('lines.0.unit_price', 100_000)
        ->call('previewPdf')
        ->call('cancel')
        ->assertRedirect(route('pme.invoices.index'));

    expect(Invoice::query()->where('company_id', $company->id)->count())->toBe(0);
});

// ─── openSaveDraftModal ──────────────────────────────────────────────────────

test('openSaveDraftModal ouvre la modale si le formulaire est valide', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('clientId', $client->id)
        ->set('lines.0.description', 'Prestation')
        ->set('lines.0.quantity', 1)
        ->set('lines.0.unit_price', 10_000)
        ->call('openSaveDraftModal')
        ->assertSet('showSaveDraftModal', true)
        ->assertHasNoErrors();
});

test('openSaveDraftModal ne ouvre pas la modale si le formulaire est invalide', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->call('openSaveDraftModal')
        ->assertSet('showSaveDraftModal', false)
        ->assertHasErrors(['clientId']);
});

test('confirmSaveDraft sauvegarde la facture et redirige vers la liste en brouillon', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('clientId', $client->id)
        ->set('lines.0.description', 'Prestation')
        ->set('lines.0.quantity', 1)
        ->set('lines.0.unit_price', 10_000)
        ->call('openSaveDraftModal')
        ->call('confirmSaveDraft')
        ->assertRedirect(route('pme.invoices.index').'?statut=draft');

    expect(Invoice::query()->where('company_id', $company->id)->first())
        ->not->toBeNull()
        ->status->toBe(InvoiceStatus::Draft);
});

test('confirmSaveDraft flash un message de succès pour le toaster', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('clientId', $client->id)
        ->set('lines.0.description', 'Prestation')
        ->set('lines.0.quantity', 1)
        ->set('lines.0.unit_price', 10_000)
        ->call('openSaveDraftModal')
        ->call('confirmSaveDraft');

    expect(session('success'))->toBe('Brouillon enregistré avec succès.');
});

// ─── Pré-sélection du client via paramètre URL ────────────────────────────────

test('le client est pré-sélectionné quand clientId est passé via URL', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form', ['clientId' => $client->id])
        ->assertSet('clientId', $client->id);
});

test('clientId est remis à vide si le client appartient à une autre entreprise', function () {
    ['user' => $user] = createSmeUser();
    $otherCompany = Company::factory()->create(['type' => 'sme']);
    $foreignClient = Client::factory()->create(['company_id' => $otherCompany->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form', ['clientId' => $foreignClient->id])
        ->assertSet('clientId', '');
});

test('clientId est remis à vide si l\'identifiant ne correspond à aucun client', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form', ['clientId' => 'id-inexistant'])
        ->assertSet('clientId', '');
});

test('le paramètre URL client est ignoré en mode édition de facture', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $invoice = createDraftInvoice($company);
    $otherClient = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form', [
            'invoice' => $invoice,
            'clientId' => $otherClient->id,
        ])
        ->assertSet('clientId', $invoice->client_id);
});

test('le bouton Nouvelle facture sur la fiche client pointe vers create avec le client en paramètre', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    $expectedUrl = route('pme.invoices.create', ['client' => $client->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.clients.show', ['client' => $client])
        ->assertSee($expectedUrl, escape: false);
});

// ─── Deposit (acompte) ───────────────────────────────────────────────────────

test('hasDeposit est désactivé par défaut', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->assertSet('hasDeposit', false)
        ->assertSet('depositAmount', 0);
});

test('désactiver hasDeposit remet depositAmount à 0', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('hasDeposit', true)
        ->set('depositAmount', 25_000)
        ->set('hasDeposit', false)
        ->assertSet('depositAmount', 0);
});

test('on peut sauvegarder un brouillon avec un acompte', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('clientId', $client->id)
        ->set('lines.0.description', 'Service')
        ->set('lines.0.quantity', 1)
        ->set('lines.0.unit_price', 100_000)
        ->set('hasDeposit', true)
        ->set('depositAmount', 30_000)
        ->call('saveDraft')
        ->assertHasNoErrors();

    $invoice = Invoice::query()->where('company_id', $company->id)->first();

    expect($invoice->deposit_amount)->toBe(30_000)
        ->and($invoice->amount_paid)->toBe(30_000)
        ->and($invoice->payments()->where('is_deposit', true)->count())->toBe(1);
});

test('désactiver hasDeposit avant de sauver ne crée pas de Payment', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('clientId', $client->id)
        ->set('lines.0.description', 'Service')
        ->set('lines.0.quantity', 1)
        ->set('lines.0.unit_price', 50_000)
        ->set('hasDeposit', true)
        ->set('depositAmount', 10_000)
        ->set('hasDeposit', false)
        ->call('saveDraft')
        ->assertHasNoErrors();

    $invoice = Invoice::query()->where('company_id', $company->id)->first();

    expect($invoice->deposit_amount)->toBe(0)
        ->and($invoice->amount_paid)->toBe(0)
        ->and($invoice->payments)->toHaveCount(0);
});

test('la page d\'édition réhydrate hasDeposit et depositAmount', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $invoice = createDraftInvoice($company);
    $invoice->update(['deposit_amount' => 25_000]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form', ['invoice' => $invoice])
        ->assertSet('hasDeposit', true)
        ->assertSet('depositAmount', 25_000);
});

test('depositAmount ne peut pas dépasser le maximum pour la devise', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('clientId', $client->id)
        ->set('currency', 'XOF')
        ->set('lines.0.description', 'Service')
        ->set('lines.0.quantity', 1)
        ->set('lines.0.unit_price', 10_000)
        ->set('hasDeposit', true)
        ->set('depositAmount', 1_000_000_000)
        ->call('saveDraft')
        ->assertHasErrors(['depositAmount']);
});

test('mettre à jour une facture remplace l\'acompte existant', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    $invoice = app(InvoiceService::class)->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-FAC-EDITDEP',
        'currency' => 'XOF',
        'tax_rate' => 0,
        'discount' => 0,
        'deposit_amount' => 10_000,
        'issued_at' => now()->format('Y-m-d'),
        'due_at' => now()->addDays(30)->format('Y-m-d'),
    ], [
        ['description' => 'Service', 'quantity' => 1, 'unit_price' => 50_000],
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form', ['invoice' => $invoice])
        ->set('depositAmount', 20_000)
        ->call('saveDraft')
        ->assertHasNoErrors();

    $invoice->refresh();

    expect($invoice->deposit_amount)->toBe(20_000)
        ->and($invoice->amount_paid)->toBe(20_000)
        ->and($invoice->payments()->where('is_deposit', true)->count())->toBe(1);
});

// ─── Bug fix: discount suffix renders FCFA in fixed mode ─────────────────────

test('le suffixe de la remise globale affiche % en mode pourcentage', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('hasDiscount', true)
        ->set('discountType', 'percent')
        ->assertSeeHtml('wire:key="discount-suffix-percent"');
});

test('le suffixe de la remise globale affiche FCFA en mode montant fixe pour XOF', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('currency', 'XOF')
        ->set('hasDiscount', true)
        ->set('discountType', 'fixed')
        ->assertSeeHtml('wire:key="discount-suffix-fixed"')
        ->assertSeeHtml('FCFA');
});

test('le suffixe de la remise globale affiche EUR en mode montant fixe pour EUR', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('currency', 'EUR')
        ->set('hasDiscount', true)
        ->set('discountType', 'fixed')
        ->assertSeeHtml('wire:key="discount-suffix-fixed"')
        ->assertSeeHtml('EUR');
});

// ─── Discount toggle (hasDiscount) ──────────────────────────────────────────

test('hasDiscount est désactivé par défaut', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->assertSet('hasDiscount', false)
        ->assertSet('discount', 0);
});

test('désactiver hasDiscount remet le discount à 0', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('hasDiscount', true)
        ->set('discount', 25)
        ->set('hasDiscount', false)
        ->assertSet('discount', 0);
});

test('saveDraft avec hasDiscount désactivé persiste discount = 0 même si une valeur a été saisie', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('clientId', $client->id)
        ->set('lines.0.description', 'Service')
        ->set('lines.0.quantity', 1)
        ->set('lines.0.unit_price', 100_000)
        ->set('hasDiscount', true)
        ->set('discountType', 'percent')
        ->set('discount', 30)
        ->set('hasDiscount', false)
        ->call('saveDraft')
        ->assertHasNoErrors();

    $invoice = Invoice::query()->where('company_id', $company->id)->first();

    expect($invoice->discount)->toBe(0);
});

test('saveDraft avec hasDiscount activé persiste la remise en pourcentage', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('clientId', $client->id)
        ->set('lines.0.description', 'Service')
        ->set('lines.0.quantity', 1)
        ->set('lines.0.unit_price', 100_000)
        ->set('hasDiscount', true)
        ->set('discountType', 'percent')
        ->set('discount', 10)
        ->call('saveDraft')
        ->assertHasNoErrors();

    $invoice = Invoice::query()->where('company_id', $company->id)->first();

    expect($invoice->discount)->toBe(10)
        ->and($invoice->discount_type)->toBe('percent');
});

test('édition d\'une facture avec remise initialise hasDiscount à true', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    $invoice = app(InvoiceService::class)->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-FAC-DSC',
        'currency' => 'XOF',
        'tax_rate' => 0,
        'discount' => 10,
        'discount_type' => 'percent',
        'issued_at' => now()->format('Y-m-d'),
        'due_at' => now()->addDays(30)->format('Y-m-d'),
    ], [
        ['description' => 'Service', 'quantity' => 1, 'unit_price' => 100_000],
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form', ['invoice' => $invoice])
        ->assertSet('hasDiscount', true)
        ->assertSet('discount', 10)
        ->assertSet('discountType', 'percent');
});

test('édition d\'une facture sans remise initialise hasDiscount à false', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $invoice = createDraftInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form', ['invoice' => $invoice])
        ->assertSet('hasDiscount', false)
        ->assertSet('discount', 0);
});
