<?php

use App\Enums\PME\InvoiceStatus;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function setupInvoiceMatrix(): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme']);
    $company->users()->attach($user->id, ['role' => 'owner']);

    return ['user' => $user, 'company' => $company];
}

function makeInvoiceForMatrix(Company $company, InvoiceStatus $status, array $overrides = []): Invoice
{
    return Invoice::factory()
        ->forCompany($company)
        ->withLines(1)
        ->create(array_merge(['status' => $status], $overrides));
}

it('Draft : Envoyer la facture en principal + Modifier/Supprimer dans le menu', function () {
    ['user' => $user, 'company' => $company] = setupInvoiceMatrix();
    $invoice = makeInvoiceForMatrix($company, InvoiceStatus::Draft);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->assertSeeHtml('data-action="primary-send"')
        ->assertSeeHtml('data-action="edit"')
        ->assertSeeHtml('data-action="delete-draft"')
        ->assertSeeText('Envoyer la facture');
});

it('Sent : Enregistrer un paiement en principal + Renvoyer/Annuler dans le menu', function () {
    ['user' => $user, 'company' => $company] = setupInvoiceMatrix();
    $invoice = makeInvoiceForMatrix($company, InvoiceStatus::Sent, [
        'due_at' => now()->addDays(15),
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->assertSeeHtml('data-action="primary-record-payment"')
        ->assertSeeHtml('data-action="resend"')
        ->assertSeeHtml('data-action="cancel"')
        ->assertDontSeeHtml('data-action="credit-note"');
});

it('Overdue : Relancer en principal', function () {
    ['user' => $user, 'company' => $company] = setupInvoiceMatrix();
    $invoice = makeInvoiceForMatrix($company, InvoiceStatus::Overdue, [
        'due_at' => now()->subDays(5),
        'amount_paid' => 0,
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->assertSeeHtml('data-action="primary-remind"')
        ->assertSeeHtml('data-action="cancel"')
        ->assertDontSeeHtml('data-action="record-payment"');
});

it('PartiallyPaid : Enregistrer un paiement en principal', function () {
    ['user' => $user, 'company' => $company] = setupInvoiceMatrix();
    $invoice = makeInvoiceForMatrix($company, InvoiceStatus::PartiallyPaid, [
        'amount_paid' => 50_000,
        'total' => 100_000,
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->assertSeeHtml('data-action="primary-record-payment"')
        ->assertDontSeeHtml('data-action="view-payments"');
});

it('Paid : Télécharger le reçu en principal + Dupliquer', function () {
    ['user' => $user, 'company' => $company] = setupInvoiceMatrix();
    $invoice = makeInvoiceForMatrix($company, InvoiceStatus::Paid, [
        'paid_at' => now(),
        'amount_paid' => 100_000,
        'total' => 100_000,
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->assertSeeHtml('data-action="primary-download-receipt"')
        ->assertSeeHtml('data-action="duplicate"')
        ->assertDontSeeHtml('data-action="credit-note"')
        ->assertDontSeeHtml('data-action="cancel"');
});

it('Cancelled : pas d\'action principale, Archiver dans le menu', function () {
    ['user' => $user, 'company' => $company] = setupInvoiceMatrix();
    $invoice = makeInvoiceForMatrix($company, InvoiceStatus::Cancelled, [
        'cancelled_at' => now(),
        'cancellation_reason' => 'X',
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->assertSeeHtml('data-action="archive"')
        ->assertDontSeeHtml('data-action="cancel"')
        ->assertDontSeeHtml('data-action="primary-record-payment"');
});

it('openSendModal puis confirmSend bascule un Draft en Sent', function () {
    ['user' => $user, 'company' => $company] = setupInvoiceMatrix();
    $client = Client::factory()->create(['company_id' => $company->id, 'phone' => '+221770000000']);
    $invoice = makeInvoiceForMatrix($company, InvoiceStatus::Draft, ['client_id' => $client->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->assertSet('showSendModal', true)
        ->call('confirmSend')
        ->assertSet('showSendModal', false);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
});

it('confirmCancel exige un motif et passe la facture en Cancelled', function () {
    ['user' => $user, 'company' => $company] = setupInvoiceMatrix();
    $invoice = makeInvoiceForMatrix($company, InvoiceStatus::Sent);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openCancelModal')
        ->set('cancelReason', '')
        ->call('confirmCancel')
        ->assertHasErrors(['cancelReason']);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openCancelModal')
        ->set('cancelReason', 'Doublon')
        ->call('confirmCancel');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Cancelled);
});

it('archive sur une facture annulée la marque archivée', function () {
    ['user' => $user, 'company' => $company] = setupInvoiceMatrix();
    $invoice = makeInvoiceForMatrix($company, InvoiceStatus::Cancelled, [
        'cancelled_at' => now(),
        'cancellation_reason' => 'X',
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('archive');

    expect($invoice->fresh()->archived_at)->not->toBeNull();
});
