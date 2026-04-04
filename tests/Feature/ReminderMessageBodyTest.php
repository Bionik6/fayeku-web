<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Auth\Models\Company;
use Modules\PME\Clients\Models\Client;
use Modules\PME\Collection\Enums\ReminderChannel;
use Modules\PME\Collection\Models\Reminder;
use Modules\PME\Collection\Services\ReminderService;
use Modules\PME\Invoicing\Enums\InvoiceStatus;
use Modules\PME\Invoicing\Models\Invoice;
use Modules\Shared\Models\User;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * @return array{user: User, company: Company}
 */
function createReminderOwner(string $companyName = 'Sow BTP'): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create([
        'type' => 'sme',
        'name' => $companyName,
    ]);
    $company->users()->attach($user->id, ['role' => 'owner']);

    return compact('user', 'company');
}

function createClientWithPhone(Company $company, string $name = 'Dakar Pharma'): Client
{
    return Client::factory()->create([
        'company_id' => $company->id,
        'name' => $name,
        'phone' => '+221771112233',
    ]);
}

function createOverdueInvoiceForClient(Client $client, string $reference = 'FAC-TEST-01', int $total = 250_000): Invoice
{
    return Invoice::unguarded(fn () => Invoice::create([
        'company_id' => $client->company_id,
        'client_id' => $client->id,
        'reference' => $reference,
        'status' => InvoiceStatus::Overdue->value,
        'issued_at' => now()->subDays(20),
        'due_at' => now()->subDays(10),
        'subtotal' => $total,
        'tax_amount' => 0,
        'total' => $total,
        'amount_paid' => 0,
    ]));
}

// ─── ReminderService ──────────────────────────────────────────────────────────

test('ReminderService remplace le corps générique par le message personnalisé fourni', function () {
    ['company' => $company] = createReminderOwner();
    $client = createClientWithPhone($company);
    $invoice = createOverdueInvoiceForClient($client, 'FAC-SRV-01');

    $customBody = "Bonjour Dakar Pharma,\n\nVotre facture FAC-SRV-01 est en retard.\n\nCordialement,\nSow BTP";

    $reminder = app(ReminderService::class)->send($invoice, $company, ReminderChannel::WhatsApp, $customBody);

    expect($reminder->message_body)->toBe($customBody)
        ->and(Reminder::find($reminder->id)->message_body)->toBe($customBody);
});

test('ReminderService conserve le message générique quand aucun corps n est transmis', function () {
    ['company' => $company] = createReminderOwner();
    $client = createClientWithPhone($company);
    $invoice = createOverdueInvoiceForClient($client, 'FAC-SRV-02', 100_000);

    $reminder = app(ReminderService::class)->send($invoice, $company, ReminderChannel::WhatsApp);

    $expectedGeneric = sprintf(
        'Bonjour, la facture %s de %s FCFA reste en attente. Merci de prévoir votre règlement.',
        'FAC-SRV-02',
        number_format(100_000, 0, ',', ' ')
    );

    expect($reminder->message_body)->toBe($expectedGeneric);
});

// ─── Page recouvrement ────────────────────────────────────────────────────────

test('recouvrement: openPreview + ton cordial stocke un message avec le nom du client et de la société', function () {
    ['user' => $user, 'company' => $company] = createReminderOwner('Sow BTP');
    $client = createClientWithPhone($company, 'Dakar Pharma');
    $invoice = createOverdueInvoiceForClient($client, 'FAC-COL-01');

    Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->call('openPreview', $invoice->id)
        ->set('previewTone', 'cordial')
        ->call('sendReminder', $invoice->id)
        ->assertDispatched('toast', type: 'success');

    $stored = Reminder::query()->where('invoice_id', $invoice->id)->latest()->first();

    expect($stored)->not->toBeNull()
        ->and($stored->message_body)->toContain('Bonjour Dakar Pharma,')
        ->and($stored->message_body)->toContain('FAC-COL-01')
        ->and($stored->message_body)->toContain('Cordialement,')
        ->and($stored->message_body)->toContain('Sow BTP')
        ->and($stored->message_body)->not->toContain('Merci de prévoir votre règlement.');
});

test('recouvrement: openPreview + ton ferme stocke un message ferme', function () {
    ['user' => $user, 'company' => $company] = createReminderOwner('Sow BTP');
    $client = createClientWithPhone($company, 'Dakar Pharma');
    $invoice = createOverdueInvoiceForClient($client, 'FAC-COL-02');

    Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->call('openPreview', $invoice->id)
        ->set('previewTone', 'ferme')
        ->call('sendReminder', $invoice->id)
        ->assertDispatched('toast', type: 'success');

    $stored = Reminder::query()->where('invoice_id', $invoice->id)->latest()->first();

    expect($stored)->not->toBeNull()
        ->and($stored->message_body)->toContain('est en retard de paiement')
        ->and($stored->message_body)->toContain('FAC-COL-02')
        ->and($stored->message_body)->toContain('Sow BTP')
        ->and($stored->message_body)->not->toContain('Merci de prévoir votre règlement.');
});

test('recouvrement: openPreview + ton urgent stocke un message urgent', function () {
    ['user' => $user, 'company' => $company] = createReminderOwner('Sow BTP');
    $client = createClientWithPhone($company, 'Dakar Pharma');
    $invoice = createOverdueInvoiceForClient($client, 'FAC-COL-03');

    Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->call('openPreview', $invoice->id)
        ->set('previewTone', 'urgent')
        ->call('sendReminder', $invoice->id)
        ->assertDispatched('toast', type: 'success');

    $stored = Reminder::query()->where('invoice_id', $invoice->id)->latest()->first();

    expect($stored)->not->toBeNull()
        ->and($stored->message_body)->toContain('URGENT')
        ->and($stored->message_body)->toContain('FAC-COL-03')
        ->and($stored->message_body)->toContain('Sow BTP')
        ->and($stored->message_body)->not->toContain('Merci de prévoir votre règlement.');
});

test('recouvrement: envoi direct (sans aperçu) stocke un message composé selon le ton par défaut', function () {
    ['user' => $user, 'company' => $company] = createReminderOwner('Sow BTP');
    $client = createClientWithPhone($company, 'Dakar Pharma');
    $invoice = createOverdueInvoiceForClient($client, 'FAC-COL-04');

    // sendReminder appelé directement sans openPreview
    Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->call('sendReminder', $invoice->id)
        ->assertDispatched('toast', type: 'success');

    $stored = Reminder::query()->where('invoice_id', $invoice->id)->latest()->first();

    expect($stored)->not->toBeNull()
        ->and($stored->message_body)->toContain('FAC-COL-04')
        ->and($stored->message_body)->toContain('Sow BTP')
        ->and($stored->message_body)->not->toContain('Merci de prévoir votre règlement.');
});

test('recouvrement: previewInvoiceId reste inchangé après un envoi direct d une autre facture', function () {
    ['user' => $user, 'company' => $company] = createReminderOwner();
    $client = createClientWithPhone($company);
    $invoiceA = createOverdueInvoiceForClient($client, 'FAC-COL-05');
    $invoiceB = createOverdueInvoiceForClient($client, 'FAC-COL-06');

    // Ouvre l'aperçu sur invoiceA, puis envoie directement invoiceB sans changer l'aperçu
    Livewire::actingAs($user)
        ->test('pages::pme.collection.index')
        ->call('openPreview', $invoiceA->id)
        ->assertSet('previewInvoiceId', $invoiceA->id)
        ->call('sendReminder', $invoiceB->id)
        ->assertSet('previewInvoiceId', $invoiceA->id);
});

// ─── Fiche client ─────────────────────────────────────────────────────────────

test('fiche client: openPreview + ton cordial stocke un message avec le nom du client et de la société', function () {
    ['user' => $user, 'company' => $company] = createReminderOwner('Sow BTP');
    $client = createClientWithPhone($company, 'Dakar Pharma');
    $invoice = createOverdueInvoiceForClient($client, 'FAC-CLI-01');

    Livewire::actingAs($user)
        ->test('pages::pme.clients.show', ['client' => $client])
        ->call('openPreview', $invoice->id)
        ->set('previewTone', 'cordial')
        ->call('sendReminder', $invoice->id)
        ->assertDispatched('toast', type: 'success');

    $stored = Reminder::query()->where('invoice_id', $invoice->id)->latest()->first();

    expect($stored)->not->toBeNull()
        ->and($stored->message_body)->toContain('Bonjour Dakar Pharma,')
        ->and($stored->message_body)->toContain('FAC-CLI-01')
        ->and($stored->message_body)->toContain('Cordialement,')
        ->and($stored->message_body)->toContain('Sow BTP')
        ->and($stored->message_body)->not->toContain('Merci de prévoir votre règlement.');
});

test('fiche client: openPreview + ton urgent stocke un message urgent', function () {
    ['user' => $user, 'company' => $company] = createReminderOwner('Sow BTP');
    $client = createClientWithPhone($company, 'Dakar Pharma');
    $invoice = createOverdueInvoiceForClient($client, 'FAC-CLI-02');

    Livewire::actingAs($user)
        ->test('pages::pme.clients.show', ['client' => $client])
        ->call('openPreview', $invoice->id)
        ->set('previewTone', 'urgent')
        ->call('sendReminder', $invoice->id)
        ->assertDispatched('toast', type: 'success');

    $stored = Reminder::query()->where('invoice_id', $invoice->id)->latest()->first();

    expect($stored)->not->toBeNull()
        ->and($stored->message_body)->toContain('URGENT')
        ->and($stored->message_body)->toContain('FAC-CLI-02')
        ->and($stored->message_body)->toContain('Sow BTP')
        ->and($stored->message_body)->not->toContain('Merci de prévoir votre règlement.');
});

test('fiche client: previewInvoiceId est remis à null après envoi réussi', function () {
    ['user' => $user, 'company' => $company] = createReminderOwner();
    $client = createClientWithPhone($company);
    $invoice = createOverdueInvoiceForClient($client, 'FAC-CLI-03');

    Livewire::actingAs($user)
        ->test('pages::pme.clients.show', ['client' => $client])
        ->call('openPreview', $invoice->id)
        ->assertSet('previewInvoiceId', $invoice->id)
        ->call('sendReminder', $invoice->id)
        ->assertSet('previewInvoiceId', null);
});

test('fiche client: sendReminder est refusé pour une facture appartenant à un autre client', function () {
    ['user' => $user, 'company' => $company] = createReminderOwner();
    $client = createClientWithPhone($company);
    $otherClient = createClientWithPhone($company, 'Autre SARL');
    $foreignInvoice = createOverdueInvoiceForClient($otherClient, 'FAC-INTRUS');

    Livewire::actingAs($user)
        ->test('pages::pme.clients.show', ['client' => $client])
        ->call('sendReminder', $foreignInvoice->id)
        ->assertStatus(404);
});
