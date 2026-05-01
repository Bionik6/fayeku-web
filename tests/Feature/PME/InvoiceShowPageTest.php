<?php

use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\PaymentMethod;
use App\Enums\PME\ReminderChannel;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Models\PME\Payment;
use App\Models\PME\Reminder;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Middle of the week so weekend guard on reminders does not interfere.
    $this->travelTo(now()->startOfWeek()->addDays(2)->setHour(10));
});

/**
 * @return array{user: User, company: Company}
 */
function createSmeForShow(): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme']);
    $company->users()->attach($user->id, ['role' => 'owner']);

    return compact('user', 'company');
}

function makeShowPageInvoice(Company $company, array $overrides = []): Invoice
{
    $client = Client::factory()->create(['company_id' => $company->id]);

    return Invoice::unguarded(fn () => Invoice::create(array_merge([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'FYK-FAC-'.fake()->unique()->bothify('??????'),
        'currency' => 'XOF',
        'status' => InvoiceStatus::Sent->value,
        'issued_at' => now()->subDays(5),
        'due_at' => now()->addDays(25),
        'subtotal' => 100_000,
        'tax_amount' => 18_000,
        'total' => 118_000,
        'amount_paid' => 0,
    ], $overrides)));
}

// ─── Access & security ────────────────────────────────────────────────────────

test('un visiteur non authentifié est redirigé vers la connexion', function () {
    ['company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company);

    $this->get(route('pme.invoices.show', $invoice))
        ->assertRedirect(route('login'));
});

test('le propriétaire PME accède à la fiche facture', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company);

    $this->actingAs($user)
        ->get(route('pme.invoices.show', $invoice))
        ->assertOk()
        ->assertSee($invoice->reference);
});

test('un utilisateur d\'une autre PME ne peut pas voir la facture', function () {
    ['company' => $ownerCompany] = createSmeForShow();
    $invoice = makeShowPageInvoice($ownerCompany);

    ['user' => $intruder] = createSmeForShow();

    $this->actingAs($intruder)
        ->get(route('pme.invoices.show', $invoice))
        ->assertNotFound();
});

// ─── KPIs ─────────────────────────────────────────────────────────────────────

test('la bande KPI affiche montant TTC, reste dû, relances et prochaine relance', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, [
        'status' => InvoiceStatus::PartiallyPaid->value,
        'total' => 100_000,
        'amount_paid' => 40_000,
        'due_at' => now()->addDays(5),
    ]);

    $response = $this->actingAs($user)->get(route('pme.invoices.show', $invoice));

    $response->assertOk()
        ->assertSee('Montant TTC')
        ->assertSee('Reste dû')
        ->assertSee('Relances')
        ->assertSee('Prochaine relance');
});

test('sentRemindersCount reflète le nombre de relances liées', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(10),
    ]);

    Reminder::unguarded(fn () => Reminder::create([
        'invoice_id' => $invoice->id,
        'channel' => ReminderChannel::Email->value,
        'mode' => 'auto',
        'sent_at' => now()->subDays(3),
    ]));
    Reminder::unguarded(fn () => Reminder::create([
        'invoice_id' => $invoice->id,
        'channel' => ReminderChannel::WhatsApp->value,
        'mode' => 'auto',
        'sent_at' => now()->subDays(1),
    ]));

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->assertSet('sentRemindersCount', 2);
});

test('nextUpcomingReminder retourne la prochaine relance planifiée ou null', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();

    $invoice = makeShowPageInvoice($company, [
        'status' => InvoiceStatus::Sent->value,
        'due_at' => now()->addDays(10),
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice]);

    $next = $component->get('nextUpcomingReminder');

    expect($next)->not->toBeNull();
    expect($next)->toHaveKeys(['at', 'offset', 'days_from_now']);
    expect($next['offset'])->toBeGreaterThan(0);
});

test('nextUpcomingReminder est null pour une facture payée', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, [
        'status' => InvoiceStatus::Paid->value,
        'amount_paid' => 118_000,
        'paid_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->assertSet('nextUpcomingReminder', null);
});

// ─── Carte client ─────────────────────────────────────────────────────────────

test('la carte client affiche les coordonnées et un lien « Voir la fiche client »', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();

    $client = Client::factory()->create([
        'company_id' => $company->id,
        'name' => 'LafargeHolcim Sénégal',
        'email' => 'dsi@lafarge.sn',
        'phone' => '+221338600008',
        'address' => 'Zone Industrielle',
        'tax_id' => 'SN2024LAF0007',
    ]);

    $invoice = Invoice::unguarded(fn () => Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'FYK-FAC-TEST01',
        'currency' => 'XOF',
        'status' => InvoiceStatus::Sent->value,
        'issued_at' => now(),
        'due_at' => now()->addDays(30),
        'subtotal' => 100_000,
        'tax_amount' => 18_000,
        'total' => 118_000,
        'amount_paid' => 0,
    ]));

    $response = $this->actingAs($user)->get(route('pme.invoices.show', $invoice));

    $response->assertOk()
        ->assertSee('LafargeHolcim Sénégal')
        ->assertSee('dsi@lafarge.sn')
        ->assertSee('SN2024LAF0007')
        ->assertSee('Voir la fiche client')
        ->assertSee(route('pme.clients.show', $client->id), escape: false);
});

test('le bouton « Modifier la facture » apparaît dans les actions rapides pour une facture envoyée', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, ['status' => InvoiceStatus::Sent->value]);

    $this->actingAs($user)
        ->get(route('pme.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('Modifier la facture')
        ->assertSee(route('pme.invoices.edit', $invoice), escape: false);
});

test('le bouton « Modifier la facture » n\'apparaît pas pour une facture payée', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, [
        'status' => InvoiceStatus::Paid->value,
        'amount_paid' => 118_000,
        'paid_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('pme.invoices.show', $invoice))
        ->assertOk()
        ->assertDontSee('Modifier la facture');
});

test('la carte client affiche le délai moyen et la date de la dernière facture', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();

    $client = Client::factory()->create(['company_id' => $company->id, 'name' => 'AUCHAN Sénégal']);

    // Une facture passée payée pour calculer le délai moyen.
    Invoice::unguarded(fn () => Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'FYK-FAC-PASS01',
        'currency' => 'XOF',
        'status' => InvoiceStatus::Paid->value,
        'issued_at' => now()->subDays(40),
        'paid_at' => now()->subDays(3),
        'due_at' => now()->subDays(10),
        'subtotal' => 100_000,
        'tax_amount' => 0,
        'total' => 100_000,
        'amount_paid' => 100_000,
    ]));

    $current = makeShowPageInvoice($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-FAC-NOW01',
    ]);

    $this->actingAs($user)
        ->get(route('pme.invoices.show', $current))
        ->assertOk()
        ->assertSee('AUCHAN Sénégal')
        ->assertSee('Délai moyen 37 jours')
        ->assertSee('Dernière facture');
});

// ─── Status chip ──────────────────────────────────────────────────────────────

test('le bandeau de statut affiche le libellé localisé', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(10),
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->assertSee('En retard');
});

// ─── Send invoice ─────────────────────────────────────────────────────────────

test('sendInvoice fait passer Draft → Sent', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, ['status' => InvoiceStatus::Draft->value]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('sendInvoice')
        ->assertOk();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
});

// ─── Mark as paid ─────────────────────────────────────────────────────────────

test('markAsPaid bascule la facture en Payée avec amount_paid égal au total', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, ['status' => InvoiceStatus::Sent->value]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('markAsPaid')
        ->assertOk();

    $fresh = $invoice->fresh();
    expect($fresh->status)->toBe(InvoiceStatus::Paid);
    expect($fresh->amount_paid)->toBe($fresh->total);
    expect($fresh->paid_at)->not->toBeNull();
});

// ─── Partial payments ─────────────────────────────────────────────────────────

test('recordPayment combine la date saisie avec l\'heure courante', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, [
        'status' => InvoiceStatus::Sent->value,
        'total' => 100_000,
    ]);

    $this->travelTo(now()->setTime(14, 32, 45));

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openPaymentModal')
        ->set('paymentAmount', '40000')
        ->set('paymentPaidAt', now()->toDateString())
        ->set('paymentMethod', PaymentMethod::Transfer->value)
        ->call('recordPayment');

    $payment = $invoice->fresh()->payments->first();

    expect($payment->paid_at->format('H:i:s'))->toBe('14:32:45');
    expect($payment->paid_at->toDateString())->toBe(now()->toDateString());
});

test('recordPayment crée un paiement et bascule en partiellement payée', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, [
        'status' => InvoiceStatus::Sent->value,
        'total' => 100_000,
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openPaymentModal')
        ->set('paymentAmount', '40000')
        ->set('paymentPaidAt', now()->toDateString())
        ->set('paymentMethod', PaymentMethod::Transfer->value)
        ->call('recordPayment')
        ->assertOk();

    $fresh = $invoice->fresh();
    expect($fresh->amount_paid)->toBe(40_000);
    expect($fresh->status)->toBe(InvoiceStatus::PartiallyPaid);
    expect($fresh->payments)->toHaveCount(1);
});

test('recordPayment qui solde le total bascule la facture en Payée', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, [
        'status' => InvoiceStatus::PartiallyPaid->value,
        'total' => 100_000,
        'amount_paid' => 60_000,
    ]);
    Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'amount' => 60_000,
        'paid_at' => now()->subDay(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openPaymentModal')
        ->set('paymentAmount', '40000')
        ->set('paymentPaidAt', now()->toDateString())
        ->call('recordPayment');

    $fresh = $invoice->fresh();
    expect($fresh->status)->toBe(InvoiceStatus::Paid);
    expect($fresh->amount_paid)->toBe(100_000);
    expect($fresh->paid_at)->not->toBeNull();
});

test('deletePayment retire le paiement et recalcule amount_paid', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, [
        'status' => InvoiceStatus::PartiallyPaid->value,
        'total' => 100_000,
        'amount_paid' => 40_000,
    ]);
    $payment = Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'amount' => 40_000,
        'paid_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('deletePayment', $payment->id)
        ->assertOk();

    expect(Payment::query()->whereKey($payment->id)->exists())->toBeFalse();
    expect($invoice->fresh()->amount_paid)->toBe(0);
});

// ─── Reminders ───────────────────────────────────────────────────────────────

test('sendReminderNow refuse les factures non éligibles', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, [
        'status' => InvoiceStatus::Paid->value,
        'amount_paid' => 118_000,
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('sendReminderNow')
        ->assertDispatched('toast');

    expect($invoice->fresh()->reminders)->toHaveCount(0);
});

// ─── Delete invoice ──────────────────────────────────────────────────────────

test('deleteInvoice supprime la facture et flashe un toast de succès', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, ['status' => InvoiceStatus::Draft->value]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('deleteInvoice')
        ->assertRedirect(route('pme.invoices.index'))
        ->assertSessionHas('success');

    expect(Invoice::query()->whereKey($invoice->id)->exists())->toBeFalse();
});

// ─── Draft invoices : pas de paiement, pas de relance ────────────────────────

test('une facture en brouillon ne propose pas d\'enregistrement de paiement', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, ['status' => InvoiceStatus::Draft->value]);

    $this->actingAs($user)
        ->get(route('pme.invoices.show', $invoice))
        ->assertOk()
        ->assertDontSee('Paiements enregistrés')
        ->assertDontSee('Enregistrer un paiement');
});

test('une facture en brouillon n\'affiche pas l\'action de relance dans l\'activité', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, ['status' => InvoiceStatus::Draft->value]);

    $this->actingAs($user)
        ->get(route('pme.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('Activité')
        ->assertDontSee('Relancer maintenant')
        ->assertDontSee('Relance prévue');
});

test('openPaymentModal refuse sur un brouillon', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, ['status' => InvoiceStatus::Draft->value]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openPaymentModal')
        ->assertSet('showPaymentModal', false)
        ->assertDispatched('toast');
});

test('recordPayment refuse sur un brouillon même si appelé directement', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, ['status' => InvoiceStatus::Draft->value]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->set('paymentAmount', '10000')
        ->set('paymentPaidAt', now()->toDateString())
        ->set('paymentMethod', PaymentMethod::Transfer->value)
        ->call('recordPayment')
        ->assertDispatched('toast');

    expect($invoice->fresh()->payments)->toHaveCount(0);
});

test('openReminderPreview ouvre le slide-over pour une facture éligible', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(10),
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openReminderPreview')
        ->assertSet('previewInvoiceId', $invoice->id);
});

test('openReminderPreview refuse et ne crée pas de relance pour une facture non éligible', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, [
        'status' => InvoiceStatus::Paid->value,
        'amount_paid' => 118_000,
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openReminderPreview')
        ->assertSet('previewInvoiceId', null)
        ->assertDispatched('toast');

    expect($invoice->fresh()->reminders)->toHaveCount(0);
});

test('closeReminderPreview ferme le slide-over', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(10),
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openReminderPreview')
        ->call('closeReminderPreview')
        ->assertSet('previewInvoiceId', null);
});

test('le bouton « Relancer le client » déclenche openReminderPreview et non sendReminderNow', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(10),
    ]);

    $html = (string) $this->actingAs($user)
        ->get(route('pme.invoices.show', $invoice))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('wire:click="openReminderPreview"');
});

test('sendReminderNow refuse sur un brouillon', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, ['status' => InvoiceStatus::Draft->value]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('sendReminderNow')
        ->assertDispatched('toast');

    expect($invoice->fresh()->reminders)->toHaveCount(0);
});

// ─── Timeline ────────────────────────────────────────────────────────────────

test('la timeline inclut les relances sans sent_at (fallback sur created_at)', function () {
    ['company' => $company] = createSmeForShow();

    $invoice = makeShowPageInvoice($company, [
        'issued_at' => now()->subDays(10),
    ]);

    Reminder::unguarded(fn () => Reminder::create([
        'invoice_id' => $invoice->id,
        'channel' => ReminderChannel::Sms->value,
        'mode' => 'manual',
        'sent_at' => null,
        'created_at' => now()->subDays(1),
        'updated_at' => now()->subDays(1),
    ]));

    $events = $invoice->fresh(['reminders', 'payments'])->timeline();
    $reminderEvents = $events->where('type', 'reminder');

    expect($reminderEvents)->toHaveCount(1);
});

test('la page n\'affiche plus les boutons d\'action dupliqués dans le header', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, ['status' => InvoiceStatus::Draft->value]);

    $html = (string) $this->actingAs($user)
        ->get(route('pme.invoices.show', $invoice))
        ->assertOk()
        ->getContent();

    // "Modifier la facture" vit dans la sidebar Actions rapides, plus dans le header.
    expect(substr_count($html, 'Modifier la facture'))->toBe(1);
    // "Envoyer au client" ne doit apparaître qu'une fois (sidebar).
    expect(substr_count($html, 'Envoyer au client'))->toBe(1);
});

test('le bandeau de statut est informatif (aucun bouton d\'action)', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(10),
    ]);

    $html = (string) $this->actingAs($user)
        ->get(route('pme.invoices.show', $invoice))
        ->assertOk()
        ->getContent();

    // "Relancer le client" n'apparaît qu'une seule fois (sidebar), plus dans le bandeau.
    expect(substr_count($html, 'Relancer le client'))->toBe(1);
});

test('la timeline combine création, échéance, relance et paiement dans l\'ordre chronologique', function () {
    ['company' => $company] = createSmeForShow();

    // Échéance dans le passé pour éviter les relances à venir parasites.
    $invoice = makeShowPageInvoice($company, [
        'status' => InvoiceStatus::PartiallyPaid->value,
        'issued_at' => now()->subDays(20),
        'due_at' => now()->subDays(10),
        'amount_paid' => 40_000,
        'total' => 100_000,
    ]);
    Reminder::unguarded(fn () => Reminder::create([
        'invoice_id' => $invoice->id,
        'channel' => ReminderChannel::WhatsApp->value,
        'mode' => 'manual',
        'sent_at' => now()->subDays(5),
    ]));
    Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'amount' => 40_000,
        'paid_at' => now()->subDays(2),
    ]);

    $events = $invoice->fresh(['client', 'reminders', 'payments'])->timeline();
    $pastEvents = $events
        ->filter(fn ($e) => $e['type'] !== 'upcoming')
        ->pluck('type')
        ->values()
        ->all();

    expect($pastEvents)->toBe(['created', 'due_date', 'reminder', 'payment']);
});

test('la timeline inclut les relances à venir pour une facture non-brouillon', function () {
    ['company' => $company] = createSmeForShow();

    $invoice = makeShowPageInvoice($company, [
        'status' => InvoiceStatus::Sent->value,
        'issued_at' => now()->subDays(2),
        'due_at' => now()->addDays(10),
    ]);

    $types = $invoice->fresh(['client', 'reminders', 'payments'])
        ->timeline()
        ->pluck('type')
        ->all();

    expect($types)->toContain('created', 'due_date', 'upcoming');
});

test('la timeline n\'inclut aucune relance à venir pour un brouillon', function () {
    ['company' => $company] = createSmeForShow();

    $invoice = makeShowPageInvoice($company, [
        'status' => InvoiceStatus::Draft->value,
        'issued_at' => now()->subDays(2),
        'due_at' => now()->addDays(10),
    ]);

    $types = $invoice->fresh(['client', 'reminders', 'payments'])
        ->timeline()
        ->pluck('type')
        ->all();

    expect($types)->not->toContain('upcoming');
});

// ─── Send modal (Envoyer la facture) ─────────────────────────────────────────

test('openSendModal pré-remplit le téléphone + détecte le pays + bascule en email si pas de tel', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $client = Client::factory()->create(['company_id' => $company->id, 'phone' => '+221770000000']);
    $invoice = makeShowPageInvoice($company, ['client_id' => $client->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->assertSet('showSendModal', true)
        ->assertSet('sendChannel', 'whatsapp')
        ->assertSet('sendCountry', 'SN')
        ->assertSet('sendRecipient', '770000000');
});

test('le template facture suit le format demandé (Bonjour, prestation, échéance, modalités, signature)', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $company->update(['name' => 'Rassoul Electronique Services', 'sender_name' => 'Moussa Diop']);
    $invoice = makeShowPageInvoice($company);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal');

    $message = $component->get('sendMessage');

    expect($message)
        ->toStartWith('Bonjour,')
        ->not->toContain('M.')
        ->toContain('Suite à notre prestation')
        ->toContain($invoice->reference)
        ->toContain('TTC')
        ->toContain('échéance le')
        ->toContain(route('pme.invoices.pdf', $invoice->public_code))
        ->toContain('Wave, Orange Money, virement bancaire')
        ->toEndWith("Cordialement,\nMoussa Diop\nRassoul Electronique Services");
});

test('confirmSend WhatsApp construit wa.me/<international> + ouvre le canal', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $client = Client::factory()->create(['company_id' => $company->id, 'phone' => '+221770000000']);
    $invoice = makeShowPageInvoice($company, ['client_id' => $client->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->call('confirmSend')
        ->assertDispatched('open-external-url', function ($name, $params) {
            return str_starts_with($params['url'], 'https://wa.me/221770000000?text=');
        });
});

test('confirmSend Email construit mailto:client@... + ouvre le canal', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $client = Client::factory()->create(['company_id' => $company->id, 'email' => 'jean@client.sn']);
    $invoice = makeShowPageInvoice($company, ['client_id' => $client->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->set('sendChannel', 'email')
        ->set('sendRecipient', 'jean@client.sn')
        ->call('confirmSend')
        ->assertDispatched('open-external-url', function ($name, $params) {
            return str_starts_with($params['url'], 'mailto:jean@client.sn?subject=');
        });
});

test('confirmSend sur une facture Draft la passe automatiquement en Sent + toast', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $client = Client::factory()->create(['company_id' => $company->id, 'phone' => '+221770000000']);
    $invoice = makeShowPageInvoice($company, ['client_id' => $client->id, 'status' => InvoiceStatus::Draft->value]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->call('confirmSend')
        ->assertHasNoErrors()
        ->assertDispatched('open-external-url')
        ->assertDispatched('toast', function ($name, $params) {
            return ($params['type'] ?? null) === 'success'
                && str_contains((string) ($params['title'] ?? ''), 'envoyée');
        });

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
});

// ─── Layout des actions rapides : 3 primaires + dropdown "Plus d'actions" ───

test('Actions rapides facture Sent : 3 primaires visibles + dropdown Plus d\'actions', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $client = Client::factory()->create(['company_id' => $company->id, 'phone' => '+221770000000']);
    $invoice = makeShowPageInvoice($company, ['client_id' => $client->id, 'status' => InvoiceStatus::Sent->value]);

    $this->actingAs($user)
        ->get(route('pme.invoices.show', $invoice))
        ->assertOk()
        ->assertSeeText('Enregistrer un paiement')
        ->assertSeeText('Relancer le client')
        ->assertSeeText('Télécharger le PDF')
        ->assertSeeText("Plus d'actions");
});

test("Le dropdown Plus d'actions contient: Renvoyer/Modifier/Marquer payée/Voir client/Dupliquer/Supprimer", function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $client = Client::factory()->create(['company_id' => $company->id]);
    $invoice = makeShowPageInvoice($company, ['client_id' => $client->id, 'status' => InvoiceStatus::Sent->value]);

    $this->actingAs($user)
        ->get(route('pme.invoices.show', $invoice))
        ->assertOk()
        ->assertSeeText('Renvoyer au client')
        ->assertSeeText('Modifier la facture')
        ->assertSeeText('Marquer comme payée')
        ->assertSeeText('Voir le client')
        ->assertSeeText('Dupliquer')
        ->assertSeeText('Supprimer la facture');
});

test("facture Draft : pas de paiement/relance comme primaire, mais 'Envoyer au client' dans Plus d'actions", function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, ['status' => InvoiceStatus::Draft->value]);

    $response = $this->actingAs($user)
        ->get(route('pme.invoices.show', $invoice))
        ->assertOk();

    // Pas de paiement / relance en primaire (Draft : pas applicable)
    $response->assertDontSeeText('Enregistrer un paiement')
        ->assertDontSeeText('Relancer le client');

    // Mais Télécharger le PDF + Plus d'actions toujours visibles
    $response->assertSeeText('Télécharger le PDF')
        ->assertSeeText("Plus d'actions")
        // L'envoi initial est dans le dropdown sur Draft
        ->assertSeeText('Envoyer au client')
        ->assertSeeText('Modifier la facture');
});

test('facture Paid : seul Télécharger le PDF en primaire, dropdown limité (Voir client + Dupliquer)', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $client = Client::factory()->create(['company_id' => $company->id]);
    $invoice = makeShowPageInvoice($company, [
        'client_id' => $client->id,
        'status' => InvoiceStatus::Paid->value,
        'amount_paid' => 118_000,
        'paid_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->get(route('pme.invoices.show', $invoice))
        ->assertOk();

    // Aucun primaire payable / relance
    $response->assertDontSeeText('Enregistrer un paiement')
        ->assertDontSeeText('Relancer le client');

    // PDF + Plus d'actions
    $response->assertSeeText('Télécharger le PDF')
        ->assertSeeText("Plus d'actions");

    // Sur Paid : pas de "Renvoyer au client", pas de "Modifier", pas de "Marquer payée"
    $response->assertDontSeeText('Renvoyer au client')
        ->assertDontSeeText('Modifier la facture')
        ->assertDontSeeText('Marquer comme payée');
});

test('facture PartiallyPaid : Enregistrer paiement reste visible si remaining > 0', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, [
        'status' => InvoiceStatus::PartiallyPaid->value,
        'amount_paid' => 50_000, // remaining = 68 000
    ]);

    $this->actingAs($user)
        ->get(route('pme.invoices.show', $invoice))
        ->assertOk()
        ->assertSeeText('Enregistrer un paiement')
        ->assertSeeText('Relancer le client');
});

test('confirmSend sur une facture déjà Sent ne ré-affiche pas le toast de bascule', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $client = Client::factory()->create(['company_id' => $company->id, 'phone' => '+221770000000']);
    $invoice = makeShowPageInvoice($company, ['client_id' => $client->id, 'status' => InvoiceStatus::Sent->value]);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->call('confirmSend');

    $component->assertDispatched('open-external-url');

    $events = collect($component->effects['dispatches'] ?? []);
    $statusToast = $events->first(fn ($e) => ($e['name'] ?? null) === 'toast' && str_contains((string) ($e['params']['title'] ?? ''), 'envoyée'));
    expect($statusToast)->toBeNull();
});

test('openSendModal n\'ouvre pas si la facture est Paid ou Cancelled', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();

    foreach ([InvoiceStatus::Paid, InvoiceStatus::Cancelled] as $blockedStatus) {
        $invoice = makeShowPageInvoice($company, ['status' => $blockedStatus->value]);

        Livewire::actingAs($user)
            ->test('pages::pme.invoices.show', ['invoice' => $invoice])
            ->call('openSendModal')
            ->assertSet('showSendModal', false);
    }
});
