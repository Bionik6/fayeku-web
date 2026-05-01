<?php

use App\Enums\PME\ProposalDocumentStatus;
use App\Enums\PME\ProposalDocumentType;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\ProposalDocument;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * REGRESSION — Vue unifiée Devis & Proformas (filtre Statut uniquement).
 *
 * Garantit que la liste affiche bien les deux types ensemble et que les
 * transitions de filtre Statut (incluant retour à "Tous") restaurent l'ensemble.
 */
function bootstrapUnifiedFilterScenario(): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme']);
    $company->users()->attach($user->id, ['role' => 'owner']);
    $client = Client::factory()->create(['company_id' => $company->id]);

    // 5 devis : 2 draft, 2 sent, 1 accepted
    $quotes = [
        ['DEV-D1', ProposalDocumentStatus::Draft],
        ['DEV-D2', ProposalDocumentStatus::Draft],
        ['DEV-S1', ProposalDocumentStatus::Sent],
        ['DEV-S2', ProposalDocumentStatus::Sent],
        ['DEV-A1', ProposalDocumentStatus::Accepted],
    ];
    foreach ($quotes as [$ref, $status]) {
        ProposalDocument::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'type' => ProposalDocumentType::Quote,
            'reference' => $ref,
            'currency' => 'XOF',
            'status' => $status,
            'issued_at' => now(),
            'valid_until' => now()->addDays(30),
            'subtotal' => 100_000, 'tax_amount' => 0, 'total' => 100_000,
        ]);
    }

    // 3 proformas : 1 draft, 1 sent, 1 po_received
    $proformas = [
        ['PRO-D1', ProposalDocumentStatus::Draft],
        ['PRO-S1', ProposalDocumentStatus::Sent],
        ['PRO-P1', ProposalDocumentStatus::PoReceived],
    ];
    foreach ($proformas as [$ref, $status]) {
        ProposalDocument::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'type' => ProposalDocumentType::Proforma,
            'reference' => $ref,
            'currency' => 'XOF',
            'status' => $status,
            'issued_at' => now(),
            'valid_until' => now()->addDays(30),
            'subtotal' => 50_000, 'tax_amount' => 0, 'total' => 50_000,
        ]);
    }

    return compact('user', 'company', 'client');
}

// ─── Vue unifiée par défaut ──────────────────────────────────────────────────

test('La liste contient les devis ET les proformas par défaut', function () {
    ['user' => $user] = bootstrapUnifiedFilterScenario();

    $component = Livewire::actingAs($user)->test('pages::pme.quotes.index');
    $rows = collect($component->get('rows'));

    expect($rows)->toHaveCount(8)
        ->and($rows->where('type', 'quote'))->toHaveCount(5)
        ->and($rows->where('type', 'proforma'))->toHaveCount(3);
});

test('Aucune méthode setTypeFilter ne doit exister sur le composant', function () {
    ['user' => $user] = bootstrapUnifiedFilterScenario();

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.index')
        ->call('setTypeFilter', 'quote')
        ->assertStatus(500);
})->throws(Exception::class);

// ─── Aller-retour Statut ─────────────────────────────────────────────────────

test('REGRESSION : cliquer Envoyé puis Tous (statut) restaure les 8 documents', function () {
    ['user' => $user] = bootstrapUnifiedFilterScenario();

    $component = Livewire::actingAs($user)->test('pages::pme.quotes.index');

    $component->call('setStatusFilter', 'sent');
    expect(count($component->get('rows')))->toBe(3);

    $component->call('setStatusFilter', 'all');
    expect(count($component->get('rows')))->toBe(8);
});

test('REGRESSION : statuts spécifiques (po_received, accepted) restent disponibles dans le filtre statut', function () {
    ['user' => $user] = bootstrapUnifiedFilterScenario();

    $component = Livewire::actingAs($user)->test('pages::pme.quotes.index');

    $component->call('setStatusFilter', 'po_received');
    expect(count($component->get('rows')))->toBe(1)
        ->and(collect($component->get('rows'))->first()['reference'])->toBe('PRO-P1');

    $component->call('setStatusFilter', 'accepted');
    expect(count($component->get('rows')))->toBe(1)
        ->and(collect($component->get('rows'))->first()['reference'])->toBe('DEV-A1');

    $component->call('setStatusFilter', 'all');
    expect(count($component->get('rows')))->toBe(8);
});

// ─── KPIs combinés ───────────────────────────────────────────────────────────

test('Les KPIs combinent toujours devis et proformas', function () {
    ['user' => $user] = bootstrapUnifiedFilterScenario();

    $component = Livewire::actingAs($user)->test('pages::pme.quotes.index');

    expect($component->get('totalDocuments'))->toBe(8)
        ->and($component->get('totalQuotes'))->toBe(5)
        ->and($component->get('totalProformas'))->toBe(3)
        ->and($component->get('pendingDocuments'))->toBe(3) // 2 devis sent + 1 proforma sent
        ->and($component->get('validatedDocuments'))->toBe(2); // 1 devis accepted + 1 proforma po_received
});
