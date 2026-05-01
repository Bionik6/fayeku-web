<?php

use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\ProformaStatus;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Models\PME\Proforma;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Vue unifiée Devis & Proformas — section dédiée aux proformas dans
 * pages::pme.quotes.index. La route /pme/proformas redirige ici, pré-filtrée.
 */
function createSmeForProformaIndex(): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme', 'name' => 'Test PME SARL']);
    $company->users()->attach($user->id, ['role' => 'owner']);

    return compact('user', 'company');
}

function makeProforma(Company $company, array $overrides = []): Proforma
{
    $client = Client::factory()->create(['company_id' => $company->id]);

    return Proforma::unguarded(fn () => Proforma::create(array_merge([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'FYK-PRO-'.strtoupper(fake()->unique()->bothify('??????')),
        'currency' => 'XOF',
        'status' => ProformaStatus::Draft->value,
        'issued_at' => now(),
        'valid_until' => now()->addDays(30),
        'subtotal' => 100_000,
        'tax_amount' => 18_000,
        'total' => 118_000,
        'discount' => 0,
    ], $overrides)));
}

// ─── Access & redirect ───────────────────────────────────────────────────────

test('un visiteur non authentifié est redirigé vers la connexion', function () {
    $this->get(route('pme.proformas.index'))->assertRedirect(route('login'));
});

test('/pme/proformas redirige authentifié vers la vue unifiée /pme/quotes', function () {
    ['user' => $user] = createSmeForProformaIndex();

    $this->actingAs($user)
        ->get(route('pme.proformas.index'))
        ->assertRedirect('/pme/quotes');
});

test('un utilisateur SME peut accéder à la vue unifiée Devis & Proformas', function () {
    ['user' => $user] = createSmeForProformaIndex();

    $this->actingAs($user)->get(route('pme.quotes.index'))->assertOk();
});

// ─── Listing & rows (vue unifiée) ────────────────────────────────────────────

test('rows() inclut les proformas de la société courante avec type=proforma', function () {
    ['user' => $user, 'company' => $company] = createSmeForProformaIndex();
    $other = Company::factory()->create(['type' => 'sme']);

    makeProforma($company, ['reference' => 'FYK-PRO-MINE']);
    makeProforma($other, ['reference' => 'FYK-PRO-OTHER']);

    $rows = collect(Livewire::actingAs($user)->test('pages::pme.quotes.index')->get('rows'));

    expect($rows->pluck('reference'))->toContain('FYK-PRO-MINE')
        ->and($rows->pluck('reference'))->not->toContain('FYK-PRO-OTHER')
        ->and($rows->firstWhere('reference', 'FYK-PRO-MINE')['type'])->toBe('proforma');
});

test('rows expose le statut expired quand validUntil est dépassé pour une proforma envoyée', function () {
    ['user' => $user, 'company' => $company] = createSmeForProformaIndex();

    makeProforma($company, [
        'reference' => 'FYK-PRO-EXP',
        'status' => ProformaStatus::Sent->value,
        'valid_until' => now()->subDays(5),
    ]);

    $rows = collect(Livewire::actingAs($user)->test('pages::pme.quotes.index')->get('rows'));

    expect($rows->firstWhere('reference', 'FYK-PRO-EXP')['status_value'])->toBe('expired');
});

// ─── Status transitions via la page unifiée ──────────────────────────────────

test('markAsPoReceived bascule la proforma envoyée au statut PoReceived', function () {
    ['user' => $user, 'company' => $company] = createSmeForProformaIndex();

    $proforma = makeProforma($company, ['status' => ProformaStatus::Sent->value]);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.index')
        ->call('markAsPoReceived', $proforma->id);

    expect($proforma->fresh()->status)->toBe(ProformaStatus::PoReceived);
});

test('convertProformaToInvoice depuis la vue unifiée redirige vers l\'édition de la nouvelle facture', function () {
    ['user' => $user, 'company' => $company] = createSmeForProformaIndex();

    $proforma = makeProforma($company, ['status' => ProformaStatus::PoReceived->value]);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.index')
        ->call('convertProformaToInvoice', $proforma->id)
        ->assertRedirect();

    $invoice = Invoice::query()->where('proforma_id', $proforma->id)->first();
    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe(InvoiceStatus::Draft);
    expect($proforma->fresh()->status)->toBe(ProformaStatus::Converted);
});

test('deleteProforma supprime un brouillon de la société courante', function () {
    ['user' => $user, 'company' => $company] = createSmeForProformaIndex();

    $proforma = makeProforma($company);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.index')
        ->call('deleteProforma', $proforma->id);

    expect(Proforma::query()->find($proforma->id))->toBeNull();
});
