<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Modules\Auth\Models\Company;
use Modules\PME\Clients\Models\Client;
use Modules\PME\Collection\Enums\ReminderChannel;
use Modules\PME\Collection\Enums\ReminderStatus;
use Modules\PME\Collection\Models\Reminder;
use Modules\PME\Invoicing\Enums\InvoiceStatus;
use Modules\PME\Invoicing\Models\Invoice;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makeCompanyAndClient(): array
{
    $company = Company::factory()->create(['type' => 'sme', 'name' => 'Sow BTP']);
    $client = Client::factory()->create([
        'company_id' => $company->id,
        'name' => 'Dakar Pharma',
        'phone' => '+221771112233',
    ]);

    return compact('company', 'client');
}

function makeInvoiceForFeed(Client $client, array $overrides = []): Invoice
{
    return Invoice::unguarded(fn () => Invoice::create(array_merge([
        'company_id' => $client->company_id,
        'client_id' => $client->id,
        'reference' => 'FAC-FEED-'.fake()->unique()->numerify('###'),
        'status' => InvoiceStatus::Overdue->value,
        'issued_at' => now()->subDays(20),
        'due_at' => now()->subDays(10),
        'subtotal' => 300_000,
        'tax_amount' => 0,
        'total' => 300_000,
        'amount_paid' => 0,
    ], $overrides)));
}

function makeReminderForFeed(Invoice $invoice, array $overrides = []): Reminder
{
    return Reminder::query()->create(array_merge([
        'invoice_id' => $invoice->id,
        'channel' => ReminderChannel::WhatsApp,
        'status' => ReminderStatus::Sent,
        'sent_at' => now()->subDays(2),
        'message_body' => 'Bonjour, votre facture est en attente.',
        'recipient_phone' => '+221771112233',
    ], $overrides));
}

function renderFeed(string $props, array $vars = []): string
{
    return Blade::render("<x-collection.reminder-feed {$props} />", $vars);
}

// ─── Mode liste (:reminders) ─────────────────────────────────────────────────

it('affiche le message vide par défaut quand la liste est vide', function () {
    $html = renderFeed(':reminders="$reminders"', ['reminders' => collect()]);

    expect($html)
        ->toContain('Aucune relance envoyée pour le moment.')
        ->toContain('data-flux-icon'); // icône SVG Flux présente
});

it('affiche un message vide personnalisé', function () {
    $html = renderFeed(
        ':reminders="$reminders" empty-message="Pas encore de relance ici."',
        ['reminders' => collect()],
    );

    expect($html)->toContain('Pas encore de relance ici.');
});

it('affiche une relance WhatsApp avec le bon libellé et les classes couleur émeraude', function () {
    ['client' => $client] = makeCompanyAndClient();
    $invoice = makeInvoiceForFeed($client);
    $reminder = makeReminderForFeed($invoice, ['channel' => ReminderChannel::WhatsApp]);
    $invoice->load('reminders');

    $html = renderFeed(':reminders="$invoice->reminders"', compact('invoice'));

    expect($html)
        ->toContain('Relance WhatsApp')
        ->toContain('bg-emerald-50')
        ->toContain('text-emerald-600')
        ->not->toContain('Aucune relance');
});

it('affiche une relance Email avec le bon libellé et les classes bleu', function () {
    ['client' => $client] = makeCompanyAndClient();
    $invoice = makeInvoiceForFeed($client);
    makeReminderForFeed($invoice, ['channel' => ReminderChannel::Email]);
    $invoice->load('reminders');

    $html = renderFeed(':reminders="$invoice->reminders"', compact('invoice'));

    expect($html)
        ->toContain('Relance Email')
        ->toContain('bg-blue-50')
        ->toContain('text-blue-600');
});

it('affiche une relance SMS avec le bon libellé et les classes violet', function () {
    ['client' => $client] = makeCompanyAndClient();
    $invoice = makeInvoiceForFeed($client);
    makeReminderForFeed($invoice, ['channel' => ReminderChannel::Sms]);
    $invoice->load('reminders');

    $html = renderFeed(':reminders="$invoice->reminders"', compact('invoice'));

    expect($html)
        ->toContain('Relance SMS')
        ->toContain('bg-violet-50')
        ->toContain('text-violet-600');
});

it('affiche le corps du message quand il est renseigné', function () {
    ['client' => $client] = makeCompanyAndClient();
    $invoice = makeInvoiceForFeed($client);
    makeReminderForFeed($invoice, ['message_body' => 'Bonjour, merci de régulariser FAC-001.']);
    $invoice->load('reminders');

    $html = renderFeed(':reminders="$invoice->reminders"', compact('invoice'));

    expect($html)->toContain('Bonjour, merci de régulariser FAC-001.');
});

it('n\'affiche pas de corps si message_body est null', function () {
    ['client' => $client] = makeCompanyAndClient();
    $invoice = makeInvoiceForFeed($client);
    makeReminderForFeed($invoice, ['message_body' => null]);
    $invoice->load('reminders');

    $html = renderFeed(':reminders="$invoice->reminders"', compact('invoice'));

    // No paragraph containing a message body text – only structural elements
    expect($html)
        ->toContain('Relance WhatsApp')
        ->not->toContain('leading-relaxed');
});

// ─── Statuts ─────────────────────────────────────────────────────────────────

it('applique les classes bleues pour le statut sent', function () {
    ['client' => $client] = makeCompanyAndClient();
    $invoice = makeInvoiceForFeed($client);
    makeReminderForFeed($invoice, ['status' => ReminderStatus::Sent]);
    $invoice->load('reminders');

    $html = renderFeed(':reminders="$invoice->reminders"', compact('invoice'));

    expect($html)->toContain('bg-blue-50')->toContain('text-blue-700')->toContain('Envoyée');
});

it('applique les classes accent pour le statut delivered', function () {
    ['client' => $client] = makeCompanyAndClient();
    $invoice = makeInvoiceForFeed($client);
    makeReminderForFeed($invoice, ['status' => ReminderStatus::Delivered]);
    $invoice->load('reminders');

    $html = renderFeed(':reminders="$invoice->reminders"', compact('invoice'));

    expect($html)->toContain('bg-accent/10')->toContain('text-accent')->toContain('Livrée');
});

it('applique les classes rose pour le statut failed', function () {
    ['client' => $client] = makeCompanyAndClient();
    $invoice = makeInvoiceForFeed($client);
    makeReminderForFeed($invoice, ['status' => ReminderStatus::Failed]);
    $invoice->load('reminders');

    $html = renderFeed(':reminders="$invoice->reminders"', compact('invoice'));

    expect($html)->toContain('bg-rose-50')->toContain('text-rose-700')->toContain('Échouée');
});

it('applique les classes ambre pour le statut pending', function () {
    ['client' => $client] = makeCompanyAndClient();
    $invoice = makeInvoiceForFeed($client);
    makeReminderForFeed($invoice, ['status' => ReminderStatus::Pending]);
    $invoice->load('reminders');

    $html = renderFeed(':reminders="$invoice->reminders"', compact('invoice'));

    expect($html)->toContain('bg-amber-50')->toContain('text-amber-700')->toContain('En attente');
});

// ─── Référence facture ────────────────────────────────────────────────────────

it('affiche la référence facture quand show-invoice-ref est true', function () {
    ['client' => $client] = makeCompanyAndClient();
    $invoice = makeInvoiceForFeed($client, ['reference' => 'FAC-SHOW-REF']);
    $reminder = makeReminderForFeed($invoice);
    $reminder->setRelation('invoice', $invoice);

    $html = renderFeed(
        ':reminders="$reminders" :show-invoice-ref="true"',
        ['reminders' => collect([$reminder])],
    );

    expect($html)->toContain('FAC-SHOW-REF');
});

it('masque la référence facture quand show-invoice-ref est false (par défaut)', function () {
    ['client' => $client] = makeCompanyAndClient();
    $invoice = makeInvoiceForFeed($client, ['reference' => 'FAC-HIDDEN-REF']);
    $reminder = makeReminderForFeed($invoice);

    $html = renderFeed(
        ':reminders="$reminders"',
        ['reminders' => collect([$reminder])],
    );

    expect($html)->not->toContain('FAC-HIDDEN-REF');
});

// ─── Ligne verticale ─────────────────────────────────────────────────────────

it('ajoute la ligne verticale entre les items et la retire sur le dernier', function () {
    ['client' => $client] = makeCompanyAndClient();
    $invoice = makeInvoiceForFeed($client);
    makeReminderForFeed($invoice, ['sent_at' => now()->subDays(3)]);
    makeReminderForFeed($invoice, ['sent_at' => now()->subDays(1)]);
    $invoice->load('reminders');

    $html = renderFeed(':reminders="$invoice->reminders"', compact('invoice'));

    // "pb-6" is added on non-last items (contains the connector span)
    // It must appear at least once (for the first item)
    expect(substr_count($html, 'pb-6'))->toBeGreaterThanOrEqual(1);
    // The very last <li> should NOT have the connector span inside it
    preg_match_all('/<li[^>]*>(.*?)<\/li>/s', $html, $matches);
    $lastLi = end($matches[1]);
    expect($lastLi)->not->toContain('absolute top-4 left-4');
});

// ─── Mode timeline (:invoice) ─────────────────────────────────────────────────

it('affiche le marqueur d\'échéance en mode invoice', function () {
    ['client' => $client] = makeCompanyAndClient();
    $invoice = makeInvoiceForFeed($client, [
        'due_at' => now()->subDays(10),
        'total' => 500_000,
        'amount_paid' => 100_000,
    ]);
    $invoice->load('reminders');

    $html = renderFeed(':invoice="$invoice"', compact('invoice'));

    expect($html)
        ->toContain('Date d&#039;échéance')
        ->toContain('400')   // 500k - 100k = 400k FCFA somewhere in the output
        ->toContain('Montant');
});

it('affiche le marqueur de paiement quand la facture est payée', function () {
    ['client' => $client] = makeCompanyAndClient();
    $invoice = makeInvoiceForFeed($client, [
        'status' => InvoiceStatus::Paid->value,
        'paid_at' => now()->subDays(3),
        'amount_paid' => 300_000,
    ]);
    $invoice->load('reminders');

    $html = renderFeed(':invoice="$invoice"', compact('invoice'));

    expect($html)->toContain('Paiement reçu');
});

it('n\'affiche pas le marqueur de paiement quand la facture est impayée', function () {
    ['client' => $client] = makeCompanyAndClient();
    $invoice = makeInvoiceForFeed($client, ['paid_at' => null, 'amount_paid' => 0]);
    $invoice->load('reminders');

    $html = renderFeed(':invoice="$invoice"', compact('invoice'));

    expect($html)->not->toContain('Paiement reçu');
});

it('affiche l\'état vide (et non le marqueur paiement) quand aucune relance et pas de paiement', function () {
    ['client' => $client] = makeCompanyAndClient();
    $invoice = makeInvoiceForFeed($client, ['paid_at' => null, 'amount_paid' => 0]);
    $invoice->load('reminders'); // no reminders

    $html = renderFeed(':invoice="$invoice"', compact('invoice'));

    expect($html)
        ->toContain('Date d&#039;échéance')
        ->toContain('Aucune relance envoyée pour le moment.')
        ->not->toContain('Paiement reçu');
});

it('ordonne les items : échéance → relances → paiement', function () {
    ['client' => $client] = makeCompanyAndClient();
    $invoice = makeInvoiceForFeed($client, [
        'status' => InvoiceStatus::Paid->value,
        'paid_at' => now()->subDays(1),
        'amount_paid' => 300_000,
    ]);
    makeReminderForFeed($invoice, [
        'sent_at' => now()->subDays(5),
        'message_body' => 'Rappel FAC-001',
    ]);
    $invoice->load('reminders');

    $html = renderFeed(':invoice="$invoice"', compact('invoice'));

    $posDueDate = strpos($html, 'Date d&#039;échéance');
    $posReminder = strpos($html, 'Rappel FAC-001');
    $posPayment = strpos($html, 'Paiement reçu');

    expect($posDueDate)->toBeLessThan($posReminder)
        ->and($posReminder)->toBeLessThan($posPayment);
});

it('affiche plusieurs relances en mode liste', function () {
    ['client' => $client] = makeCompanyAndClient();
    $invoice = makeInvoiceForFeed($client);
    makeReminderForFeed($invoice, ['message_body' => 'Premier rappel', 'sent_at' => now()->subDays(5)]);
    makeReminderForFeed($invoice, ['message_body' => 'Second rappel', 'sent_at' => now()->subDays(2)]);
    $invoice->load('reminders');

    $html = renderFeed(':reminders="$invoice->reminders"', compact('invoice'));

    expect($html)
        ->toContain('Premier rappel')
        ->toContain('Second rappel');
});
