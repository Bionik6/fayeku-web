<?php

use App\Enums\PME\InvoiceStatus;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Models\PME\Reminder;
use App\Models\Shared\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Assure qu'on n'est jamais le week-end pour les tests d'envoi de relance.
    $this->travelTo(now()->startOfWeek()->setHour(10));
});

function createSmeUserForCollection(): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme']);
    $company->users()->attach($user->id, ['role' => 'owner']);

    return compact('user', 'company');
}

function createOverdueInvoice(Company $company, int $daysOverdue = 10, int $total = 100_000): Invoice
{
    $client = Client::factory()->create(['company_id' => $company->id]);

    return Invoice::factory()
        ->forCompany($company)
        ->withClient($client)
        ->create([
            'status' => InvoiceStatus::Overdue,
            'due_at' => now()->subDays($daysOverdue),
            'total' => $total,
            'amount_paid' => 0,
        ]);
}

// ─── Access control ──────────────────────────────────────────────────────────

test('un visiteur non authentifié est redirigé vers la connexion', function () {
    $this->get(route('pme.collection.index'))
        ->assertRedirect(route('login'));
});

test('un utilisateur SME peut accéder à la page de recouvrement', function () {
    ['user' => $user] = createSmeUserForCollection();

    $this->actingAs($user)
        ->get(route('pme.collection.index'))
        ->assertOk();
});

test('la page affiche le titre et les sections principales', function () {
    ['user' => $user] = createSmeUserForCollection();

    Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->assertSee('Relances');
});

test('la page ne contient plus la configuration des règles', function () {
    ['user' => $user] = createSmeUserForCollection();

    Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->assertDontSee('Configurer les règles')
        ->assertDontSee('Mode de relance')
        ->assertDontSee('Règles de relance');
});

// ─── KPIs ────────────────────────────────────────────────────────────────────

test('les KPIs affichent les bonnes valeurs', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForCollection();

    createOverdueInvoice($company, daysOverdue: 70, total: 200_000);
    createOverdueInvoice($company, daysOverdue: 45, total: 150_000);
    createOverdueInvoice($company, daysOverdue: 10, total: 100_000);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.collection.index');

    expect($component->get('criticalCount'))->toBe(1)
        ->and($component->get('criticalAmount'))->toBe(200_000)
        ->and($component->get('lateCount'))->toBe(1)
        ->and($component->get('lateAmount'))->toBe(150_000)
        ->and($component->get('pendingCount'))->toBe(1)
        ->and($component->get('pendingAmount'))->toBe(100_000);
});

// ─── Filters ─────────────────────────────────────────────────────────────────

test('la recherche filtre les factures', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForCollection();

    $client = Client::factory()->create(['company_id' => $company->id, 'name' => 'Dakar Pharma']);
    Invoice::factory()->forCompany($company)->withClient($client)->create([
        'status' => InvoiceStatus::Overdue,
        'due_at' => now()->subDays(10),
        'reference' => 'FYK-001',
    ]);

    $client2 = Client::factory()->create(['company_id' => $company->id, 'name' => 'Abidjan Tech']);
    Invoice::factory()->forCompany($company)->withClient($client2)->create([
        'status' => InvoiceStatus::Overdue,
        'due_at' => now()->subDays(15),
        'reference' => 'FYK-002',
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->set('search', 'Dakar')
        ->assertSee('Dakar Pharma')
        ->assertDontSee('Abidjan Tech');
});

// ─── Slide-overs ─────────────────────────────────────────────────────────────

test('l\'aperçu WhatsApp s\'ouvre pour une facture', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForCollection();
    $invoice = createOverdueInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->assertSet('previewInvoiceId', null)
        ->call('openPreview', $invoice->id)
        ->assertSet('previewInvoiceId', $invoice->id);
});

test('la timeline s\'ouvre pour une facture', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForCollection();
    $invoice = createOverdueInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->call('openTimeline', $invoice->id)
        ->assertSet('timelineInvoiceId', $invoice->id);
});

test('l\'envoi d\'une relance dispatche un toast', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForCollection();
    $invoice = createOverdueInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->call('sendReminder', $invoice->id)
        ->assertDispatched('toast');
});

// ─── Age filter ─────────────────────────────────────────────────────────────

test('le filtre critique n\'affiche que les factures > 60 jours', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForCollection();

    createOverdueInvoice($company, daysOverdue: 70, total: 200_000);
    createOverdueInvoice($company, daysOverdue: 10, total: 100_000);

    $rows = Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->set('ageFilter', 'critical')
        ->get('invoiceRows');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['days_overdue'])->toBeGreaterThan(60);
});

test('le filtre en retard affiche les factures entre 30 et 60 jours', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForCollection();

    createOverdueInvoice($company, daysOverdue: 70);
    createOverdueInvoice($company, daysOverdue: 45);
    createOverdueInvoice($company, daysOverdue: 10);

    $rows = Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->set('ageFilter', 'late')
        ->get('invoiceRows');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['days_overdue'])->toBeGreaterThanOrEqual(30)
        ->and($rows[0]['days_overdue'])->toBeLessThanOrEqual(60);
});

test('le filtre en attente affiche les factures < 30 jours', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForCollection();

    createOverdueInvoice($company, daysOverdue: 70);
    createOverdueInvoice($company, daysOverdue: 10);

    $rows = Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->set('ageFilter', 'pending')
        ->get('invoiceRows');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['days_overdue'])->toBeLessThan(30);
});

test('les compteurs reflètent les catégories de retard', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForCollection();

    createOverdueInvoice($company, daysOverdue: 70, total: 200_000);
    createOverdueInvoice($company, daysOverdue: 45, total: 150_000);
    createOverdueInvoice($company, daysOverdue: 10, total: 100_000);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.collection.index');

    $counts = $component->get('counts');

    expect($counts['critical'])->toBe(1)
        ->and($counts['late'])->toBe(1)
        ->and($counts['pending'])->toBe(1)
        ->and($counts['all'])->toBe(3);
});

test('le montant total en attente additionne les soldes restants', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForCollection();

    createOverdueInvoice($company, daysOverdue: 10, total: 200_000);
    createOverdueInvoice($company, daysOverdue: 15, total: 100_000);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.collection.index');

    expect($component->get('totalPendingAmount'))->toBe(300_000)
        ->and($component->get('totalPendingCount'))->toBe(2);
});

test('les factures payées ne sont pas dans les lignes', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForCollection();

    $client = Client::factory()->create(['company_id' => $company->id]);

    Invoice::factory()->forCompany($company)->withClient($client)->create([
        'status' => InvoiceStatus::Paid,
        'due_at' => now()->subDays(10),
    ]);

    createOverdueInvoice($company, daysOverdue: 5);

    $rows = Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->get('invoiceRows');

    expect($rows)->toHaveCount(1);
});

test('les factures dont l\'échéance est future sont exclues', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForCollection();

    $client = Client::factory()->create(['company_id' => $company->id]);

    Invoice::factory()->forCompany($company)->withClient($client)->create([
        'status' => InvoiceStatus::Sent,
        'due_at' => now()->addDays(10),
    ]);

    $rows = Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->get('invoiceRows');

    expect($rows)->toHaveCount(0);
});

test('closePreview remet le previewInvoiceId à null', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForCollection();
    $invoice = createOverdueInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->call('openPreview', $invoice->id)
        ->assertSet('previewInvoiceId', $invoice->id)
        ->call('closePreview')
        ->assertSet('previewInvoiceId', null);
});

test('closeTimeline remet le timelineInvoiceId à null', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForCollection();
    $invoice = createOverdueInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->call('openTimeline', $invoice->id)
        ->assertSet('timelineInvoiceId', $invoice->id)
        ->call('closeTimeline')
        ->assertSet('timelineInvoiceId', null);
});

test('remindersThisMonth ne compte que les relances du mois courant', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForCollection();
    $invoice = createOverdueInvoice($company, daysOverdue: 10);

    $reminderData = [
        'invoice_id' => $invoice->id,
        'channel' => 'whatsapp',
        'mode' => 'manual',
        'sent_at' => now(),
    ];

    Reminder::unguarded(fn () => Reminder::create([...$reminderData, 'created_at' => now()]));
    Reminder::unguarded(fn () => Reminder::create([...$reminderData, 'created_at' => now()]));
    Reminder::unguarded(fn () => Reminder::create([...$reminderData, 'created_at' => now()->subMonthWithoutOverflow()]));

    $component = Livewire::actingAs($user)
        ->test('pages::pme.collection.index');

    expect($component->get('remindersThisMonth'))->toBe(2);
});

test('overdueInvoices eager-load les relations client et reminders', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForCollection();
    createOverdueInvoice($company, daysOverdue: 10);

    $invoices = Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->get('overdueInvoices');

    expect($invoices)->toHaveCount(1)
        ->and($invoices->first()->relationLoaded('client'))->toBeTrue()
        ->and($invoices->first()->relationLoaded('reminders'))->toBeTrue();
});

test('invoiceRows() inclut client_id pour chaque facture', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForCollection();
    $invoice = createOverdueInvoice($company);

    $row = collect(
        Livewire::actingAs($user)->test('pages::pme.collection.index')->get('invoiceRows')
    )->firstWhere('id', $invoice->id);

    expect($row['client_id'])->toBe($invoice->client_id);
});

// ─── Dropdown ─────────────────────────────────────────────────────────────────

test('le dropdown affiche les actions standard pour une facture en retard', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForCollection();
    $invoice = createOverdueInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->assertSee('Voir la facture')
        ->assertSee('Afficher en PDF')
        ->assertSeeHtml(route('pme.invoices.pdf', $invoice->id))
        ->assertSee('Voir les relances')
        ->assertSee('Relancer le client')
        ->assertSee('Marquer comme payée')
        ->assertSee('Supprimer la facture');
});

test('"Voir le client" est affiché dans le dropdown quand la facture a un client', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForCollection();
    $invoice = createOverdueInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->assertSeeHtml(route('pme.clients.show', $invoice->client_id))
        ->assertSee('Voir le client');
});

test('viewInvoice() positionne selectedInvoiceId', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForCollection();
    $invoice = createOverdueInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->assertSet('selectedInvoiceId', null)
        ->call('viewInvoice', $invoice->id)
        ->assertSet('selectedInvoiceId', $invoice->id);
});

test('viewInvoice() affiche le modal avec la référence de la facture', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForCollection();
    $invoice = createOverdueInvoice($company);
    $invoice->update(['reference' => 'FAC-COLL-001']);

    Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->call('viewInvoice', $invoice->id)
        ->assertSee('FAC-COLL-001');
});

test('closeInvoice() remet selectedInvoiceId à null', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForCollection();
    $invoice = createOverdueInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->call('viewInvoice', $invoice->id)
        ->assertSet('selectedInvoiceId', $invoice->id)
        ->call('closeInvoice')
        ->assertSet('selectedInvoiceId', null);
});

test('viewInvoice() refuse une facture d\'une autre PME', function () {
    ['user' => $user] = createSmeUserForCollection();
    $otherCompany = Company::factory()->create(['type' => 'sme']);
    $otherInvoice = createOverdueInvoice($otherCompany);

    Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->call('viewInvoice', $otherInvoice->id);
})->throws(ModelNotFoundException::class);

// ─── Mark paid / delete ──────────────────────────────────────────────────────

test('markAsPaid() met à jour le statut de la facture en Payée', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForCollection();
    $invoice = createOverdueInvoice($company, total: 150_000);

    Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->call('markAsPaid', $invoice->id);

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->amount_paid)->toBe(150_000)
        ->and($invoice->paid_at)->not->toBeNull();
});

test('markAsPaid() retire la facture de la liste des factures à relancer', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForCollection();
    $invoice = createOverdueInvoice($company);

    $component = Livewire::actingAs($user)->test('pages::pme.collection.index');
    expect($component->get('invoiceRows'))->toHaveCount(1);

    $component->call('markAsPaid', $invoice->id);
    expect($component->get('invoiceRows'))->toHaveCount(0);
});

test('deleteInvoice() supprime (soft-delete) la facture', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForCollection();
    $invoice = createOverdueInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->call('deleteInvoice', $invoice->id)
        ->assertDispatched('toast', type: 'success', title: 'La facture a été supprimée.');

    expect(Invoice::query()->find($invoice->id))->toBeNull()
        ->and(Invoice::withTrashed()->find($invoice->id))->not->toBeNull();
});
