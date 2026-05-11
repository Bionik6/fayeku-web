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

// ─── Cycle de vie ───────────────────────────────────────────────────────────

test('le cycle de vie facture est rendu juste après les KPIs', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company);

    $html = (string) $this->actingAs($user)
        ->get(route('pme.invoices.show', $invoice))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('data-document-lifecycle')
        ->and(strpos($html, 'Prochaine relance'))->toBeLessThan(strpos($html, 'data-document-lifecycle'))
        ->and(strpos($html, 'data-document-lifecycle'))->toBeLessThan(strpos($html, 'Aperçu de la facture'));
});

test('la fiche facture affiche le cycle de vie courant pour chaque état', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();

    $cases = [
        'draft' => [
            ['status' => InvoiceStatus::Draft->value],
            ['État : Brouillon — non encore envoyée', 'Le cycle de vie démarre à l’envoi'],
        ],
        'sent' => [
            ['status' => InvoiceStatus::Sent->value, 'sent_at' => now()->subDays(2), 'due_at' => now()->addDays(20)],
            ['État : Envoyée — en attente de paiement', 'FACTURE'],
        ],
        'overdue-derived' => [
            ['status' => InvoiceStatus::Sent->value, 'sent_at' => now()->subDays(30), 'due_at' => now()->subDays(12), 'amount_paid' => 0],
            ['État : Envoyée + en retard — alerte temporelle', 'En retard · J+12'],
        ],
        'partial' => [
            ['status' => InvoiceStatus::PartiallyPaid->value, 'sent_at' => now()->subDays(5), 'total' => 100_000, 'amount_paid' => 40_000],
            ['État : Partiellement payée — paiement en cours', '40 000 / 100 000 reçus'],
        ],
        'paid' => [
            ['status' => InvoiceStatus::Paid->value, 'sent_at' => now()->subDays(8), 'paid_at' => now()->subDay(), 'amount_paid' => 118_000],
            ['État : Payée — encaissement complet', 'Payée'],
        ],
        'cancelled' => [
            ['status' => InvoiceStatus::Cancelled->value, 'sent_at' => now()->subDays(8), 'cancelled_at' => now()->subDay()],
            ['État : Annulée — sortie de cycle', 'La facture a été annulée'],
        ],
    ];

    foreach ($cases as [$overrides, $expectedTexts]) {
        $invoice = makeShowPageInvoice($company, $overrides);
        $response = $this->actingAs($user)
            ->get(route('pme.invoices.show', $invoice))
            ->assertOk();

        foreach ($expectedTexts as $text) {
            $response->assertSeeText($text);
        }
    }
});

// ─── Carte client ─────────────────────────────────────────────────────────────

test('la carte client affiche les coordonnées et un lien « Voir la fiche »', function () {
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
        ->assertSee('Voir la fiche')
        ->assertSee(route('pme.clients.show', $client->id), escape: false);
});

test('le bouton « Modifier » apparaît dans le menu Actions pour une facture envoyée', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, ['status' => InvoiceStatus::Sent->value]);

    $this->actingAs($user)
        ->get(route('pme.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('Modifier')
        ->assertSee(route('pme.invoices.edit', $invoice), escape: false);
});

test('le bouton « Modifier » n\'apparaît pas pour une facture payée', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, [
        'status' => InvoiceStatus::Paid->value,
        'amount_paid' => 118_000,
        'paid_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('pme.invoices.show', $invoice))
        ->assertOk()
        ->assertDontSee(route('pme.invoices.edit', $invoice), escape: false);
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

    // Le lien d'édition vit dans le menu Actions, plus dans le header.
    expect(substr_count($html, route('pme.invoices.edit', $invoice)))->toBe(1);
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

    // Le bouton "Relancer" (action principale) apparaît une seule fois dans la sidebar.
    expect(substr_count($html, 'data-action="primary-remind"'))->toBe(1);
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
        ->toContain('Veuillez trouver notre facture n° '.$invoice->reference)
        ->toContain('Consulter la facture :')
        ->toContain(route('pme.invoices.pdf', $invoice->public_code))
        ->toContain('Échéance de paiement :')
        ->toContain('Moyens de paiement acceptés : Wave, Orange Money, virement bancaire.')
        ->toEndWith("Cordialement,\nMoussa Diop\nRassoul Electronique Services");
});

test('le bouton Envoyer de la modale rend une URL wa.me/<international> en data-send-url pour WhatsApp', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $client = Client::factory()->create(['company_id' => $company->id, 'phone' => '+221770000000']);
    $invoice = makeShowPageInvoice($company, ['client_id' => $client->id]);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal');

    $component->assertSeeHtml('data-send-url="https://wa.me/221770000000?text=')
        ->assertSeeHtml('data-can-send="1"');
});

test('le bouton Envoyer de la modale rend un mailto:client@... en data-send-url pour Email', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $client = Client::factory()->create(['company_id' => $company->id, 'email' => 'jean@client.sn']);
    $invoice = makeShowPageInvoice($company, ['client_id' => $client->id]);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->set('sendChannel', 'email')
        ->set('sendRecipient', 'jean@client.sn');

    $component->assertSeeHtml('data-send-url="mailto:jean@client.sn?subject=')
        ->assertSeeHtml('data-can-send="1"');
});

test('confirmSend ne dispatch plus open-external-url (l\'ouverture est faite côté client)', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $client = Client::factory()->create(['company_id' => $company->id, 'phone' => '+221770000000']);
    $invoice = makeShowPageInvoice($company, ['client_id' => $client->id, 'status' => InvoiceStatus::Draft->value]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->call('confirmSend')
        ->assertNotDispatched('open-external-url');
});

test('le bouton Envoyer expose data-can-send=0 quand le destinataire est vide (popup non ouvert côté client)', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $client = Client::factory()->create(['company_id' => $company->id, 'phone' => null, 'email' => null]);
    $invoice = makeShowPageInvoice($company, ['client_id' => $client->id]);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->set('sendRecipient', '');

    $component->assertSeeHtml('data-can-send="0"');
});

test('le bouton Envoyer appelle wire:click confirmSend via Alpine click handler', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $client = Client::factory()->create(['company_id' => $company->id, 'phone' => '+221770000000']);
    $invoice = makeShowPageInvoice($company, ['client_id' => $client->id]);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal');

    // Le click handler Alpine exécute window.open puis $wire.confirmSend()
    $component->assertSeeHtml('$wire.confirmSend()')
        ->assertSeeHtml('window.open($el.dataset.sendUrl');
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
        ->assertDispatched('toast', function ($name, $params) {
            return ($params['type'] ?? null) === 'success'
                && str_contains((string) ($params['title'] ?? ''), 'envoyée');
        });

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
});

// ─── Matrice d'actions : action principale + menu Actions ────────────────────

test('Actions facture Sent : Enregistrer un paiement en principal + menu Actions', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $client = Client::factory()->create(['company_id' => $company->id, 'phone' => '+221770000000']);
    $invoice = makeShowPageInvoice($company, ['client_id' => $client->id, 'status' => InvoiceStatus::Sent->value]);

    $this->actingAs($user)
        ->get(route('pme.invoices.show', $invoice))
        ->assertOk()
        ->assertSeeText('Enregistrer un paiement')
        ->assertSeeText('Renvoyer')
        ->assertSeeText('Relancer manuellement')
        ->assertSeeText('Aperçu PDF')
        ->assertSeeText('Télécharger PDF');
});

test('Menu Actions facture Sent contient: Renvoyer/Modifier/Dupliquer/Annuler', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $client = Client::factory()->create(['company_id' => $company->id]);
    $invoice = makeShowPageInvoice($company, ['client_id' => $client->id, 'status' => InvoiceStatus::Sent->value]);

    $this->actingAs($user)
        ->get(route('pme.invoices.show', $invoice))
        ->assertOk()
        ->assertSeeText('Renvoyer')
        ->assertSeeText('Modifier')
        ->assertSeeText('Voir le client')
        ->assertSeeText('Dupliquer')
        ->assertSeeText('Annuler')
        ->assertDontSeeText('Créer un avoir');
});

test('facture Draft : Émettre la facture en principal, pas de paiement/relance', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, ['status' => InvoiceStatus::Draft->value]);

    $response = $this->actingAs($user)
        ->get(route('pme.invoices.show', $invoice))
        ->assertOk();

    $response->assertDontSeeText('Enregistrer un paiement')
        ->assertDontSeeText('Relancer');

    $response->assertSeeText('Émettre la facture')
        ->assertSeeText('Modifier')
        ->assertSeeText('Supprimer');
});

test('facture Paid : Télécharger le reçu en principal', function () {
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

    $response->assertDontSeeText('Enregistrer un paiement')
        ->assertDontSeeText('Renvoyer');

    $response->assertSeeText('Télécharger le reçu')
        ->assertSeeText('Dupliquer');
});

test('facture PartiallyPaid : Enregistrer un paiement reste visible si remaining > 0', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, [
        'status' => InvoiceStatus::PartiallyPaid->value,
        'amount_paid' => 50_000,
    ]);

    $this->actingAs($user)
        ->get(route('pme.invoices.show', $invoice))
        ->assertOk()
        ->assertSeeText('Enregistrer un paiement')
        ->assertSeeText('Voir les paiements');
});

test('confirmSend sur une facture déjà Sent ne ré-affiche pas le toast de bascule', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $client = Client::factory()->create(['company_id' => $company->id, 'phone' => '+221770000000']);
    $invoice = makeShowPageInvoice($company, ['client_id' => $client->id, 'status' => InvoiceStatus::Sent->value]);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->call('confirmSend');

    $events = collect($component->effects['dispatches'] ?? []);
    $statusToast = $events->first(fn ($e) => ($e['name'] ?? null) === 'toast' && str_contains((string) ($e['params']['title'] ?? ''), 'envoyée'));
    expect($statusToast)->toBeNull();
});

test('?send=1 dans l\'URL auto-ouvre la modale d\'envoi', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $client = Client::factory()->create(['company_id' => $company->id, 'phone' => '+221770000000']);
    $invoice = makeShowPageInvoice($company, ['client_id' => $client->id, 'status' => InvoiceStatus::Draft->value]);

    $this->actingAs($user)->withSession([])
        ->get(route('pme.invoices.show', $invoice).'?send=1')
        ->assertOk()
        ->assertSeeLivewire('pages::pme.invoices.show');

    Livewire::actingAs($user)
        ->withQueryParams(['send' => '1'])
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->assertSet('showSendModal', true);
});

test('?send=1 ne rouvre pas la modale si la facture est Paid', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, ['status' => InvoiceStatus::Paid->value]);

    Livewire::actingAs($user)
        ->withQueryParams(['send' => '1'])
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->assertSet('showSendModal', false);
});

test('confirmSend transitionne une Draft avec acompte vers PartiallyPaid', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $client = Client::factory()->create(['company_id' => $company->id, 'phone' => '+221770000000']);
    $invoice = makeShowPageInvoice($company, [
        'client_id' => $client->id,
        'status' => InvoiceStatus::Draft->value,
        'subtotal' => 100_000,
        'tax_amount' => 0,
        'total' => 100_000,
        'amount_paid' => 30_000,
        'deposit_amount' => 30_000,
    ]);

    Payment::query()->create([
        'invoice_id' => $invoice->id,
        'amount' => 30_000,
        'is_deposit' => true,
        'paid_at' => now(),
        'method' => PaymentMethod::Cash,
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->call('confirmSend');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::PartiallyPaid);
});

test('confirmSend transitionne une Draft sans acompte vers Sent', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $client = Client::factory()->create(['company_id' => $company->id, 'phone' => '+221770000000']);
    $invoice = makeShowPageInvoice($company, [
        'client_id' => $client->id,
        'status' => InvoiceStatus::Draft->value,
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->call('confirmSend');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
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

// ─── Acompte section ────────────────────────────────────────────────────────

test('la section Acompte s\'affiche quand un acompte a été versé même en brouillon', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, [
        'status' => InvoiceStatus::Draft->value,
        'amount_paid' => 30_000,
        'deposit_amount' => 30_000,
    ]);

    Payment::query()->create([
        'invoice_id' => $invoice->id,
        'amount' => 30_000,
        'is_deposit' => true,
        'paid_at' => now(),
        'method' => PaymentMethod::Cash,
        'notes' => 'Acompte versé à la création',
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->assertSee('Avance / acompte déjà payé', escape: false)
        ->assertSee('Acompte versé à la création de la facture', escape: false);
});

test('la section Acompte n\'apparaît pas en l\'absence d\'acompte', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->assertDontSee('Acompte versé à la création');
});

test('le tableau Paiements enregistrés exclut le Payment is_deposit', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, [
        'status' => InvoiceStatus::PartiallyPaid->value,
        'amount_paid' => 75_000,
        'deposit_amount' => 30_000,
    ]);

    Payment::query()->create([
        'invoice_id' => $invoice->id,
        'amount' => 30_000,
        'is_deposit' => true,
        'paid_at' => now()->subDays(2),
        'method' => PaymentMethod::Cash,
        'reference' => 'DEPOSIT-REF',
        'notes' => 'Acompte',
    ]);

    Payment::query()->create([
        'invoice_id' => $invoice->id,
        'amount' => 45_000,
        'is_deposit' => false,
        'paid_at' => now(),
        'method' => PaymentMethod::Transfer,
        'reference' => 'MANUAL-REF',
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice]);

    // Manual payment reference appears in the Paiements table
    $component->assertSee('MANUAL-REF');
    // Deposit reference must NOT appear in the Paiements table
    $component->assertDontSee('DEPOSIT-REF');
});

test('l\'aperçu de la facture affiche Acompte versé et Reste à payer quand un acompte est présent', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, [
        'status' => InvoiceStatus::Draft->value,
        'subtotal' => 1_000_000,
        'tax_amount' => 0,
        'total' => 1_000_000,
        'amount_paid' => 250_000,
        'deposit_amount' => 250_000,
    ]);

    Payment::query()->create([
        'invoice_id' => $invoice->id,
        'amount' => 250_000,
        'is_deposit' => true,
        'paid_at' => now(),
        'method' => PaymentMethod::Cash,
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->assertSee('Acompte versé', escape: false)
        ->assertSee('Reste à payer', escape: false)
        ->assertSee('750 000', escape: false);
});

test('le message d\'envoi mentionne l\'acompte et le reste à payer quand un acompte est présent', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $client = Client::factory()->create(['company_id' => $company->id, 'phone' => '+221770000000']);
    $invoice = makeShowPageInvoice($company, [
        'client_id' => $client->id,
        'subtotal' => 1_000_000,
        'tax_amount' => 0,
        'total' => 1_000_000,
        'amount_paid' => 250_000,
        'deposit_amount' => 250_000,
    ]);

    Payment::query()->create([
        'invoice_id' => $invoice->id,
        'amount' => 250_000,
        'is_deposit' => true,
        'paid_at' => now(),
        'method' => PaymentMethod::Cash,
    ]);

    $message = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->get('sendMessage');

    expect($message)
        ->toContain('Veuillez trouver notre facture n°')
        ->toContain("d'un montant total de")
        ->toContain('TTC')
        ->toContain('Acompte déjà versé : 250 000')
        ->toContain('Reste à payer : 750 000')
        ->toContain('Consulter la facture :')
        ->toContain('Échéance de paiement :')
        ->toContain('Moyens de paiement acceptés :');
});

test('le message d\'envoi ne mentionne pas l\'acompte en son absence', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $client = Client::factory()->create(['company_id' => $company->id, 'phone' => '+221770000000']);
    $invoice = makeShowPageInvoice($company, ['client_id' => $client->id]);

    $message = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->get('sendMessage');

    expect($message)
        ->not->toContain('Acompte déjà versé')
        ->not->toContain('Reste à payer');
});

test('le message d\'envoi commence toujours par Bonjour générique sans nom de client', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $client = Client::factory()->create([
        'company_id' => $company->id,
        'name' => 'CBAO Groupe Attijariwafa',
        'phone' => '+221770000000',
    ]);
    $invoice = makeShowPageInvoice($company, ['client_id' => $client->id]);

    $message = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->get('sendMessage');

    expect($message)
        ->toStartWith('Bonjour,')
        ->not->toContain('CBAO Groupe Attijariwafa');
});

test('le message d\'envoi utilise le moyen de paiement spécifique quand un seul est sélectionné', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $client = Client::factory()->create(['company_id' => $company->id, 'phone' => '+221770000000']);
    $invoice = makeShowPageInvoice($company, [
        'client_id' => $client->id,
        'payment_method' => 'wave',
    ]);

    $message = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->get('sendMessage');

    expect($message)->toContain('Moyens de paiement acceptés : Wave.');
});

test('le message d\'envoi utilise la liste par défaut quand aucun moyen de paiement n\'est sélectionné', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $client = Client::factory()->create(['company_id' => $company->id, 'phone' => '+221770000000']);
    $invoice = makeShowPageInvoice($company, [
        'client_id' => $client->id,
        'payment_method' => null,
    ]);

    $message = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->get('sendMessage');

    expect($message)->toContain('Moyens de paiement acceptés : Wave, Orange Money, virement bancaire.');
});

test('l\'aperçu de la facture n\'affiche pas Reste à payer en l\'absence d\'acompte', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->assertDontSee('Reste à payer');
});

test('la section Paiements enregistrés affiche dont acompte quand un acompte existe', function () {
    ['user' => $user, 'company' => $company] = createSmeForShow();
    $invoice = makeShowPageInvoice($company, [
        'status' => InvoiceStatus::PartiallyPaid->value,
        'amount_paid' => 30_000,
        'deposit_amount' => 30_000,
    ]);

    Payment::query()->create([
        'invoice_id' => $invoice->id,
        'amount' => 30_000,
        'is_deposit' => true,
        'paid_at' => now(),
        'method' => PaymentMethod::Cash,
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->assertSee('dont acompte', escape: false);
});
