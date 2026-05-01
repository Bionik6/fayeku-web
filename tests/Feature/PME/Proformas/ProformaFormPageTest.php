<?php

use App\Enums\PME\ProformaStatus;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Proforma;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createSmeForProformaForm(): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme']);
    $company->users()->attach($user->id, ['role' => 'owner']);

    return compact('user', 'company');
}

test('mount() en création pré-remplit reference, dates et une ligne vide', function () {
    ['user' => $user] = createSmeForProformaForm();

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.form')
        ->assertSet('isEditing', false)
        ->assertSet('currency', 'XOF')
        ->assertCount('lines', 1)
        ->tap(fn ($t) => expect($t->get('reference'))->toMatch('/^FYK-PRO-[A-Z0-9]{6}$/'));
});

test('saveDraft persiste une proforma avec les 3 champs spécifiques', function () {
    ['user' => $user, 'company' => $company] = createSmeForProformaForm();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.form')
        ->set('clientId', $client->id)
        ->set('dossierReference', 'DAO N°2026/MEF/045')
        ->set('paymentTerms', '30 jours fin de mois')
        ->set('deliveryTerms', '15 jours ouvrés après BC')
        ->set('lines', [['description' => 'Service A', 'quantity' => 2, 'unit_price' => 50_000]])
        ->call('saveDraft');

    $proforma = Proforma::query()->where('company_id', $company->id)->first();

    expect($proforma)->not->toBeNull()
        ->and($proforma->dossier_reference)->toBe('DAO N°2026/MEF/045')
        ->and($proforma->payment_terms)->toBe('30 jours fin de mois')
        ->and($proforma->delivery_terms)->toBe('15 jours ouvrés après BC')
        ->and($proforma->status)->toBe(ProformaStatus::Draft)
        ->and($proforma->lines)->toHaveCount(1)
        ->and($proforma->total)->toBe(118_000); // 100k + 18% TVA
});

test('saveDraft échoue sans client', function () {
    ['user' => $user] = createSmeForProformaForm();

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.form')
        ->set('lines', [['description' => 'X', 'quantity' => 1, 'unit_price' => 10_000]])
        ->call('saveDraft')
        ->assertHasErrors(['clientId']);
});

test('saveDraft échoue avec validUntil avant issuedAt', function () {
    ['user' => $user, 'company' => $company] = createSmeForProformaForm();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.form')
        ->set('clientId', $client->id)
        ->set('issuedAt', '2026-01-15')
        ->set('validUntil', '2026-01-10')
        ->set('lines', [['description' => 'X', 'quantity' => 1, 'unit_price' => 10_000]])
        ->call('saveDraft')
        ->assertHasErrors(['validUntil']);
});

test('mount() en édition charge les champs proforma existants', function () {
    ['user' => $user, 'company' => $company] = createSmeForProformaForm();
    $client = Client::factory()->create(['company_id' => $company->id]);

    $proforma = Proforma::unguarded(fn () => Proforma::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'FYK-PRO-EDIT01',
        'currency' => 'XOF',
        'status' => ProformaStatus::Draft->value,
        'issued_at' => now(),
        'valid_until' => now()->addDays(30),
        'subtotal' => 100_000,
        'tax_amount' => 18_000,
        'total' => 118_000,
        'dossier_reference' => 'DOSSIER-X',
        'payment_terms' => 'Net 60',
        'delivery_terms' => '7 jours',
    ]));

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.form', ['proforma' => $proforma])
        ->assertSet('isEditing', true)
        ->assertSet('reference', 'FYK-PRO-EDIT01')
        ->assertSet('dossierReference', 'DOSSIER-X')
        ->assertSet('paymentTerms', 'Net 60')
        ->assertSet('deliveryTerms', '7 jours');
});

test('mount() en édition redirige vers l\'index si la proforma est PoReceived', function () {
    ['user' => $user, 'company' => $company] = createSmeForProformaForm();
    $client = Client::factory()->create(['company_id' => $company->id]);

    $proforma = Proforma::unguarded(fn () => Proforma::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'FYK-PRO-LOCK01',
        'currency' => 'XOF',
        'status' => ProformaStatus::PoReceived->value,
        'issued_at' => now(),
        'valid_until' => now()->addDays(30),
        'subtotal' => 0, 'tax_amount' => 0, 'total' => 0,
    ]));

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.form', ['proforma' => $proforma])
        ->assertRedirect(route('pme.proformas.index'));
});
