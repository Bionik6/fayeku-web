<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Modules\Auth\Models\Company;
use Modules\PME\Clients\Models\Client;
use Modules\PME\Invoicing\Enums\InvoiceStatus;
use Modules\PME\Invoicing\Mail\InvoiceMail;
use Modules\PME\Invoicing\Models\Invoice;
use Modules\PME\Invoicing\Models\InvoiceLine;
use Modules\Shared\Models\User;

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

// ─── Client creation inline ─────────────────────────────────────────────────

test('on peut créer un client depuis le formulaire facture', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->call('openClientModal')
        ->assertSet('showClientModal', true)
        ->set('clientName', 'Dakar Pharma')
        ->set('clientEmail', 'contact@dakarpharma.sn')
        ->call('saveClient')
        ->assertSet('showClientModal', false);

    $client = Client::query()->where('company_id', $company->id)->where('name', 'Dakar Pharma')->first();

    expect($client)->not->toBeNull();
});

// ─── Send flow ───────────────────────────────────────────────────────────────

test('envoyer une facture change son statut en Sent', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $invoice = createDraftInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form', ['invoice' => $invoice])
        ->set('sendChannel', 'whatsapp')
        ->call('send')
        ->assertRedirect(route('pme.invoices.index'));

    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Sent);
});

test('on ne peut pas envoyer une facture à 0 FCFA', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->call('openSendModal')
        ->assertSet('showSendModal', false)
        ->assertHasErrors('lines');
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
        ->set('taxMode', 'custom')
        ->set('customTaxRate', 10)
        ->assertSet('taxRate', 10);
});

test('le taux de TVA personnalisé est limité entre 0 et 100', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('taxMode', 'custom')
        ->set('customTaxRate', 150)
        ->assertSet('taxRate', 100);
});

test('le champ customTaxRate est lui-même clampé à 100 quand la valeur dépasse 100', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('taxMode', 'custom')
        ->set('customTaxRate', 999)
        ->assertSet('customTaxRate', 100);
});

test('le taux de TVA personnalisé accepte exactement 100', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('taxMode', 'custom')
        ->set('customTaxRate', 100)
        ->assertSet('customTaxRate', 100)
        ->assertSet('taxRate', 100);
});

test('le taux de TVA personnalisé est clampé à 0 si une valeur négative est saisie', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('taxMode', 'custom')
        ->set('customTaxRate', -5)
        ->assertSet('customTaxRate', 0)
        ->assertSet('taxRate', 0);
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

test('les relances ont des valeurs par défaut', function () {
    ['user' => $user] = createSmeUser();

    $component = Livewire::actingAs($user)
        ->test('pages::pme.invoices.form');

    expect($component->get('reminderSchedule'))->toBe(['-7', '-2', '0', '+7']);
});

test('on peut activer une relance', function () {
    ['user' => $user] = createSmeUser();

    $component = Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->call('toggleReminder', '+15');

    expect($component->get('reminderSchedule'))->toContain('+15');
});

test('on peut désactiver une relance', function () {
    ['user' => $user] = createSmeUser();

    $component = Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->call('toggleReminder', '-7');

    expect($component->get('reminderSchedule'))->not->toContain('-7');
});

test('on peut désactiver toutes les relances', function () {
    ['user' => $user] = createSmeUser();

    $component = Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->call('toggleReminder', '-7')
        ->call('toggleReminder', '-2')
        ->call('toggleReminder', '0')
        ->call('toggleReminder', '+7');

    expect($component->get('reminderSchedule'))->toBeEmpty();
});

test('les relances sont sauvegardées avec la facture', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('clientId', $client->id)
        ->set('lines.0.description', 'Service')
        ->set('lines.0.quantity', 1)
        ->set('lines.0.unit_price', 10_000)
        ->call('toggleReminder', '+15')
        ->call('saveDraft')
        ->assertHasNoErrors();

    $invoice = Invoice::query()->where('company_id', $company->id)->first();

    expect($invoice->reminder_schedule)->toContain('+15')
        ->and($invoice->reminder_schedule)->toContain('-7');
});

test('la page d\'édition charge les relances existantes', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $invoice = createDraftInvoice($company);
    $invoice->update(['reminder_schedule' => ['-2', '0', '+30']]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form', ['invoice' => $invoice])
        ->assertSet('reminderSchedule', ['-2', '0', '+30']);
});

test('la validation refuse une valeur de relance invalide', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('clientId', $client->id)
        ->set('lines.0.description', 'Service')
        ->set('lines.0.quantity', 1)
        ->set('lines.0.unit_price', 10_000)
        ->set('reminderSchedule', ['+99'])
        ->call('saveDraft')
        ->assertHasErrors(['reminderSchedule.0']);
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
        ->set('notes', 'Merci pour votre confiance')
        ->call('confirmCancel')
        ->assertSet('showCancelModal', true);
});

test('confirmer l\'annulation redirige vers la liste', function () {
    ['user' => $user] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->call('cancel')
        ->assertRedirect(route('pme.invoices.index'));
});

// ─── PDF & Envoi ─────────────────────────────────────────────────────────────

test('previewPdf sauvegarde le brouillon et dispatch l\'event open-pdf', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('clientId', $client->id)
        ->set('lines.0.description', 'Test PDF')
        ->set('lines.0.quantity', 1)
        ->set('lines.0.unit_price', 10_000)
        ->call('previewPdf')
        ->assertDispatched('open-pdf');
});

test('envoyer par email envoie un mail avec PDF en pièce jointe', function () {
    Mail::fake();

    ['user' => $user, 'company' => $company] = createSmeUser();
    $invoice = createDraftInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form', ['invoice' => $invoice])
        ->set('sendChannel', 'email')
        ->set('sendRecipient', 'client@example.com')
        ->set('sendMessage', 'Voici votre facture.')
        ->call('send')
        ->assertRedirect(route('pme.invoices.index'));

    Mail::assertQueued(InvoiceMail::class, fn ($mail) => $mail->hasTo('client@example.com'));

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
});

test('envoyer par email échoue avec un email invalide', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $invoice = createDraftInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form', ['invoice' => $invoice])
        ->set('sendChannel', 'email')
        ->set('sendRecipient', 'pas-un-email')
        ->call('send')
        ->assertHasErrors('sendRecipient');
});

test('envoyer par whatsapp marque la facture comme envoyée sans envoyer d\'email', function () {
    Mail::fake();

    ['user' => $user, 'company' => $company] = createSmeUser();
    $invoice = createDraftInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form', ['invoice' => $invoice])
        ->set('sendChannel', 'whatsapp')
        ->set('sendRecipient', '+221771234567')
        ->call('send')
        ->assertRedirect(route('pme.invoices.index'));

    Mail::assertNothingQueued();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
});

test('envoyer en PDF ne marque pas la facture comme envoyée', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $invoice = createDraftInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form', ['invoice' => $invoice])
        ->set('sendChannel', 'pdf')
        ->call('send')
        ->assertDispatched('open-pdf')
        ->assertNoRedirect();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Draft);
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

test('sendRecipient est initialisé avec l\'email du client quand il en a un', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create([
        'company_id' => $company->id,
        'email' => 'contact@acme.sn',
        'phone' => '+221771234567',
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form', ['clientId' => $client->id])
        ->assertSet('sendRecipient', 'contact@acme.sn');
});

test('sendRecipient est initialisé avec le téléphone quand le client n\'a pas d\'email', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();
    $client = Client::factory()->create([
        'company_id' => $company->id,
        'email' => null,
        'phone' => '+221771234567',
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form', ['clientId' => $client->id])
        ->assertSet('sendRecipient', '+221771234567');
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

// ─── Téléphone client (x-phone-input) ───────────────────────────────────────

test('clientPhoneCountries contient tous les pays de config au montage', function () {
    ['user' => $user] = createSmeUser();

    $component = Livewire::actingAs($user)->test('pages::pme.invoices.form');

    $expected = collect(config('fayeku.phone_countries'))->map(fn ($c) => $c['label'])->all();

    expect($component->get('clientPhoneCountries'))->toBe($expected);
});

test('clientPhoneCountry est initialisé au code pays de la compagnie', function () {
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme', 'country_code' => 'CI']);
    $company->users()->attach($user->id, ['role' => 'owner']);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->call('openClientModal')
        ->assertSet('clientPhoneCountry', 'CI');
});

test('openClientModal réinitialise clientPhone et clientPhoneCountry', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('clientPhone', '+221771234567')
        ->set('clientPhoneCountry', 'CI')
        ->call('openClientModal')
        ->assertSet('clientPhone', '')
        ->assertSet('clientPhoneCountry', $company->country_code ?? 'SN');
});

test('saveClient normalise un numéro SN sans préfixe', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->call('openClientModal')
        ->set('clientName', 'Diallo Tech')
        ->set('clientPhoneCountry', 'SN')
        ->set('clientPhone', '771234567')
        ->call('saveClient')
        ->assertHasNoErrors();

    expect(Client::query()->where('company_id', $company->id)->first()->phone)
        ->toBe('+221771234567');
});

test('saveClient normalise un numéro CI sans préfixe', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->call('openClientModal')
        ->set('clientName', 'Kouassi BTP')
        ->set('clientPhoneCountry', 'CI')
        ->set('clientPhone', '0712345678')
        ->call('saveClient')
        ->assertHasNoErrors();

    expect(Client::query()->where('company_id', $company->id)->first()->phone)
        ->toBe('+2250712345678');
});

test('saveClient conserve un numéro déjà internationalisé (+221...)', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->call('openClientModal')
        ->set('clientName', 'Fall Logistics')
        ->set('clientPhoneCountry', 'SN')
        ->set('clientPhone', '+221771234567')
        ->call('saveClient')
        ->assertHasNoErrors();

    expect(Client::query()->where('company_id', $company->id)->first()->phone)
        ->toBe('+221771234567');
});

test('saveClient stocke null quand clientPhone est vide', function () {
    ['user' => $user, 'company' => $company] = createSmeUser();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->call('openClientModal')
        ->set('clientName', 'Sow Import')
        ->set('clientPhone', '')
        ->call('saveClient')
        ->assertHasNoErrors();

    expect(Client::query()->where('company_id', $company->id)->first()->phone)->toBeNull();
});
