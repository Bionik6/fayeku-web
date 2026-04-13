<?php

use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\ReminderChannel;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Models\PME\Reminder;
use App\Models\Shared\User;
use App\Services\PME\ClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makeScoreCompany(): Company
{
    return Company::factory()->create(['type' => 'sme', 'country_code' => 'SN']);
}

function makeScoreClient(Company $company, array $overrides = []): Client
{
    return Client::factory()->create(array_merge(['company_id' => $company->id], $overrides));
}

function makeScoreInvoice(Client $client, array $overrides = []): Invoice
{
    return Invoice::unguarded(fn () => Invoice::create(array_merge([
        'company_id' => $client->company_id,
        'client_id' => $client->id,
        'reference' => 'FYK-FAC-'.fake()->unique()->numerify('######'),
        'status' => InvoiceStatus::Paid->value,
        'issued_at' => now()->subDays(20),
        'due_at' => now()->subDays(10),
        'paid_at' => now()->subDays(8),
        'subtotal' => 200_000,
        'tax_amount' => 0,
        'total' => 200_000,
        'amount_paid' => 200_000,
    ], $overrides)));
}

function clientRow(Client $client): array
{
    return app(ClientService::class)->detail($client)['row'];
}

// ─── Client sans historique ───────────────────────────────────────────────────

test('payment_score est null pour un client sans facture', function () {
    $company = makeScoreCompany();
    $client = makeScoreClient($company);

    expect(clientRow($client)['payment_score'])->toBeNull();
});

test('payment_label est null pour un client sans facture', function () {
    $company = makeScoreCompany();
    $client = makeScoreClient($company);

    expect(clientRow($client)['payment_label'])->toBeNull();
});

test('payment_tone est null pour un client sans facture', function () {
    $company = makeScoreCompany();
    $client = makeScoreClient($company);

    expect(clientRow($client)['payment_tone'])->toBeNull();
});

test("score_explanation indique qu'aucune facture n'est enregistrée pour un nouveau client", function () {
    $company = makeScoreCompany();
    $client = makeScoreClient($company);

    expect(clientRow($client)['score_explanation'])
        ->toBe('Aucune facture enregistrée pour le moment.');
});

test('is_reliable est false pour un client sans facture', function () {
    $company = makeScoreCompany();
    $client = makeScoreClient($company);

    expect(clientRow($client)['is_reliable'])->toBeFalse();
});

test('is_watch est false pour un client sans facture ni impayé', function () {
    $company = makeScoreCompany();
    $client = makeScoreClient($company);

    expect(clientRow($client)['is_watch'])->toBeFalse();
});

// ─── Client avec des factures ─────────────────────────────────────────────────

test('payment_score est un entier entre 5 et 100 pour un client avec des factures', function () {
    $company = makeScoreCompany();
    $client = makeScoreClient($company);
    makeScoreInvoice($client);

    $score = clientRow($client)['payment_score'];

    expect($score >= 5 && $score <= 100)->toBeTrue();
});

test('payment_label est non null pour un client avec des factures', function () {
    $company = makeScoreCompany();
    $client = makeScoreClient($company);
    makeScoreInvoice($client);

    expect(clientRow($client)['payment_label'])->not->toBeNull();
});

test('payment_tone est non null pour un client avec des factures', function () {
    $company = makeScoreCompany();
    $client = makeScoreClient($company);
    makeScoreInvoice($client);

    expect(clientRow($client)['payment_tone'])->not->toBeNull();
});

test('un bon payeur sans retard obtient un score élevé et le label Fiable', function () {
    $company = makeScoreCompany();
    $client = makeScoreClient($company);
    // Paid on time, no reminders
    makeScoreInvoice($client, [
        'status' => InvoiceStatus::Paid->value,
        'due_at' => now()->subDays(5),
        'paid_at' => now()->subDays(6),
        'amount_paid' => 200_000,
    ]);

    $row = clientRow($client);

    expect($row['payment_score'] >= 85)->toBeTrue();
    expect($row['payment_label'])->toBe('Fiable');
    expect($row['payment_tone'])->toBe('emerald');
    expect($row['is_reliable'])->toBeTrue();
});

test('is_watch est true pour un client avec des impayés', function () {
    $company = makeScoreCompany();
    $client = makeScoreClient($company);
    makeScoreInvoice($client, [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(15),
        'paid_at' => null,
        'amount_paid' => 0,
    ]);

    expect(clientRow($client)['is_watch'])->toBeTrue();
});

test('is_watch est true pour un client avec un score inférieur à 65', function () {
    $company = makeScoreCompany();
    $client = makeScoreClient($company);
    // Multiple overdue invoices to drive score down
    for ($i = 0; $i < 4; $i++) {
        $invoice = makeScoreInvoice($client, [
            'status' => InvoiceStatus::Overdue->value,
            'due_at' => now()->subDays(30 + $i * 10),
            'paid_at' => null,
            'amount_paid' => 0,
        ]);
        Reminder::query()->create([
            'invoice_id' => $invoice->id,
            'channel' => ReminderChannel::WhatsApp,
            'mode' => 'manual',
            'sent_at' => now()->subDays(2),
            'message_body' => 'Relance test',
            'recipient_phone' => '+221771234567',
        ]);
    }

    expect(clientRow($client)['is_watch'])->toBeTrue();
});

// ─── Affichage dans la liste clients ─────────────────────────────────────────

test('la liste clients expose payment_score null pour un nouveau client', function () {
    $company = makeScoreCompany();
    $client = makeScoreClient($company);

    $rows = app(ClientService::class)->portfolioRows($company);
    $row = collect($rows)->firstWhere('id', $client->id);

    expect($row['payment_score'])->toBeNull()
        ->and($row['payment_label'])->toBeNull()
        ->and($row['is_watch'])->toBeFalse();
});

// ─── Affichage sur la fiche client ────────────────────────────────────────────

test('la fiche client n\'affiche pas de badge score pour un nouveau client', function () {
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = makeScoreCompany();
    $company->users()->attach($user->id, ['role' => 'owner']);
    $client = makeScoreClient($company);

    Livewire::actingAs($user)
        ->test('pages::pme.clients.show', ['client' => $client])
        ->assertSee('Aucune facture enregistrée pour le moment.')
        ->assertDontSee('À surveiller')
        ->assertDontSee('Risqué');
});
