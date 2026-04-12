<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use App\Models\Auth\AccountantCompany;
use App\Models\Auth\Company;
use App\Enums\PME\InvoiceStatus;
use App\Models\PME\Invoice;
use App\Models\Shared\User;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée un utilisateur comptable avec un cabinet et une PME cliente liée.
 *
 * @return array{user: User, firm: Company, sme: Company}
 */
function createAccountantWithClient(array $smeAttributes = []): array
{
    $user = User::factory()->accountantFirm()->create();
    $firm = Company::factory()->accountantFirm()->create();
    $firm->users()->attach($user->id, ['role' => 'admin']);

    $sme = Company::factory()->create(array_merge(['type' => 'sme'], $smeAttributes));

    AccountantCompany::create([
        'accountant_firm_id' => $firm->id,
        'sme_company_id' => $sme->id,
        'started_at' => now()->subMonths(3),
    ]);

    return compact('user', 'firm', 'sme');
}

/**
 * Crée une facture pour la PME avec les attributs donnés.
 */
function createShowInvoice(Company $sme, array $attributes = []): Invoice
{
    return Invoice::factory()
        ->forCompany($sme)
        ->create(array_merge([
            'status' => InvoiceStatus::Draft,
            'issued_at' => now(),
            'due_at' => now()->addDays(30),
        ], $attributes));
}

// ─── Chargement de la page ─────────────────────────────────────────────────

test('la page fiche client se charge correctement pour un comptable', function () {
    ['user' => $user, 'sme' => $sme] = createAccountantWithClient();

    Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme])
        ->assertOk();
});

test('un utilisateur non authentifié ne peut pas accéder à la fiche client', function () {
    $sme = Company::factory()->create(['type' => 'sme']);

    $this->get(route('clients.show', $sme))
        ->assertRedirect(route('login'));
});

// ─── Filtre par défaut (mois courant) ─────────────────────────────────────

test('par défaut, invoiceFilter est vide et les factures du mois sont affichées', function () {
    ['user' => $user, 'sme' => $sme] = createAccountantWithClient();

    $invoiceThisMonth = createShowInvoice($sme, [
        'status' => InvoiceStatus::Sent,
        'issued_at' => now(),
    ]);
    $invoiceOldMonth = createShowInvoice($sme, [
        'status' => InvoiceStatus::Paid,
        'issued_at' => now()->subMonths(3),
    ]);

    Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme])
        ->assertSet('invoiceFilter', '')
        ->assertSee($invoiceThisMonth->reference)
        ->assertDontSee($invoiceOldMonth->reference);
});

// ─── filterByPaid ─────────────────────────────────────────────────────────

test('filterByPaid met invoiceFilter à paid et ne montre que les factures payées', function () {
    ['user' => $user, 'sme' => $sme] = createAccountantWithClient();

    $paidInvoice = createShowInvoice($sme, [
        'status' => InvoiceStatus::Paid,
        'issued_at' => now()->subMonths(2),
        'paid_at' => now()->subMonths(2),
    ]);
    $pendingInvoice = createShowInvoice($sme, [
        'status' => InvoiceStatus::Overdue,
        'issued_at' => now()->subMonth(),
        'due_at' => now()->subDays(5),
    ]);

    Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme])
        ->call('filterByPaid')
        ->assertSet('invoiceFilter', 'paid')
        ->assertSee($paidInvoice->reference)
        ->assertDontSee($pendingInvoice->reference);
});

// ─── filterByPending ──────────────────────────────────────────────────────

test('filterByPending met invoiceFilter à pending et ne montre que les factures en attente', function () {
    ['user' => $user, 'sme' => $sme] = createAccountantWithClient();

    $paidInvoice = createShowInvoice($sme, [
        'status' => InvoiceStatus::Paid,
        'issued_at' => now()->subMonths(2),
        'paid_at' => now()->subMonths(2),
    ]);
    $overdueInvoice = createShowInvoice($sme, [
        'status' => InvoiceStatus::Overdue,
        'issued_at' => now()->subMonth(),
        'due_at' => now()->subDays(10),
    ]);
    $sentInvoice = createShowInvoice($sme, [
        'status' => InvoiceStatus::Sent,
        'issued_at' => now()->subDays(5),
        'due_at' => now()->addDays(25),
    ]);

    Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme])
        ->call('filterByPending')
        ->assertSet('invoiceFilter', 'pending')
        ->assertDontSee($paidInvoice->reference)
        ->assertSee($overdueInvoice->reference)
        ->assertSee($sentInvoice->reference);
});

// ─── Toggle (double-clic désactive le filtre) ────────────────────────────

test('cliquer deux fois sur filterByPaid désactive le filtre', function () {
    ['user' => $user, 'sme' => $sme] = createAccountantWithClient();

    Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme])
        ->call('filterByPaid')
        ->assertSet('invoiceFilter', 'paid')
        ->call('filterByPaid')
        ->assertSet('invoiceFilter', '');
});

test('cliquer deux fois sur filterByPending désactive le filtre', function () {
    ['user' => $user, 'sme' => $sme] = createAccountantWithClient();

    Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme])
        ->call('filterByPending')
        ->assertSet('invoiceFilter', 'pending')
        ->call('filterByPending')
        ->assertSet('invoiceFilter', '');
});

// ─── Changement de période remet le filtre à zéro ────────────────────────

test('changer selectedPeriod remet invoiceFilter à vide', function () {
    ['user' => $user, 'sme' => $sme] = createAccountantWithClient();

    Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme])
        ->call('filterByPaid')
        ->assertSet('invoiceFilter', 'paid')
        ->set('selectedPeriod', now()->subMonth()->format('Y-m'))
        ->assertSet('invoiceFilter', '');
});

// ─── filterByBilledMonth remet le filtre à zéro ──────────────────────────

test('filterByBilledMonth remet invoiceFilter à vide', function () {
    ['user' => $user, 'sme' => $sme] = createAccountantWithClient();

    Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme])
        ->call('filterByPaid')
        ->assertSet('invoiceFilter', 'paid')
        ->call('filterByBilledMonth')
        ->assertSet('invoiceFilter', '');
});

// ─── Titre de section selon le filtre ─────────────────────────────────────

test('le titre de section indique les factures du mois par défaut', function () {
    ['user' => $user, 'sme' => $sme] = createAccountantWithClient();

    Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme])
        ->assertSet('invoiceFilter', '')
        ->assertSee('Factures du mois');
});

test('le titre de section indique les factures payées quand le filtre paid est actif', function () {
    ['user' => $user, 'sme' => $sme] = createAccountantWithClient();

    Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme])
        ->call('filterByPaid')
        ->assertSee('Factures payées · Tout historique');
});

test('le titre de section indique les factures en attente quand le filtre pending est actif', function () {
    ['user' => $user, 'sme' => $sme] = createAccountantWithClient();

    Livewire::actingAs($user)
        ->test('pages::compta.clients.show', ['company' => $sme])
        ->call('filterByPending')
        ->assertSee('Factures en attente · Tout historique');
});
