<?php

use App\Enums\PME\ProposalDocumentStatus;
use App\Models\Auth\Company;
use App\Models\PME\Invoice;
use App\Models\PME\ProposalDocument;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function setupQuoteMatrix(): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme']);
    $company->users()->attach($user->id, ['role' => 'owner']);

    return ['user' => $user, 'company' => $company];
}

function makeQuoteForMatrix(Company $company, ProposalDocumentStatus $status, array $overrides = []): ProposalDocument
{
    return ProposalDocument::factory()
        ->quote()
        ->forCompany($company)
        ->withLines(1)
        ->create(array_merge(['status' => $status], $overrides));
}

it('Draft : action principale = Envoyer, menu = Modifier/Aperçu/Télécharger/Dupliquer/Supprimer', function () {
    ['user' => $user, 'company' => $company] = setupQuoteMatrix();
    $quote = makeQuoteForMatrix($company, ProposalDocumentStatus::Draft);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $quote])
        ->assertSeeHtml('data-action="primary-send"')
        ->assertSeeText('Envoyer le devis')
        ->assertSeeText('Modifier')
        ->assertSeeText('Aperçu PDF')
        ->assertSeeText('Dupliquer')
        ->assertSeeText('Supprimer')
        ->assertDontSeeHtml('data-action="primary-extend"')
        ->assertDontSeeHtml('data-action="primary-convert"');
});

it('Sent : action principale = Marquer accepté + refusé, menu = Renvoyer/Prolonger/Dupliquer/Annuler', function () {
    ['user' => $user, 'company' => $company] = setupQuoteMatrix();
    $quote = makeQuoteForMatrix($company, ProposalDocumentStatus::Sent, [
        'valid_until' => now()->addDays(15)->toDateString(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $quote])
        ->assertSeeHtml('data-action="primary-accept"')
        ->assertSeeHtml('data-action="primary-decline"')
        ->assertSeeHtml('data-action="resend"')
        ->assertSeeHtml('data-action="extend"')
        ->assertSeeHtml('data-action="duplicate"')
        ->assertSeeHtml('data-action="cancel"')
        ->assertDontSeeHtml('data-action="delete"');
});

it('Accepted : action principale = Convertir en facture, menu = Aperçu/Télécharger/Dupliquer', function () {
    ['user' => $user, 'company' => $company] = setupQuoteMatrix();
    $quote = makeQuoteForMatrix($company, ProposalDocumentStatus::Accepted);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $quote])
        ->assertSeeHtml('data-action="primary-convert"')
        ->assertSeeHtml('data-action="duplicate"')
        ->assertDontSeeHtml('data-action="cancel"')
        ->assertDontSeeHtml('data-action="archive"');
});

it('Expired : action principale = Prolonger la validité (pas Dupliquer)', function () {
    ['user' => $user, 'company' => $company] = setupQuoteMatrix();
    $quote = makeQuoteForMatrix($company, ProposalDocumentStatus::Expired, [
        'valid_until' => now()->subDays(5)->toDateString(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $quote])
        ->assertSeeHtml('data-action="primary-extend"')
        ->assertSeeHtml('data-action="archive"')
        ->assertSeeHtml('data-action="duplicate"');
});

it('Declined : menu = Dupliquer/Archiver, pas d\'action principale', function () {
    ['user' => $user, 'company' => $company] = setupQuoteMatrix();
    $quote = makeQuoteForMatrix($company, ProposalDocumentStatus::Declined);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $quote])
        ->assertSeeHtml('data-action="duplicate"')
        ->assertSeeHtml('data-action="archive"')
        ->assertDontSeeHtml('data-action="primary-send"')
        ->assertDontSeeHtml('data-action="primary-accept"');
});

it('Cancelled : menu = Dupliquer/Archiver', function () {
    ['user' => $user, 'company' => $company] = setupQuoteMatrix();
    $quote = makeQuoteForMatrix($company, ProposalDocumentStatus::Cancelled, [
        'cancelled_at' => now(),
        'cancellation_reason' => 'test',
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $quote])
        ->assertSeeHtml('data-action="duplicate"')
        ->assertSeeHtml('data-action="archive"');
});

it('Factured (with invoice link) : action principale = Voir la facture liée', function () {
    ['user' => $user, 'company' => $company] = setupQuoteMatrix();
    $quote = makeQuoteForMatrix($company, ProposalDocumentStatus::Accepted);
    $invoice = Invoice::factory()->forCompany($company)->create([
        'proposal_document_id' => $quote->id,
    ]);
    $quote->refresh()->load('invoice');

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $quote])
        ->assertSeeHtml('data-action="view-invoice"')
        ->assertSeeText('Voir la facture liée');
});

it('confirmCancel rejette un motif vide et passe avec un motif valide', function () {
    ['user' => $user, 'company' => $company] = setupQuoteMatrix();
    $quote = makeQuoteForMatrix($company, ProposalDocumentStatus::Sent);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $quote])
        ->call('openCancelModal')
        ->set('cancelReason', '')
        ->call('confirmCancel')
        ->assertHasErrors(['cancelReason']);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $quote])
        ->call('openCancelModal')
        ->set('cancelReason', 'Client a changé d\'avis')
        ->call('confirmCancel')
        ->assertHasNoErrors();

    expect($quote->fresh()->status)->toBe(ProposalDocumentStatus::Cancelled);
});

it('confirmExtendValidity prolonge un Expired et le repasse en Sent', function () {
    ['user' => $user, 'company' => $company] = setupQuoteMatrix();
    $quote = makeQuoteForMatrix($company, ProposalDocumentStatus::Expired, [
        'valid_until' => now()->subDays(3)->toDateString(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $quote])
        ->call('openExtendValidityModal')
        ->set('newValidUntil', now()->addDays(15)->toDateString())
        ->call('confirmExtendValidity')
        ->assertHasNoErrors();

    $fresh = $quote->fresh();
    expect($fresh->status)->toBe(ProposalDocumentStatus::Sent);
    expect($fresh->valid_until->toDateString())->toBe(now()->addDays(15)->toDateString());
});
