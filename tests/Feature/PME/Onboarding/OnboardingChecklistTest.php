<?php

use App\Enums\Auth\LegalForm;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function smeForChecklist(?array $companyAttrs = null): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(array_merge(
        ['type' => 'sme', 'setup_completed_at' => now()],
        $companyAttrs ?? []
    ));
    $company->users()->attach($user->id, ['role' => 'owner']);

    return [$user, $company];
}

test('un user juste après onboarding voit "compte créé" complété et le reste à faire', function () {
    [$user, $company] = smeForChecklist();

    $component = Livewire::actingAs($user)
        ->test('onboarding-checklist', ['company' => $company])
        ->assertSet('isVisible', true);

    expect($component->get('done'))->toBe(1)
        ->and($component->get('total'))->toBe(5);
});

test('compléter l\'identité (legal_form) coche le 2e item', function () {
    [$user, $company] = smeForChecklist(['legal_form' => LegalForm::Sarl->value]);

    $component = Livewire::actingAs($user)
        ->test('onboarding-checklist', ['company' => $company]);

    $items = $component->get('items');
    expect(collect($items)->firstWhere('key', 'identity')['done'])->toBeTrue();
});

test('renseigner l\'adresse depuis les paramètres coche le 2e item (legal_form non exposé en réglages)', function () {
    [$user, $company] = smeForChecklist(['address' => '15 Avenue Bourguiba']);

    $component = Livewire::actingAs($user)
        ->test('onboarding-checklist', ['company' => $company]);

    $items = $component->get('items');
    expect(collect($items)->firstWhere('key', 'identity')['done'])->toBeTrue();
});

test('renseigner le NINEA depuis les paramètres coche le 2e item', function () {
    [$user, $company] = smeForChecklist(['ninea' => 'SN20240001']);

    $component = Livewire::actingAs($user)
        ->test('onboarding-checklist', ['company' => $company]);

    $items = $component->get('items');
    expect(collect($items)->firstWhere('key', 'identity')['done'])->toBeTrue();
});

test('compléter la signature (sender_name) coche le 3e item', function () {
    [$user, $company] = smeForChecklist(['sender_name' => 'Awa']);

    $component = Livewire::actingAs($user)
        ->test('onboarding-checklist', ['company' => $company]);

    $items = $component->get('items');
    expect(collect($items)->firstWhere('key', 'signature')['done'])->toBeTrue();
});

test('ajouter un client coche le 4e item', function () {
    [$user, $company] = smeForChecklist();
    Client::factory()->create(['company_id' => $company->id]);

    $component = Livewire::actingAs($user)
        ->test('onboarding-checklist', ['company' => $company]);

    $items = $component->get('items');
    expect(collect($items)->firstWhere('key', 'first_client')['done'])->toBeTrue();
});

test('créer une facture coche le 5e item', function () {
    [$user, $company] = smeForChecklist();
    $client = Client::factory()->create(['company_id' => $company->id]);
    Invoice::factory()->create(['company_id' => $company->id, 'client_id' => $client->id]);

    $component = Livewire::actingAs($user)
        ->test('onboarding-checklist', ['company' => $company]);

    $items = $component->get('items');
    expect(collect($items)->firstWhere('key', 'first_invoice')['done'])->toBeTrue();
});

test('quand tous les items sont done, isVisible est false', function () {
    [$user, $company] = smeForChecklist([
        'legal_form' => LegalForm::Sarl->value,
        'sender_name' => 'Awa',
    ]);
    $client = Client::factory()->create(['company_id' => $company->id]);
    Invoice::factory()->create(['company_id' => $company->id, 'client_id' => $client->id]);

    Livewire::actingAs($user)
        ->test('onboarding-checklist', ['company' => $company])
        ->assertSet('isVisible', false);
});

test('dismiss met à jour onboarding_checklist_dismissed_at et masque la checklist', function () {
    [$user, $company] = smeForChecklist();

    Livewire::actingAs($user)
        ->test('onboarding-checklist', ['company' => $company])
        ->call('dismiss')
        ->assertSet('isVisible', false);

    expect($user->fresh()->onboarding_checklist_dismissed_at)->not->toBeNull();
});

test('un user déjà dismissé ne voit pas la checklist', function () {
    [$user, $company] = smeForChecklist();
    $user->update(['onboarding_checklist_dismissed_at' => now()]);

    Livewire::actingAs($user)
        ->test('onboarding-checklist', ['company' => $company])
        ->assertSet('isVisible', false);
});
