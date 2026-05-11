<?php

use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\ProposalDocumentStatus;
use App\Models\Auth\Company;
use App\Models\PME\Invoice;
use App\Models\PME\ProposalDocument;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function setupProformaMatrix(): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme']);
    $company->users()->attach($user->id, ['role' => 'owner']);

    return ['user' => $user, 'company' => $company];
}

function makeProformaForMatrix(Company $company, ProposalDocumentStatus $status, array $overrides = []): ProposalDocument
{
    return ProposalDocument::factory()
        ->proforma()
        ->forCompany($company)
        ->withLines(1)
        ->create(array_merge(['status' => $status], $overrides));
}

it('Draft : Envoyer la proforma en principal', function () {
    ['user' => $user, 'company' => $company] = setupProformaMatrix();
    $proforma = makeProformaForMatrix($company, ProposalDocumentStatus::Draft);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $proforma])
        ->assertSeeHtml('data-action="primary-send"')
        ->assertSeeText('Envoyer la proforma');
});

it('Sent : raccourci direct Convertir + alternative Marquer acceptée/refusée', function () {
    ['user' => $user, 'company' => $company] = setupProformaMatrix();
    $proforma = makeProformaForMatrix($company, ProposalDocumentStatus::Sent, [
        'valid_until' => now()->addDays(15)->toDateString(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $proforma])
        ->assertSeeHtml('data-action="primary-shortcut-convert"')
        ->assertSeeHtml('data-action="primary-accept"')
        ->assertSeeHtml('data-action="primary-decline"')
        ->assertSeeHtml('data-action="cancel"');
});

it('Accepted sans BC : Ajouter un BC en principal, Convertir en secondaire primaire', function () {
    ['user' => $user, 'company' => $company] = setupProformaMatrix();
    $proforma = makeProformaForMatrix($company, ProposalDocumentStatus::Accepted, [
        'accepted_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $proforma])
        ->assertSeeHtml('data-action="primary-add-po"')
        ->assertSeeHtml('data-action="primary-convert"')
        ->assertDontSeeHtml('data-action="po-edit"');
});

it('Accepted avec BC : Convertir en principal, Modifier le BC dans le menu', function () {
    ['user' => $user, 'company' => $company] = setupProformaMatrix();
    $proforma = makeProformaForMatrix($company, ProposalDocumentStatus::Accepted, [
        'accepted_at' => now(),
        'po_reference' => 'BC-2026/0042',
        'po_received_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $proforma])
        ->assertSeeHtml('data-action="primary-convert"')
        ->assertSeeHtml('data-action="po-edit"')
        ->assertDontSeeHtml('data-action="primary-add-po"');
});

it('Accepted sans BC formel : Convertir en principal, Modifier le BC dans le menu', function () {
    ['user' => $user, 'company' => $company] = setupProformaMatrix();
    $proforma = makeProformaForMatrix($company, ProposalDocumentStatus::Accepted, [
        'accepted_at' => now(),
        'has_no_formal_po' => true,
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $proforma])
        ->assertSeeHtml('data-action="primary-convert"')
        ->assertSeeHtml('data-action="po-edit"');
});

it('Expired : Prolonger la validité en principal', function () {
    ['user' => $user, 'company' => $company] = setupProformaMatrix();
    $proforma = makeProformaForMatrix($company, ProposalDocumentStatus::Expired, [
        'valid_until' => now()->subDays(2)->toDateString(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $proforma])
        ->assertSeeHtml('data-action="primary-extend"')
        ->assertSeeHtml('data-action="archive"');
});

it('Cancelled : menu = Dupliquer/Archiver', function () {
    ['user' => $user, 'company' => $company] = setupProformaMatrix();
    $proforma = makeProformaForMatrix($company, ProposalDocumentStatus::Cancelled, [
        'cancelled_at' => now(),
        'cancellation_reason' => 'motif',
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $proforma])
        ->assertSeeHtml('data-action="duplicate"')
        ->assertSeeHtml('data-action="archive"');
});

it('raccourci direct : clic Convertir depuis Envoyée crée la facture + accepted_at + converted_at', function () {
    ['user' => $user, 'company' => $company] = setupProformaMatrix();
    $proforma = makeProformaForMatrix($company, ProposalDocumentStatus::Sent);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $proforma])
        ->call('convertToInvoice');

    $fresh = $proforma->fresh();
    expect($fresh->status)->toBe(ProposalDocumentStatus::Converted);
    expect($fresh->accepted_at)->not->toBeNull();
    expect($fresh->converted_at)->not->toBeNull();
    expect($fresh->accepted_at->lessThan($fresh->converted_at))->toBeTrue();

    $invoice = Invoice::query()->where('proposal_document_id', $proforma->id)->first();
    expect($invoice)->not->toBeNull();
    expect($invoice->status)->toBe(InvoiceStatus::Draft);
});

it('BC : enregistrement avec case "Pas de BC formel" coche has_no_formal_po', function () {
    ['user' => $user, 'company' => $company] = setupProformaMatrix();
    $proforma = makeProformaForMatrix($company, ProposalDocumentStatus::Accepted, [
        'accepted_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $proforma])
        ->call('openPoModal')
        ->set('poNoFormal', true)
        ->set('poNotes', 'Accord verbal sur WhatsApp')
        ->call('recordPurchaseOrder')
        ->assertHasNoErrors();

    $fresh = $proforma->fresh();
    expect($fresh->has_no_formal_po)->toBeTrue();
    expect($fresh->po_notes)->toBe('Accord verbal sur WhatsApp');
    expect($fresh->status)->toBe(ProposalDocumentStatus::PoReceived);
});
