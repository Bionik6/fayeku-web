<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Auth\Models\Company;
use Modules\PME\Clients\Models\Client;
use Modules\PME\Collection\Models\Reminder;
use Modules\PME\Collection\Models\ReminderRule;
use Modules\PME\Invoicing\Enums\InvoiceStatus;
use Modules\PME\Invoicing\Models\Invoice;
use Modules\Shared\Models\User;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

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

// ─── Page rendering ──────────────────────────────────────────────────────────

test('la page affiche le titre et les sections principales', function () {
    ['user' => $user] = createSmeUserForCollection();

    Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->assertSee('Relances')
        ->assertSee('Configurer les')
        ->assertSee('Mode de relance');
});

test('la page crée les règles par défaut au premier chargement', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForCollection();

    Livewire::actingAs($user)
        ->test('pages::pme.collection.index');

    expect(ReminderRule::where('company_id', $company->id)->count())->toBe(4);
    expect($company->fresh()->reminder_settings)->not->toBeNull();
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

// ─── Toggle global ───────────────────────────────────────────────────────────

test('le toggle active/désactive les relances', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForCollection();

    $component = Livewire::actingAs($user)
        ->test('pages::pme.collection.index');

    expect($component->get('configEnabled'))->toBeFalse();

    $component->call('toggleGlobalReminders');

    expect($component->get('configEnabled'))->toBeTrue();
    expect($company->fresh()->reminder_settings['enabled'])->toBeTrue();
});

// ─── Config modal ────────────────────────────────────────────────────────────

test('le modal de configuration s\'ouvre et se ferme', function () {
    ['user' => $user] = createSmeUserForCollection();

    Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->assertSet('showConfigModal', false)
        ->call('openConfigModal')
        ->assertSet('showConfigModal', true)
        ->set('showConfigModal', false)
        ->assertSet('showConfigModal', false);
});

test('la sauvegarde de la configuration persiste en base', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForCollection();

    Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->call('openConfigModal')
        ->set('configMode', 'auto')
        ->set('configChannel', 'sms')
        ->set('configTone', 'ferme')
        ->set('configHourStart', 9)
        ->set('configHourEnd', 17)
        ->set('configExcludeWeekends', false)
        ->set('configAttachPdf', false)
        ->call('saveConfig')
        ->assertSet('showConfigModal', false)
        ->assertHasNoErrors();

    $settings = $company->fresh()->reminder_settings;

    expect($settings['mode'])->toBe('auto')
        ->and($settings['default_channel'])->toBe('sms')
        ->and($settings['default_tone'])->toBe('ferme')
        ->and($settings['send_hour_start'])->toBe(9)
        ->and($settings['send_hour_end'])->toBe(17)
        ->and($settings['exclude_weekends'])->toBeFalse()
        ->and($settings['attach_pdf'])->toBeFalse();
});

// ─── Reminder rules sync ─────────────────────────────────────────────────────

test('les jours de relance se synchronisent correctement', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForCollection();

    Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->call('openConfigModal')
        ->call('toggleRuleDay', 3)
        ->call('toggleRuleDay', 10)
        ->call('saveConfig');

    $activeDays = ReminderRule::query()
        ->where('company_id', $company->id)
        ->where('is_active', true)
        ->pluck('trigger_days')
        ->sort()
        ->values()
        ->all();

    expect($activeDays)->toBe([7, 10, 15, 30]);
});

// ─── Slide-overs ─────────────────────────────────────────────────────────────

test('l\'aperçu WhatsApp s\'ouvre pour une facture', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForCollection();
    $invoice = createOverdueInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->assertSet('previewInvoiceId', null)
        ->call('openPreview', $invoice->id)
        ->assertSet('previewInvoiceId', $invoice->id)
        ->assertSee('Aperçu de la relance');
});

test('la timeline s\'ouvre pour une facture', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForCollection();
    $invoice = createOverdueInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->call('openTimeline', $invoice->id)
        ->assertSet('timelineInvoiceId', $invoice->id)
        ->assertSee('Historique');
});

// ─── Send reminder ───────────────────────────────────────────────────────────

test('l\'envoi d\'une relance dispatche un message quand le service n\'est pas prêt', function () {
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

// ─── Computed counts & totals ───────────────────────────────────────────────

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

// ─── Exclusions ─────────────────────────────────────────────────────────────

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

// ─── Close actions ──────────────────────────────────────────────────────────

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

// ─── Reminders this month ───────────────────────────────────────────────────

test('remindersThisMonth ne compte que les relances du mois courant', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForCollection();
    $invoice = createOverdueInvoice($company, daysOverdue: 10);

    $reminderData = [
        'invoice_id' => $invoice->id,
        'channel' => 'whatsapp',
        'status' => 'sent',
        'sent_at' => now(),
    ];

    // 2 relances ce mois
    Reminder::unguarded(fn () => Reminder::create([...$reminderData, 'created_at' => now()]));
    Reminder::unguarded(fn () => Reminder::create([...$reminderData, 'created_at' => now()]));

    // 1 relance du mois dernier
    Reminder::unguarded(fn () => Reminder::create([...$reminderData, 'created_at' => now()->subMonthWithoutOverflow()]));

    $component = Livewire::actingAs($user)
        ->test('pages::pme.collection.index');

    expect($component->get('remindersThisMonth'))->toBe(2);
});

// ─── N+1 prevention ────────────────────────────────────────────────────────

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
