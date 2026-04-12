<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use App\Models\Auth\AccountantCompany;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Enums\PME\InvoiceStatus;
use App\Models\PME\Invoice;
use App\Models\Shared\User;

uses(RefreshDatabase::class);

/**
 * Helper : crée un user avec son cabinet et une PME gérée.
 *
 * @return array{user: User, firm: Company, sme: Company, relation: AccountantCompany}
 */
function setupShowPortfolio(): array
{
    $user = User::factory()->accountantFirm()->create();
    $firm = Company::factory()->accountantFirm()->create();
    $firm->users()->attach($user->id, ['role' => 'admin']);

    $sme = Company::factory()->create(['plan' => 'basique']);
    $relation = AccountantCompany::create([
        'accountant_firm_id' => $firm->id,
        'sme_company_id' => $sme->id,
        'started_at' => now()->subMonths(3),
    ]);

    return compact('user', 'firm', 'sme', 'relation');
}

/**
 * Helper : crée une facture pour une PME.
 *
 * @param  array<string, mixed>  $overrides
 */
function makeShowInvoice(Company $sme, array $overrides = []): Invoice
{
    return Invoice::unguarded(fn () => Invoice::create(array_merge([
        'company_id' => $sme->id,
        'client_id' => null,
        'reference' => 'FAC-'.fake()->unique()->numerify('###'),
        'status' => InvoiceStatus::Paid->value,
        'issued_at' => now(),
        'due_at' => now()->addDays(30),
        'paid_at' => now()->subDays(5),
        'subtotal' => 100_000,
        'tax_amount' => 18_000,
        'total' => 118_000,
        'amount_paid' => 118_000,
    ], $overrides)));
}

// ─── Accès ────────────────────────────────────────────────────────────────────

test('un invité est redirigé vers la page de connexion', function () {
    $sme = Company::factory()->create();
    $this->get(route('clients.show', $sme))->assertRedirect(route('login'));
});

test('un user sans cabinet reçoit une 403', function () {
    $user = User::factory()->create();
    $sme = Company::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme])
        ->assertForbidden();
});

test('un user dont le cabinet ne gère pas la PME reçoit une 403', function () {
    ['user' => $user] = setupShowPortfolio();
    $otherSme = Company::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $otherSme])
        ->assertForbidden();
});

test('un user autorisé peut voir la fiche du client', function () {
    ['user' => $user, 'sme' => $sme] = setupShowPortfolio();

    Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme])
        ->assertOk()
        ->assertSee($sme->name);
});

// ─── Rendu ────────────────────────────────────────────────────────────────────

test('le header affiche le nom, le plan et la référence', function () {
    ['user' => $user, 'sme' => $sme] = setupShowPortfolio();

    makeShowInvoice($sme, [
        'status' => InvoiceStatus::Overdue->value,
        'total' => 145_000,
        'amount_paid' => 0,
        'paid_at' => null,
        'due_at' => now()->subDays(12),
    ]);

    Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme])
        ->assertSee($sme->name)
        ->assertSee('Basique')
        ->assertSee('Client depuis')
        ->assertSee('Réf.')
        ->assertSee('1 facture en attente')
        ->assertSee('145 000 FCFA à recouvrer')
        ->assertSee('Taux de recouvrement de 0%');
});

test('le badge statut est visible', function () {
    ['user' => $user, 'sme' => $sme] = setupShowPortfolio();
    makeShowInvoice($sme);

    Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme])
        ->assertSee('À jour');
});

test('le badge statut à surveiller est affiché quand une facture reste ouverte sans criticité', function () {
    ['user' => $user, 'sme' => $sme] = setupShowPortfolio();

    makeShowInvoice($sme, [
        'status' => InvoiceStatus::Sent->value,
        'due_at' => now()->addDays(7),
        'paid_at' => null,
        'amount_paid' => 0,
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme]);

    expect($component->get('statusValue'))->toBe('watch');
    $component->assertSee('À surveiller');
});

test('le badge statut critique est affiché quand une facture est overdue > 60j', function () {
    ['user' => $user, 'sme' => $sme] = setupShowPortfolio();

    makeShowInvoice($sme, [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(65),
        'paid_at' => null,
        'amount_paid' => 0,
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme]);

    expect($component->get('statusValue'))->toBe('critical');
    $component->assertSee('Critique');
});

// ─── Stat cards ───────────────────────────────────────────────────────────────

test('stats.billed_month = somme des totaux du mois sélectionné', function () {
    ['user' => $user, 'sme' => $sme] = setupShowPortfolio();

    makeShowInvoice($sme, ['issued_at' => now(), 'total' => 200_000, 'amount_paid' => 200_000]);
    makeShowInvoice($sme, ['issued_at' => now(), 'total' => 150_000, 'amount_paid' => 150_000]);
    // mois précédent → ne doit pas être inclus
    makeShowInvoice($sme, ['issued_at' => now()->subMonthWithoutOverflow(), 'total' => 999_999, 'amount_paid' => 999_999]);

    $stats = Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme])
        ->get('stats');

    expect($stats['billed_month'])->toBe(350_000);
});

test('stats.collected = somme des amount_paid (all-time)', function () {
    ['user' => $user, 'sme' => $sme] = setupShowPortfolio();

    makeShowInvoice($sme, ['total' => 100_000, 'amount_paid' => 80_000]);
    makeShowInvoice($sme, ['issued_at' => now()->subMonth(), 'total' => 50_000, 'amount_paid' => 50_000]);

    $stats = Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme])
        ->get('stats');

    expect($stats['collected'])->toBe(130_000);
});

test('stats.pending_amount = somme des montants impayés', function () {
    ['user' => $user, 'sme' => $sme] = setupShowPortfolio();

    makeShowInvoice($sme, [
        'status' => InvoiceStatus::Overdue->value,
        'total' => 300_000,
        'amount_paid' => 100_000,
        'paid_at' => null,
    ]);
    makeShowInvoice($sme, [
        'status' => InvoiceStatus::PartiallyPaid->value,
        'total' => 200_000,
        'amount_paid' => 50_000,
        'paid_at' => null,
    ]);

    $stats = Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme])
        ->get('stats');

    expect($stats['pending_amount'])->toBe(350_000);
    expect($stats['pending_count'])->toBe(2);
});

test('stats.recovery_rate est calculé correctement', function () {
    ['user' => $user, 'sme' => $sme] = setupShowPortfolio();

    makeShowInvoice($sme, ['total' => 100_000, 'amount_paid' => 72_000]);

    $stats = Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme])
        ->get('stats');

    expect($stats['recovery_rate'])->toBe(72);
});

test('les cartes KPI utilisent le copy métier harmonisé', function () {
    ['user' => $user, 'sme' => $sme] = setupShowPortfolio();

    makeShowInvoice($sme, [
        'status' => InvoiceStatus::Overdue->value,
        'paid_at' => null,
        'amount_paid' => 0,
        'due_at' => now()->subDays(8),
    ]);

    Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme])
        ->assertSee('Montant en attente')
        ->assertSee('Délai moyen de paiement · Taux de recouvrement')
        ->assertSee('Cumul');
});

// ─── Table des factures ───────────────────────────────────────────────────────

test('la table filtre les factures sur le mois sélectionné', function () {
    ['user' => $user, 'sme' => $sme] = setupShowPortfolio();

    makeShowInvoice($sme, ['issued_at' => now(), 'reference' => 'FAC-CURRENT']);
    makeShowInvoice($sme, ['issued_at' => now()->subMonthWithoutOverflow(), 'reference' => 'FAC-OLD']);

    Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme])
        ->assertSee('FAC-CURRENT')
        ->assertDontSee('FAC-OLD');
});

test('la table utilise la colonne retard avec un format lisible', function () {
    ['user' => $user, 'sme' => $sme] = setupShowPortfolio();

    makeShowInvoice($sme, [
        'reference' => 'FAC-RETARD',
        'status' => InvoiceStatus::Overdue->value,
        'paid_at' => null,
        'amount_paid' => 0,
        'due_at' => now()->startOfDay()->subDays(12),
    ]);

    Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme])
        ->assertSee('Factures du mois')
        ->assertSee('Retard')
        ->assertSee('12 j')
        ->assertDontSee('J+12');
});

test('changer selectedPeriod recharge les factures du mois cible', function () {
    ['user' => $user, 'sme' => $sme] = setupShowPortfolio();

    makeShowInvoice($sme, ['issued_at' => now(), 'reference' => 'FAC-NOW']);
    makeShowInvoice($sme, ['issued_at' => now()->subMonthWithoutOverflow(), 'reference' => 'FAC-PREV']);

    $prevPeriod = now()->subMonthWithoutOverflow()->format('Y-m');

    $invoices = Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme])
        ->set('selectedPeriod', $prevPeriod)
        ->get('invoices');

    expect($invoices)->toHaveCount(1);
    expect($invoices->first()->reference)->toBe('FAC-PREV');
});

test('les factures sont chargées avec le client en eager loading', function () {
    ['user' => $user, 'sme' => $sme] = setupShowPortfolio();

    $client = Client::create([
        'company_id' => $sme->id,
        'name' => 'Immeuble ATLAN',
        'phone' => '+221700000001',
    ]);

    makeShowInvoice($sme, ['client_id' => $client->id]);

    $invoices = Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme])
        ->get('invoices');

    expect($invoices->first()->relationLoaded('client'))->toBeTrue();
    expect($invoices->first()->client->name)->toBe('Immeuble ATLAN');
});

// ─── Pagination ───────────────────────────────────────────────────────────────

test('par défaut perPage = 20', function () {
    ['user' => $user, 'sme' => $sme] = setupShowPortfolio();

    Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme])
        ->assertSet('perPage', 20);
});

test('le footer show-more est visible quand les factures dépassent perPage', function () {
    ['user' => $user, 'sme' => $sme] = setupShowPortfolio();

    for ($i = 0; $i < 5; $i++) {
        makeShowInvoice($sme);
    }

    Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme])
        ->set('perPage', 3)
        ->assertSee('Afficher tout');
});

test('changer perPage met à jour le nombre de lignes visibles', function () {
    ['user' => $user, 'sme' => $sme] = setupShowPortfolio();

    for ($i = 0; $i < 5; $i++) {
        makeShowInvoice($sme);
    }

    $component = Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme])
        ->set('perPage', 3);

    // Les 5 factures existent, mais seules 3 sont affichées (footer visible)
    expect($component->get('invoices'))->toHaveCount(5);
    $component->assertSee('Afficher tout');
});

// ─── Modale facture ───────────────────────────────────────────────────────────

test('viewInvoice() sélectionne la facture et la modale de détail est visible', function () {
    ['user' => $user, 'sme' => $sme] = setupShowPortfolio();
    $invoice = makeShowInvoice($sme);

    Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme])
        ->call('viewInvoice', $invoice->id)
        ->assertSet('selectedInvoiceId', $invoice->id)
        ->assertSee($invoice->reference);
});

test('closeInvoice() remet selectedInvoiceId à null', function () {
    ['user' => $user, 'sme' => $sme] = setupShowPortfolio();
    $invoice = makeShowInvoice($sme);

    Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme])
        ->call('viewInvoice', $invoice->id)
        ->call('closeInvoice')
        ->assertSet('selectedInvoiceId', null);
});

test('selectedInvoice eager-load les lignes de facture', function () {
    ['user' => $user, 'sme' => $sme] = setupShowPortfolio();
    $invoice = makeShowInvoice($sme);

    $component = Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme])
        ->call('viewInvoice', $invoice->id);

    $selected = $component->get('selectedInvoice');
    expect($selected)->not->toBeNull();
    expect($selected->relationLoaded('lines'))->toBeTrue();
    expect($selected->relationLoaded('client'))->toBeTrue();
});

// ─── Archive ──────────────────────────────────────────────────────────────────

test('archive() pose ended_at sur la relation AccountantCompany', function () {
    ['user' => $user, 'sme' => $sme, 'relation' => $relation] = setupShowPortfolio();

    Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme])
        ->call('archive');

    expect($relation->fresh()->ended_at)->not->toBeNull();
});

test('archive() redirige vers la liste des clients', function () {
    ['user' => $user, 'sme' => $sme] = setupShowPortfolio();

    Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme])
        ->call('archive')
        ->assertRedirect(route('clients.index'));
});

// ─── Navigation & routage ─────────────────────────────────────────────────────

test('la route HTTP clients.show retourne une réponse 200', function () {
    ['user' => $user, 'sme' => $sme] = setupShowPortfolio();

    $this->actingAs($user)
        ->get(route('clients.show', $sme))
        ->assertSuccessful()
        ->assertSee('Tableau de bord')
        ->assertSee('Clients')
        ->assertSee($sme->name);
});

test('la fiche se charge avec des factures et leurs relances sans erreur', function () {
    ['user' => $user, 'sme' => $sme] = setupShowPortfolio();

    // Plusieurs factures pour déclencher withCount('reminders')
    makeShowInvoice($sme, ['status' => InvoiceStatus::Paid->value]);
    makeShowInvoice($sme, ['status' => InvoiceStatus::Overdue->value, 'paid_at' => null, 'amount_paid' => 0]);

    Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme])
        ->assertOk()
        ->assertSee('Factures du mois')
        ->assertSee('Actions');
});

test('un client archivé n\'est plus accessible via la fiche', function () {
    ['user' => $user, 'sme' => $sme, 'relation' => $relation] = setupShowPortfolio();

    // Archiver manuellement la relation
    $relation->update(['ended_at' => now()]);

    Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme])
        ->assertForbidden();
});
