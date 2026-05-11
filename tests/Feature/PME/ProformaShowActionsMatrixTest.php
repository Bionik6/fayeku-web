<?php

use App\Enums\PME\ProposalDocumentStatus;
use App\Models\Auth\Company;
use App\Models\PME\ProposalDocument;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Storage;
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

it('Sent : Marquer acceptée (primaire) + Marquer refusée, sans raccourci Convertir', function () {
    ['user' => $user, 'company' => $company] = setupProformaMatrix();
    $proforma = makeProformaForMatrix($company, ProposalDocumentStatus::Sent, [
        'valid_until' => now()->addDays(15)->toDateString(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $proforma])
        ->assertSeeHtml('data-action="primary-accept"')
        ->assertSeeHtml('data-action="primary-decline"')
        ->assertSeeHtml('data-action="cancel"')
        ->assertDontSeeHtml('data-action="primary-shortcut-convert"')
        ->assertDontSeeText('Convertir en facture');
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

it('openAcceptModal informel : Accepted + has_no_formal_po=true + acceptance_note', function () {
    ['user' => $user, 'company' => $company] = setupProformaMatrix();
    $proforma = makeProformaForMatrix($company, ProposalDocumentStatus::Sent);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $proforma])
        ->call('openAcceptModal')
        ->assertSet('showAcceptModal', true)
        ->assertSet('acceptMode', 'informal')
        ->set('acceptanceNote', 'Accord WhatsApp du 11/05')
        ->call('confirmAccept')
        ->assertSet('showAcceptModal', false);

    $fresh = $proforma->fresh();
    expect($fresh->status)->toBe(ProposalDocumentStatus::Accepted);
    expect($fresh->has_no_formal_po)->toBeTrue();
    expect($fresh->acceptance_note)->toBe('Accord WhatsApp du 11/05');
});

it('openAcceptModal po sans référence : validation error', function () {
    ['user' => $user, 'company' => $company] = setupProformaMatrix();
    $proforma = makeProformaForMatrix($company, ProposalDocumentStatus::Sent);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $proforma])
        ->call('openAcceptModal')
        ->set('acceptMode', 'po')
        ->set('poReference', '')
        ->call('confirmAccept')
        ->assertHasErrors(['poReference']);

    expect($proforma->fresh()->status)->toBe(ProposalDocumentStatus::Sent);
});

it('openAcceptModal po avec ref + date : passe PoReceived avec le BC enregistré', function () {
    ['user' => $user, 'company' => $company] = setupProformaMatrix();
    $proforma = makeProformaForMatrix($company, ProposalDocumentStatus::Sent);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $proforma])
        ->call('openAcceptModal')
        ->set('acceptMode', 'po')
        ->set('poReference', 'BC-2026/0042')
        ->set('poReceivedAt', now()->toDateString())
        ->call('confirmAccept')
        ->assertHasNoErrors();

    $fresh = $proforma->fresh();
    expect($fresh->status)->toBe(ProposalDocumentStatus::PoReceived);
    expect($fresh->has_no_formal_po)->toBeFalse();
    expect($fresh->po_reference)->toBe('BC-2026/0042');
    expect($fresh->po_received_at)->not->toBeNull();
    expect($fresh->accepted_at)->not->toBeNull();
});

it('openAcceptModal po + upload PDF : stocke le fichier', function () {
    Storage::fake();
    ['user' => $user, 'company' => $company] = setupProformaMatrix();
    $proforma = makeProformaForMatrix($company, ProposalDocumentStatus::Sent);

    $pdf = File::create('bc.pdf', 50, 'application/pdf');

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $proforma])
        ->call('openAcceptModal')
        ->set('acceptMode', 'po')
        ->set('poReference', 'BC-2026/0099')
        ->set('poReceivedAt', now()->toDateString())
        ->set('poFile', $pdf)
        ->call('confirmAccept')
        ->assertHasNoErrors();

    $fresh = $proforma->fresh();
    expect($fresh->po_file_path)->not->toBeNull();
    Storage::assertExists($fresh->po_file_path);
});

it('openDeclineModal proforma : Declined + decline_reason', function () {
    ['user' => $user, 'company' => $company] = setupProformaMatrix();
    $proforma = makeProformaForMatrix($company, ProposalDocumentStatus::Sent);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $proforma])
        ->call('openDeclineModal')
        ->set('declineReason', 'Client a annulé son projet')
        ->call('confirmDecline')
        ->assertSet('showDeclineModal', false);

    $fresh = $proforma->fresh();
    expect($fresh->status)->toBe(ProposalDocumentStatus::Declined);
    expect($fresh->decline_reason)->toBe('Client a annulé son projet');
});

it('BC edit : passer en mode informel revient à Accepted avec acceptance_note', function () {
    ['user' => $user, 'company' => $company] = setupProformaMatrix();
    $proforma = makeProformaForMatrix($company, ProposalDocumentStatus::PoReceived, [
        'accepted_at' => now()->subDay(),
        'po_reference' => 'BC-2026/0099',
        'po_received_at' => now()->subDay(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $proforma])
        ->call('openPoModal')
        ->set('acceptMode', 'informal')
        ->set('acceptanceNote', 'Accord verbal sur WhatsApp')
        ->call('recordPurchaseOrder')
        ->assertHasNoErrors();

    $fresh = $proforma->fresh();
    expect($fresh->has_no_formal_po)->toBeTrue();
    expect($fresh->po_reference)->toBeNull();
    expect($fresh->po_received_at)->toBeNull();
    expect($fresh->acceptance_note)->toBe('Accord verbal sur WhatsApp');
    expect($fresh->status)->toBe(ProposalDocumentStatus::Accepted);
});

it('BC edit : modifier le BC depuis le mode po met à jour les champs et garde PoReceived', function () {
    ['user' => $user, 'company' => $company] = setupProformaMatrix();
    $proforma = makeProformaForMatrix($company, ProposalDocumentStatus::PoReceived, [
        'accepted_at' => now()->subDay(),
        'po_reference' => 'BC-2026/0001',
        'po_received_at' => now()->subDay(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $proforma])
        ->call('openPoModal')
        ->assertSet('acceptMode', 'po')
        ->set('poReference', 'BC-2026/UPDATED')
        ->set('poNotes', 'BC corrigé')
        ->call('recordPurchaseOrder')
        ->assertHasNoErrors();

    $fresh = $proforma->fresh();
    expect($fresh->status)->toBe(ProposalDocumentStatus::PoReceived);
    expect($fresh->po_reference)->toBe('BC-2026/UPDATED');
    expect($fresh->po_notes)->toBe('BC corrigé');
});

it('BC : upload d\'un PDF stocke le fichier et persiste son path', function () {
    Storage::fake();
    ['user' => $user, 'company' => $company] = setupProformaMatrix();
    $proforma = makeProformaForMatrix($company, ProposalDocumentStatus::Accepted, [
        'accepted_at' => now(),
    ]);

    $pdf = File::create('bon-de-commande.pdf', 50, 'application/pdf');

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $proforma])
        ->call('openPoModal')
        ->set('poReference', 'BC-2026/0042')
        ->set('poReceivedAt', now()->toDateString())
        ->set('poFile', $pdf)
        ->call('recordPurchaseOrder')
        ->assertHasNoErrors();

    $fresh = $proforma->fresh();
    expect($fresh->po_file_path)->not->toBeNull();
    expect($fresh->po_file_path)->toStartWith("pme/proformas/{$proforma->id}/bc/");
    Storage::assertExists($fresh->po_file_path);
});

it('BC : accepte un upload image (JPG/PNG) en plus du PDF', function () {
    Storage::fake();
    ['user' => $user, 'company' => $company] = setupProformaMatrix();
    $proforma = makeProformaForMatrix($company, ProposalDocumentStatus::Accepted, [
        'accepted_at' => now(),
    ]);

    $image = File::image('bc-scan.png');

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $proforma])
        ->call('openPoModal')
        ->set('poReference', 'BC-2026/0042')
        ->set('poReceivedAt', now()->toDateString())
        ->set('poFile', $image)
        ->call('recordPurchaseOrder')
        ->assertHasNoErrors();

    expect($proforma->fresh()->po_file_path)->not->toBeNull();
});

it('BC : rejette un fichier non supporté (ex. GIF)', function () {
    Storage::fake();
    ['user' => $user, 'company' => $company] = setupProformaMatrix();
    $proforma = makeProformaForMatrix($company, ProposalDocumentStatus::Accepted, [
        'accepted_at' => now(),
    ]);

    $gif = File::image('animated.gif');

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $proforma])
        ->call('openPoModal')
        ->set('poReference', 'BC-2026/0042')
        ->set('poReceivedAt', now()->toDateString())
        ->set('poFile', $gif)
        ->call('recordPurchaseOrder')
        ->assertHasErrors(['poFile']);
});

it('BC : removePoFile supprime le fichier existant à l\'enregistrement', function () {
    Storage::fake();
    ['user' => $user, 'company' => $company] = setupProformaMatrix();
    $proforma = makeProformaForMatrix($company, ProposalDocumentStatus::PoReceived, [
        'accepted_at' => now(),
        'po_reference' => 'BC-2026/0042',
        'po_received_at' => now(),
    ]);
    $path = "pme/proformas/{$proforma->id}/bc/old.pdf";
    Storage::put($path, 'fake');
    $proforma->update(['po_file_path' => $path]);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $proforma->fresh()])
        ->call('openPoModal')
        ->set('removePoFile', true)
        ->call('recordPurchaseOrder')
        ->assertHasNoErrors();

    expect($proforma->fresh()->po_file_path)->toBeNull();
    Storage::assertMissing($path);
});
