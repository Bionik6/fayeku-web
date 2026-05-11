<?php

use App\Enums\PME\InvoiceStatus;
use App\Models\Auth\Company;
use App\Models\PME\Invoice;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function setupDeleteDraft(): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme']);
    $company->users()->attach($user->id, ['role' => 'owner']);

    return ['user' => $user, 'company' => $company];
}

it('release : supprime le brouillon définitivement (force delete)', function () {
    ['user' => $user, 'company' => $company] = setupDeleteDraft();
    $invoice = Invoice::factory()
        ->forCompany($company)
        ->withLines(1)
        ->create(['status' => InvoiceStatus::Draft]);
    $id = $invoice->id;

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openDeleteDraftModal')
        ->set('deleteDraftStrategy', 'release')
        ->call('confirmDeleteDraft');

    expect(Invoice::withTrashed()->find($id))->toBeNull();
});

it('vacant : marque le brouillon archivé puis soft-delete', function () {
    ['user' => $user, 'company' => $company] = setupDeleteDraft();
    $invoice = Invoice::factory()
        ->forCompany($company)
        ->withLines(1)
        ->create(['status' => InvoiceStatus::Draft]);
    $id = $invoice->id;

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openDeleteDraftModal')
        ->set('deleteDraftStrategy', 'vacant')
        ->call('confirmDeleteDraft');

    $trashed = Invoice::withTrashed()->find($id);
    expect($trashed)->not->toBeNull();
    expect($trashed->archived_at)->not->toBeNull();
    expect(Invoice::query()->find($id))->toBeNull();
});

it('refuse d\'ouvrir la modale sur une facture non-brouillon', function () {
    ['user' => $user, 'company' => $company] = setupDeleteDraft();
    $invoice = Invoice::factory()
        ->forCompany($company)
        ->withLines(1)
        ->create(['status' => InvoiceStatus::Sent]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openDeleteDraftModal')
        ->assertSet('showDeleteDraftModal', false);
});
