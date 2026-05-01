<?php

use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\ReminderChannel;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Services\PME\ReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Garde-fou : un client sans téléphone ni email ne doit jamais recevoir de
 * relance. Couvre les 4 niveaux : Client model, Invoice model, ReminderService.
 */
function makeClientForGuard(array $attrs = []): Client
{
    $company = Company::factory()->create(['type' => 'sme']);

    return Client::factory()->create(array_merge([
        'company_id' => $company->id,
        'name' => 'Test Client',
    ], $attrs));
}

function makeInvoiceForGuard(Client $client, InvoiceStatus $status = InvoiceStatus::Sent): Invoice
{
    return Invoice::unguarded(fn () => Invoice::create([
        'company_id' => $client->company_id,
        'client_id' => $client->id,
        'reference' => 'FAC-GUARD-'.fake()->unique()->numerify('####'),
        'status' => $status->value,
        'issued_at' => now(),
        'due_at' => now()->addDays(30),
        'subtotal' => 100_000,
        'tax_amount' => 18_000,
        'total' => 118_000,
        'amount_paid' => 0,
        'currency' => 'XOF',
    ]));
}

// ─── Client::hasContact() ────────────────────────────────────────────────────

test('Client::hasContact() retourne true si téléphone OU email est rempli', function () {
    $withPhone = makeClientForGuard(['phone' => '+221771234567', 'email' => null]);
    $withEmail = makeClientForGuard(['phone' => null, 'email' => 'a@b.sn']);
    $withBoth = makeClientForGuard(['phone' => '+221771234567', 'email' => 'a@b.sn']);

    expect($withPhone->hasContact())->toBeTrue()
        ->and($withEmail->hasContact())->toBeTrue()
        ->and($withBoth->hasContact())->toBeTrue();
});

test('Client::hasContact() retourne false si téléphone ET email sont vides', function () {
    $client = makeClientForGuard(['phone' => null, 'email' => null]);

    expect($client->hasContact())->toBeFalse();
});

// ─── Client::canReceiveReminderOn() ──────────────────────────────────────────

test('Client::canReceiveReminderOn(WhatsApp) exige un téléphone', function () {
    expect(makeClientForGuard(['phone' => '+221771234567'])->canReceiveReminderOn(ReminderChannel::WhatsApp))->toBeTrue()
        ->and(makeClientForGuard(['phone' => null])->canReceiveReminderOn(ReminderChannel::WhatsApp))->toBeFalse();
});

test('Client::canReceiveReminderOn(Sms) exige un téléphone', function () {
    expect(makeClientForGuard(['phone' => '+221771234567'])->canReceiveReminderOn(ReminderChannel::Sms))->toBeTrue()
        ->and(makeClientForGuard(['phone' => null])->canReceiveReminderOn(ReminderChannel::Sms))->toBeFalse();
});

test('Client::canReceiveReminderOn(Email) exige un email', function () {
    expect(makeClientForGuard(['email' => 'a@b.sn'])->canReceiveReminderOn(ReminderChannel::Email))->toBeTrue()
        ->and(makeClientForGuard(['email' => null])->canReceiveReminderOn(ReminderChannel::Email))->toBeFalse();
});

test('Email avec téléphone seul retourne false', function () {
    $client = makeClientForGuard(['phone' => '+221771234567', 'email' => null]);

    expect($client->canReceiveReminderOn(ReminderChannel::Email))->toBeFalse();
});

test('WhatsApp avec email seul retourne false', function () {
    $client = makeClientForGuard(['phone' => null, 'email' => 'a@b.sn']);

    expect($client->canReceiveReminderOn(ReminderChannel::WhatsApp))->toBeFalse();
});

// ─── Invoice::canReceiveReminder() ───────────────────────────────────────────

test('Invoice::canReceiveReminder() retourne false si le client n\'a aucun contact', function () {
    $client = makeClientForGuard(['phone' => null, 'email' => null]);
    $invoice = makeInvoiceForGuard($client, InvoiceStatus::Sent);

    expect($invoice->canReceiveReminder())->toBeFalse();
});

test('Invoice::canReceiveReminder() retourne true si le client a un téléphone', function () {
    $client = makeClientForGuard(['phone' => '+221771234567', 'email' => null]);
    $invoice = makeInvoiceForGuard($client, InvoiceStatus::Sent);

    expect($invoice->canReceiveReminder())->toBeTrue();
});

test('Invoice::canReceiveReminder() retourne true si le client a un email', function () {
    $client = makeClientForGuard(['phone' => null, 'email' => 'a@b.sn']);
    $invoice = makeInvoiceForGuard($client, InvoiceStatus::Sent);

    expect($invoice->canReceiveReminder())->toBeTrue();
});

test('Invoice::canReceiveReminder() reste false sur Paid/Cancelled/Draft même avec contact', function () {
    $client = makeClientForGuard(['phone' => '+221771234567', 'email' => 'a@b.sn']);

    foreach ([InvoiceStatus::Paid, InvoiceStatus::Cancelled, InvoiceStatus::Draft] as $status) {
        $invoice = makeInvoiceForGuard($client, $status);
        expect($invoice->canReceiveReminder())->toBeFalse(
            "Status {$status->value} doit refuser la relance même avec contact"
        );
    }
});

// ─── ReminderService::send() — garde-fou par canal ───────────────────────────

test('ReminderService::send() throw quand WhatsApp est demandé sans téléphone client', function () {
    $client = makeClientForGuard(['phone' => null, 'email' => 'a@b.sn']);
    $invoice = makeInvoiceForGuard($client);

    app(ReminderService::class)->send(
        $invoice,
        $invoice->company,
        ReminderChannel::WhatsApp,
    );
})->throws(RuntimeException::class, 'whatsapp');

test('ReminderService::send() throw quand SMS est demandé sans téléphone client', function () {
    $client = makeClientForGuard(['phone' => null, 'email' => 'a@b.sn']);
    $invoice = makeInvoiceForGuard($client);

    app(ReminderService::class)->send(
        $invoice,
        $invoice->company,
        ReminderChannel::Sms,
    );
})->throws(RuntimeException::class, 'sms');

test('ReminderService::send() throw quand Email est demandé sans email client', function () {
    $client = makeClientForGuard(['phone' => '+221771234567', 'email' => null]);
    $invoice = makeInvoiceForGuard($client);

    app(ReminderService::class)->send(
        $invoice,
        $invoice->company,
        ReminderChannel::Email,
    );
})->throws(RuntimeException::class, 'email');

test('ReminderService::send() throw quand le client n\'a aucun contact', function () {
    $client = makeClientForGuard(['phone' => null, 'email' => null]);
    $invoice = makeInvoiceForGuard($client);

    app(ReminderService::class)->send(
        $invoice,
        $invoice->company,
        ReminderChannel::WhatsApp,
    );
})->throws(RuntimeException::class);
