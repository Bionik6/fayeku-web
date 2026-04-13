<?php

use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\ReminderChannel;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Models\PME\Reminder;
use App\Models\Shared\User;
use App\Services\PME\ForecastService;
use App\Services\PME\InvoiceService;
use App\Services\PME\TreasuryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createSmeUserForTreasury(array $companyAttributes = []): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(array_merge(['type' => 'sme'], $companyAttributes));
    $company->users()->attach($user->id, ['role' => 'owner']);

    return compact('user', 'company');
}

function createTreasuryClient(Company $company, array $attributes = []): Client
{
    return Client::factory()->create(array_merge([
        'company_id' => $company->id,
    ], $attributes));
}

function createTreasuryInvoice(Company $company, array $attributes = []): Invoice
{
    $client = $attributes['client'] ?? createTreasuryClient($company);
    unset($attributes['client']);

    return Invoice::factory()
        ->forCompany($company)
        ->withClient($client)
        ->create(array_merge([
            'status' => InvoiceStatus::Sent,
            'issued_at' => now()->subDays(5),
            'due_at' => now()->addDays(10),
            'total' => 100_000,
            'amount_paid' => 0,
        ], $attributes));
}

test('un visiteur non authentifie est redirige vers la connexion', function () {
    $this->get(route('pme.treasury.index'))
        ->assertRedirect(route('login'));
});

test('un utilisateur SME peut acceder a la page de tresorerie', function () {
    ['user' => $user] = createSmeUserForTreasury();

    $this->actingAs($user)
        ->get(route('pme.treasury.index'))
        ->assertOk();
});

test('la page affiche les sections principales et le copy encaissements', function () {
    ['user' => $user] = createSmeUserForTreasury();

    Livewire::actingAs($user)
        ->test('pages::pme.treasury.index')
        ->assertSee('Trésorerie')
        ->assertSee('Vision 90 jours')
        ->assertSee('Encaissé à date')
        ->assertSee('Entrées prévues')
        ->assertSee('Montant à risque')
        ->assertSee('Prévision des encaissements uniquement');
});

test('InvoiceService openReceivables retourne uniquement les factures ouvertes', function () {
    ['company' => $company] = createSmeUserForTreasury();

    createTreasuryInvoice($company, ['status' => InvoiceStatus::Sent]);
    createTreasuryInvoice($company, ['status' => InvoiceStatus::PartiallyPaid, 'amount_paid' => 25_000]);
    createTreasuryInvoice($company, ['status' => InvoiceStatus::Overdue, 'due_at' => now()->subDays(12)]);
    createTreasuryInvoice($company, ['status' => InvoiceStatus::Paid, 'amount_paid' => 100_000, 'paid_at' => now()]);
    createTreasuryInvoice($company, ['status' => InvoiceStatus::Draft]);

    $receivables = app(InvoiceService::class)->openReceivables($company);

    expect($receivables)->toHaveCount(3)
        ->and($receivables->pluck('status')->map->value->sort()->values()->all())
        ->toBe([
            InvoiceStatus::Overdue->value,
            InvoiceStatus::PartiallyPaid->value,
            InvoiceStatus::Sent->value,
        ]);
});

test('ForecastService calcule un niveau fort probable et l entree estimee correspondante', function () {
    ['company' => $company] = createSmeUserForTreasury();
    $client = createTreasuryClient($company);
    $invoice = createTreasuryInvoice($company, [
        'client' => $client,
        'total' => 100_000,
        'due_at' => now()->addDays(15),
    ]);

    $service = app(ForecastService::class);
    $confidence = $service->confidenceLevel($invoice, [
        $client->id => [
            'payment_score' => 80,
            'average_late_days' => 0,
        ],
    ]);
    $rows = $service->rows(collect([$invoice]), [
        $client->id => [
            'payment_score' => 80,
            'average_late_days' => 0,
        ],
    ]);

    expect($confidence['label'])->toBe('Fort probable')
        ->and($confidence['score'])->toBe(90)
        ->and($rows[0]['estimated_amount'])->toBe(90_000);
});

test('ForecastService calcule un niveau risque pour une facture tres en retard', function () {
    ['company' => $company] = createSmeUserForTreasury();
    $client = createTreasuryClient($company);
    $invoice = createTreasuryInvoice($company, [
        'client' => $client,
        'status' => InvoiceStatus::Overdue,
        'due_at' => now()->subDays(80),
    ]);

    $confidence = app(ForecastService::class)->confidenceLevel($invoice, [
        $client->id => [
            'payment_score' => 95,
            'average_late_days' => 0,
        ],
    ]);

    expect($confidence['label'])->toBe('Risqué')
        ->and($confidence['score'])->toBe(35);
});

test('TreasuryService construit les KPI, cartes et recommandations', function () {
    ['company' => $company] = createSmeUserForTreasury(['plan' => 'essentiel']);
    $goodClient = createTreasuryClient($company, ['name' => 'Sonatel']);
    $riskClient = createTreasuryClient($company, ['name' => 'Immeuble Atlan']);

    createTreasuryInvoice($company, [
        'client' => $goodClient,
        'status' => InvoiceStatus::Paid,
        'issued_at' => now()->subDays(20),
        'paid_at' => now()->subDays(10),
        'total' => 120_000,
        'amount_paid' => 120_000,
    ]);

    createTreasuryInvoice($company, [
        'client' => $goodClient,
        'status' => InvoiceStatus::Paid,
        'issued_at' => now()->subDays(10),
        'paid_at' => now()->subDays(4),
        'total' => 80_000,
        'amount_paid' => 80_000,
    ]);

    createTreasuryInvoice($company, [
        'client' => $riskClient,
        'status' => InvoiceStatus::Overdue,
        'due_at' => now()->subDays(35),
        'total' => 300_000,
        'amount_paid' => 0,
    ]);

    $dashboard = app(TreasuryService::class)->dashboard($company, '90d');

    expect($dashboard['kpis']['collected_amount'])->toBe(200_000)
        ->and($dashboard['kpis']['average_collection_days'])->toBe(8)
        ->and($dashboard['forecast_cards'])->toHaveCount(3)
        ->and($dashboard['recommendations'])->not->toBeEmpty();
});

test('le filtre de periode met a jour le sous titre et les lignes visibles', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForTreasury();

    createTreasuryInvoice($company, [
        'reference' => 'FAC-NEAR',
        'due_at' => now()->addDays(10),
    ]);

    createTreasuryInvoice($company, [
        'reference' => 'FAC-FAR',
        'due_at' => now()->addDays(55),
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.treasury.index')
        ->assertSee('Vision 90 jours')
        ->assertSee('FAC-NEAR')
        ->assertSee('FAC-FAR')
        ->call('setPeriod', '30d')
        ->assertSee('Vision 30 jours')
        ->assertSee('FAC-NEAR')
        ->assertDontSee('FAC-FAR');
});

test('ouvrir une facture depuis la page selectionne le detail local', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForTreasury();
    $invoice = createTreasuryInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.treasury.index')
        ->assertSet('selectedInvoiceId', null)
        ->call('viewInvoice', $invoice->id)
        ->assertSet('selectedInvoiceId', $invoice->id)
        ->assertSee('Facture');
});

test('relancer depuis la page cree une relance dans le quota disponible', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForTreasury(['plan' => 'essentiel']);
    $company->update([
        'reminder_settings' => [
            ...Company::defaultReminderSettings(),
            'default_channel' => 'whatsapp',
        ],
    ]);

    $invoice = createTreasuryInvoice($company, [
        'client' => createTreasuryClient($company, ['phone' => '+221771111111']),
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.treasury.index')
        ->call('sendReminder', $invoice->id)
        ->assertDispatched('toast', type: 'success', title: 'Relance envoyée avec succès.');

    expect(Reminder::query()->where('invoice_id', $invoice->id)->count())->toBe(1);
});

test('relancer depuis la page bloque si le contact requis manque', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForTreasury();
    $company->update([
        'reminder_settings' => [
            ...Company::defaultReminderSettings(),
            'default_channel' => 'email',
        ],
    ]);

    $invoice = createTreasuryInvoice($company, [
        'client' => createTreasuryClient($company, ['email' => null]),
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.treasury.index')
        ->call('sendReminder', $invoice->id)
        ->assertDispatched('toast', type: 'warning', title: 'Aucune adresse email disponible pour ce client.');

    expect(Reminder::query()->where('invoice_id', $invoice->id)->count())->toBe(0);
});

test('relancer depuis la page respecte le quota basique mensuel', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForTreasury(['plan' => 'basique']);
    $company->update([
        'reminder_settings' => [
            ...Company::defaultReminderSettings(),
            'default_channel' => 'whatsapp',
        ],
    ]);

    $invoice = createTreasuryInvoice($company, [
        'client' => createTreasuryClient($company, ['phone' => '+221772222222']),
    ]);

    for ($i = 0; $i < 20; $i++) {
        Reminder::query()->create([
            'invoice_id' => $invoice->id,
            'channel' => ReminderChannel::WhatsApp,
            'mode' => 'manual',
            'sent_at' => now()->subDays(1),
            'message_body' => 'Relance envoyée',
            'recipient_phone' => '+221772222222',
            'created_at' => now()->subDays(1),
            'updated_at' => now()->subDays(1),
        ]);
    }

    Livewire::actingAs($user)
        ->test('pages::pme.treasury.index')
        ->call('sendReminder', $invoice->id)
        ->assertDispatched('toast', type: 'warning', title: 'Quota de relances atteint pour ce mois.');

    expect(Reminder::query()->where('invoice_id', $invoice->id)->count())->toBe(20);
});

test('la route export csv retourne uniquement les lignes visibles de la societe', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForTreasury();
    ['company' => $otherCompany] = createSmeUserForTreasury();

    createTreasuryInvoice($company, [
        'reference' => 'FAC-LOCAL',
        'due_at' => now()->addDays(8),
    ]);

    createTreasuryInvoice($otherCompany, [
        'reference' => 'FAC-OTHER',
        'due_at' => now()->addDays(8),
    ]);

    $response = $this->actingAs($user)
        ->get(route('pme.treasury.export', ['period' => '90d']));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');

    $csv = $response->streamedContent();

    expect($csv)->toContain('document,client,montant_ttc')
        ->toContain('FAC-LOCAL')
        ->not->toContain('FAC-OTHER');
});

test('un utilisateur ne peut pas relancer une facture d une autre societe depuis la page', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForTreasury();
    ['company' => $otherCompany] = createSmeUserForTreasury();

    createTreasuryInvoice($company, ['reference' => 'FAC-ME']);
    $otherInvoice = createTreasuryInvoice($otherCompany, ['reference' => 'FAC-THEM']);

    expect(fn () => Livewire::actingAs($user)
        ->test('pages::pme.treasury.index')
        ->call('sendReminder', $otherInvoice->id))
        ->toThrow(ModelNotFoundException::class);
});
