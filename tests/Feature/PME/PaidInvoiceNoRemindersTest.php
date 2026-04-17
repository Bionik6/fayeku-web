<?php

use App\Enums\PME\DunningStrategy;
use App\Enums\PME\InvoiceStatus;
use App\Jobs\PME\SendReminderJob;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\DunningTemplate;
use App\Models\PME\Invoice;
use App\Models\PME\Reminder;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Lundi, 10h — fenêtre d'envoi ouverte pour les relances automatiques.
    $this->travelTo(now()->startOfWeek()->setHour(10));

    foreach ([0, 3, 7, 15, 30] as $offset) {
        DunningTemplate::updateOrCreate(
            ['day_offset' => $offset],
            ['body' => "Rappel J+{$offset} pour {invoice_reference}.", 'active' => true]
        );
    }
});

function setupSmePaidScenario(array $invoiceOverrides = []): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme']);
    $company->users()->attach($user->id, ['role' => 'owner']);

    $client = Client::factory()->create([
        'company_id' => $company->id,
        'phone' => '+221771112233',
        'email' => 'client@example.com',
        'dunning_strategy' => DunningStrategy::Standard,
    ]);

    $invoice = Invoice::factory()
        ->forCompany($company)
        ->withClient($client)
        ->create(array_merge([
            'status' => InvoiceStatus::Paid,
            'due_at' => now()->subDays(10),
            'total' => 100_000,
            'amount_paid' => 100_000,
            'paid_at' => now()->subDay(),
            'reminders_enabled' => true,
        ], $invoiceOverrides));

    return compact('user', 'company', 'client', 'invoice');
}

// ─── Relances automatiques ───────────────────────────────────────────────────

it('ne dispatch aucune relance automatique pour une facture payée', function () {
    Bus::fake(SendReminderJob::class);

    setupSmePaidScenario();

    $this->artisan('reminders:process-auto')->assertSuccessful();

    Bus::assertNotDispatched(SendReminderJob::class);
    expect(Reminder::count())->toBe(0);
});

it('ne dispatch aucune relance automatique pour une facture annulée', function () {
    Bus::fake(SendReminderJob::class);

    setupSmePaidScenario([
        'status' => InvoiceStatus::Cancelled,
        'paid_at' => null,
        'amount_paid' => 0,
    ]);

    $this->artisan('reminders:process-auto')->assertSuccessful();

    Bus::assertNotDispatched(SendReminderJob::class);
});

it('canReceiveReminder() renvoie false pour une facture payée, annulée ou brouillon', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);

    foreach ([InvoiceStatus::Paid, InvoiceStatus::Cancelled, InvoiceStatus::Draft] as $status) {
        $invoice = Invoice::factory()->forCompany($company)->withClient($client)->create(['status' => $status]);

        expect($invoice->canReceiveReminder())->toBeFalse("Le statut {$status->value} devrait bloquer la relance");
    }

    foreach ([InvoiceStatus::Sent, InvoiceStatus::Overdue, InvoiceStatus::PartiallyPaid] as $status) {
        $invoice = Invoice::factory()->forCompany($company)->withClient($client)->create(['status' => $status]);

        expect($invoice->canReceiveReminder())->toBeTrue("Le statut {$status->value} devrait autoriser la relance");
    }
});

// ─── Relances manuelles — absence du bouton dans l'UI ────────────────────────

it('n\'affiche pas l\'action « Relancer le client » sur la page client pour une facture payée', function () {
    ['user' => $user, 'client' => $client] = setupSmePaidScenario();

    Livewire::actingAs($user)
        ->test('pages::pme.clients.show', ['client' => $client])
        ->assertDontSee('Relancer le client');
});

it('n\'affiche pas l\'action « Relancer le client » sur la page factures pour une facture payée', function () {
    ['user' => $user] = setupSmePaidScenario();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->assertDontSee('Relancer le client');
});

it('ne liste pas les factures payées dans la page recouvrement', function () {
    ['user' => $user] = setupSmePaidScenario();

    $rows = Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->get('invoiceRows');

    expect($rows)->toBeEmpty();
});

// ─── Relances manuelles — backend refuse l'action ────────────────────────────

it('bloque sendReminder depuis la page client pour une facture payée', function () {
    ['user' => $user, 'client' => $client, 'invoice' => $invoice] = setupSmePaidScenario();

    Livewire::actingAs($user)
        ->test('pages::pme.clients.show', ['client' => $client])
        ->call('sendReminder', $invoice->id)
        ->assertDispatched('toast', type: 'warning', title: 'Cette facture ne peut plus être relancée.');

    expect(Reminder::count())->toBe(0);
});

it('bloque sendReminder depuis la page factures pour une facture payée', function () {
    ['user' => $user, 'invoice' => $invoice] = setupSmePaidScenario();

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.index')
        ->call('sendReminder', $invoice->id)
        ->assertDispatched('toast', type: 'warning', title: 'Cette facture ne peut plus être relancée.');

    expect(Reminder::count())->toBe(0);
});

it('bloque sendReminder depuis la page recouvrement pour une facture payée', function () {
    ['user' => $user, 'invoice' => $invoice] = setupSmePaidScenario();

    Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->call('sendReminder', $invoice->id)
        ->assertDispatched('toast', type: 'warning', title: 'Cette facture ne peut plus être relancée.');

    expect(Reminder::count())->toBe(0);
});

it('bloque sendReminder depuis la page trésorerie pour une facture payée', function () {
    ['user' => $user, 'invoice' => $invoice] = setupSmePaidScenario();

    Livewire::actingAs($user)
        ->test('pages::pme.treasury.index')
        ->call('sendReminder', $invoice->id)
        ->assertDispatched('toast', type: 'warning', title: 'Cette facture ne peut plus être relancée.');

    expect(Reminder::count())->toBe(0);
});

it('bloque sendReminder depuis la page dashboard pour une facture payée', function () {
    ['user' => $user, 'invoice' => $invoice] = setupSmePaidScenario();

    Livewire::actingAs($user)
        ->test('pages::pme.dashboard.index')
        ->call('sendReminder', $invoice->id)
        ->assertDispatched('toast', type: 'warning', title: 'Cette facture ne peut plus être relancée.');

    expect(Reminder::count())->toBe(0);
});

// ─── Weekend — relances bloquées ─────────────────────────────────────────────

it('ne dispatch aucune relance automatique le week-end', function () {
    Bus::fake(SendReminderJob::class);
    $this->travelTo(now()->startOfWeek()->addDays(5)->setHour(10)); // samedi

    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id, 'phone' => '+221771112233']);
    Invoice::factory()->forCompany($company)->withClient($client)->create([
        'status' => InvoiceStatus::Overdue,
        'due_at' => now()->subDays(5),
        'reminders_enabled' => true,
    ]);

    $this->artisan('reminders:process-auto')->assertSuccessful();

    Bus::assertNotDispatched(SendReminderJob::class);
});

it('bloque sendReminder manuel le samedi depuis la page recouvrement', function () {
    $this->travelTo(now()->startOfWeek()->addDays(5)->setHour(10));

    ['user' => $user, 'client' => $client, 'invoice' => $invoice] = setupSmePaidScenario([
        'status' => InvoiceStatus::Overdue,
        'due_at' => now()->subDays(5),
        'paid_at' => null,
        'amount_paid' => 0,
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->call('sendReminder', $invoice->id)
        ->assertDispatched('toast', type: 'warning', title: 'Les relances ne peuvent être envoyées qu\'en jour ouvré (lundi au vendredi).');

    expect(Reminder::count())->toBe(0);
});

it('bloque sendReminder manuel le dimanche depuis la page client', function () {
    $this->travelTo(now()->startOfWeek()->addDays(6)->setHour(10));

    ['user' => $user, 'client' => $client, 'invoice' => $invoice] = setupSmePaidScenario([
        'status' => InvoiceStatus::Overdue,
        'due_at' => now()->subDays(5),
        'paid_at' => null,
        'amount_paid' => 0,
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.clients.show', ['client' => $client])
        ->call('sendReminder', $invoice->id)
        ->assertDispatched('toast', type: 'warning', title: 'Les relances ne peuvent être envoyées qu\'en jour ouvré (lundi au vendredi).');

    expect(Reminder::count())->toBe(0);
});

it('bloque sendReminder manuel le week-end depuis la page trésorerie', function () {
    $this->travelTo(now()->startOfWeek()->addDays(5)->setHour(10));

    ['user' => $user, 'invoice' => $invoice] = setupSmePaidScenario([
        'status' => InvoiceStatus::Overdue,
        'due_at' => now()->subDays(5),
        'paid_at' => null,
        'amount_paid' => 0,
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.treasury.index')
        ->call('sendReminder', $invoice->id)
        ->assertDispatched('toast', type: 'warning', title: 'Les relances ne peuvent être envoyées qu\'en jour ouvré (lundi au vendredi).');

    expect(Reminder::count())->toBe(0);
});

it('autorise sendReminder manuel en jour ouvré (lundi)', function () {
    $this->travelTo(now()->startOfWeek()->setHour(10)); // lundi

    ['user' => $user, 'invoice' => $invoice] = setupSmePaidScenario([
        'status' => InvoiceStatus::Overdue,
        'due_at' => now()->subDays(5),
        'paid_at' => null,
        'amount_paid' => 0,
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->call('sendReminder', $invoice->id)
        ->assertDispatched('toast', type: 'success');

    expect(Reminder::count())->toBe(1);
});

// ─── Parcours : impayée → relance OK → marquer payée → relance bloquée ───────

it('bascule une facture de relançable à non relançable après paiement', function () {
    Bus::fake(SendReminderJob::class);

    ['company' => $company, 'client' => $client, 'invoice' => $invoice] = setupSmePaidScenario([
        'status' => InvoiceStatus::Overdue,
        'due_at' => now()->subDays(5),
        'paid_at' => null,
        'amount_paid' => 0,
    ]);

    // Avant paiement : la relance automatique est dispatché.
    $this->artisan('reminders:process-auto')->assertSuccessful();
    Bus::assertDispatched(SendReminderJob::class);

    // On marque la facture comme payée.
    $invoice->update([
        'status' => InvoiceStatus::Paid,
        'paid_at' => now(),
        'amount_paid' => $invoice->total,
    ]);

    Bus::fake(SendReminderJob::class);

    // Après paiement : plus aucune relance.
    $this->artisan('reminders:process-auto')->assertSuccessful();
    Bus::assertNotDispatched(SendReminderJob::class);

    expect($invoice->fresh()->canReceiveReminder())->toBeFalse();
});
