<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Auth\Models\Company;
use Modules\PME\Clients\Models\Client;
use Modules\PME\Collection\Enums\ReminderChannel;
use Modules\PME\Collection\Enums\ReminderStatus;
use Modules\PME\Collection\Models\Reminder;
use Modules\PME\Invoicing\Enums\InvoiceStatus;
use Modules\PME\Invoicing\Enums\QuoteStatus;
use Modules\PME\Invoicing\Models\Invoice;
use Modules\PME\Invoicing\Models\Quote;
use Modules\Shared\Models\User;

uses(RefreshDatabase::class);

/**
 * @return array{user: User, company: Company}
 */
function createSmePortfolioOwner(?string $companyName = null): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create([
        'type' => 'sme',
        'name' => $companyName ?? 'Sow BTP',
        'country_code' => 'SN',
    ]);
    $company->users()->attach($user->id, ['role' => 'owner']);

    return compact('user', 'company');
}

function makePortfolioClient(Company $company, array $overrides = []): Client
{
    return Client::factory()->create(array_merge([
        'company_id' => $company->id,
        'name' => fake()->company(),
    ], $overrides));
}

function makePortfolioInvoice(Client $client, array $overrides = []): Invoice
{
    return Invoice::unguarded(fn () => Invoice::create(array_merge([
        'company_id' => $client->company_id,
        'client_id' => $client->id,
        'reference' => 'FAC-'.fake()->unique()->numerify('###'),
        'status' => InvoiceStatus::Paid->value,
        'issued_at' => now()->subDays(12),
        'due_at' => now()->subDays(2),
        'paid_at' => now()->subDays(4),
        'subtotal' => 100_000,
        'tax_amount' => 0,
        'total' => 100_000,
        'amount_paid' => 100_000,
    ], $overrides)));
}

function makePortfolioQuote(Client $client, array $overrides = []): Quote
{
    return Quote::unguarded(fn () => Quote::create(array_merge([
        'company_id' => $client->company_id,
        'client_id' => $client->id,
        'reference' => 'DEV-'.fake()->unique()->numerify('###'),
        'status' => QuoteStatus::Sent->value,
        'issued_at' => now()->subDays(8),
        'valid_until' => now()->addDays(15),
        'subtotal' => 90_000,
        'tax_amount' => 0,
        'total' => 90_000,
    ], $overrides)));
}

function makePortfolioReminder(Invoice $invoice, array $overrides = []): Reminder
{
    return Reminder::query()->create(array_merge([
        'invoice_id' => $invoice->id,
        'channel' => ReminderChannel::WhatsApp->value,
        'status' => ReminderStatus::Sent->value,
        'sent_at' => now()->subDays(3),
        'message_body' => 'Rappel de paiement',
        'recipient_phone' => '+221771112233',
    ], $overrides));
}

test('un visiteur est redirigé vers la connexion sur l index clients PME', function () {
    $this->get(route('pme.clients.index'))
        ->assertRedirect(route('login'));
});

test('un utilisateur SME peut acceder a la page clients PME', function () {
    ['user' => $user] = createSmePortfolioOwner();

    $this->actingAs($user)
        ->get(route('pme.clients.index'))
        ->assertOk();
});

test('la page affiche l etat vide quand aucun client n existe', function () {
    ['user' => $user] = createSmePortfolioOwner();

    Livewire::actingAs($user)
        ->test('pages::pme.clients.index')
        ->assertSee('Votre portefeuille client démarre ici')
        ->assertSee('Ajouter un client');
});

test('la page calcule les kpis, les segments et l insight de risque', function () {
    ['user' => $user, 'company' => $company] = createSmePortfolioOwner();

    $best = makePortfolioClient($company, ['name' => 'Sonatel', 'sector' => 'Télécom']);
    makePortfolioInvoice($best, [
        'total' => 500_000,
        'amount_paid' => 500_000,
        'issued_at' => now()->subDays(15),
        'due_at' => now()->subDays(3),
        'paid_at' => now()->subDays(5),
    ]);
    makePortfolioInvoice($best, [
        'total' => 220_000,
        'amount_paid' => 220_000,
        'issued_at' => now()->subMonths(14),
        'due_at' => now()->subMonths(14)->addDays(20),
        'paid_at' => now()->subMonths(14)->addDays(10),
    ]);

    $risk = makePortfolioClient($company, ['name' => 'Dakar Pharma', 'sector' => 'Santé']);
    $riskInvoice = makePortfolioInvoice($risk, [
        'status' => InvoiceStatus::Overdue->value,
        'total' => 800_000,
        'amount_paid' => 0,
        'issued_at' => now()->subDays(50),
        'due_at' => now()->subDays(35),
        'paid_at' => null,
    ]);
    makePortfolioReminder($riskInvoice, ['sent_at' => now()->subDays(5)]);

    makePortfolioClient($company, ['name' => 'Immeuble ATLAN', 'sector' => 'Immobilier']);

    $component = Livewire::actingAs($user)->test('pages::pme.clients.index');

    $summary = $component->get('summary');
    $segments = $component->get('segmentCounts');
    $rows = collect($component->get('rows'));
    $insight = $component->get('insight');

    expect($summary['active_clients'])->toBe(2)
        ->and($summary['best_payer']['name'])->toBe('Sonatel')
        ->and($summary['watch_client']['name'])->toBe('Dakar Pharma')
        ->and($segments['all'])->toBe(3)
        ->and($segments['reliable'])->toBe(1)
        ->and($segments['inactive'])->toBe(1)
        ->and($segments['watch'])->toBeGreaterThanOrEqual(1)
        ->and($rows->firstWhere('name', 'Sonatel')['period_revenue'])->toBe(500_000)
        ->and($rows->firstWhere('name', 'Dakar Pharma')['outstanding_amount'])->toBe(800_000)
        ->and($insight['title'])->toBe('Exposition au risque')
        ->and($insight['body'])->toContain('Dakar Pharma');
});

test('la recherche peut filtrer sur le nom ou le secteur', function () {
    ['user' => $user, 'company' => $company] = createSmePortfolioOwner();

    makePortfolioClient($company, ['name' => 'Sonatel', 'sector' => 'Télécom']);
    makePortfolioClient($company, ['name' => 'Dakar Pharma', 'sector' => 'Santé']);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.clients.index')
        ->set('search', 'Santé');

    $rows = collect($component->get('rows'));

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['name'])->toBe('Dakar Pharma');
});

test('les segments permettent de voir uniquement les clients inactifs', function () {
    ['user' => $user, 'company' => $company] = createSmePortfolioOwner();

    $active = makePortfolioClient($company, ['name' => 'Sonatel']);
    makePortfolioInvoice($active);
    makePortfolioClient($company, ['name' => 'Immeuble ATLAN']);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.clients.index')
        ->set('segment', 'inactive');

    $rows = collect($component->get('rows'));

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['name'])->toBe('Immeuble ATLAN');
});

test('la periode limite le chiffre d affaires retenu dans la ligne client', function () {
    ['user' => $user, 'company' => $company] = createSmePortfolioOwner();

    $client = makePortfolioClient($company, ['name' => 'Transco SARL']);

    makePortfolioInvoice($client, [
        'total' => 400_000,
        'amount_paid' => 400_000,
        'issued_at' => now()->subDays(10),
        'due_at' => now()->addDays(10),
        'paid_at' => now()->subDays(2),
    ]);

    makePortfolioInvoice($client, [
        'total' => 900_000,
        'amount_paid' => 900_000,
        'issued_at' => now()->subMonths(11),
        'due_at' => now()->subMonths(11)->addDays(30),
        'paid_at' => now()->subMonths(11)->addDays(15),
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.clients.index')
        ->set('period', '30d');

    $row = collect($component->get('rows'))->firstWhere('name', 'Transco SARL');

    expect($row['period_revenue'])->toBe(400_000);
});

test('saveClient cree un client, normalise le telephone et redirige vers la fiche', function () {
    ['user' => $user, 'company' => $company] = createSmePortfolioOwner();

    $component = Livewire::actingAs($user)
        ->test('pages::pme.clients.index')
        ->set('clientName', 'Nouvelle Cliente')
        ->set('clientSector', 'Distribution')
        ->set('clientPhone', '77 123 45 67')
        ->set('clientEmail', 'contact@nouvelle.sn')
        ->set('clientTaxId', 'SN123456')
        ->set('clientAddress', 'Dakar Plateau')
        ->call('saveClient');

    $client = Client::query()->where('company_id', $company->id)->first();

    expect($client)->not->toBeNull()
        ->and($client->phone)->toBe('+221771234567')
        ->and($client->sector)->toBe('Distribution');

    $component->assertRedirect(route('pme.clients.show', $client));
});

test('la table expose les liens vers la fiche client et les actions rapides', function () {
    ['user' => $user, 'company' => $company] = createSmePortfolioOwner();

    $client = makePortfolioClient($company, ['name' => 'Dakar Pharma']);
    makePortfolioInvoice($client, [
        'status' => InvoiceStatus::Overdue->value,
        'total' => 300_000,
        'amount_paid' => 0,
        'issued_at' => now()->subDays(20),
        'due_at' => now()->subDays(5),
        'paid_at' => null,
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.clients.index')
        ->assertSee(route('pme.clients.show', $client), false)
        ->assertSee(route('pme.invoices.index', ['q' => 'Dakar Pharma']), false);
});

test('un visiteur est redirige vers la connexion sur la fiche client PME', function () {
    ['company' => $company] = createSmePortfolioOwner();
    $client = makePortfolioClient($company, ['name' => 'Sonatel']);

    $this->get(route('pme.clients.show', $client))
        ->assertRedirect(route('login'));
});

test('un utilisateur ne peut pas voir la fiche d un client qui appartient a une autre PME', function () {
    ['user' => $user, 'company' => $company] = createSmePortfolioOwner();
    $otherCompany = Company::factory()->create(['type' => 'sme']);
    $client = makePortfolioClient($otherCompany);

    Livewire::actingAs($user)
        ->test('pages::pme.clients.show', ['client' => $client])
        ->assertForbidden();
});

test('un utilisateur cabinet ne peut pas voir une fiche client PME', function () {
    ['company' => $company] = createSmePortfolioOwner();
    $client = makePortfolioClient($company);
    $user = User::factory()->accountantFirm()->create();

    $this->actingAs($user)
        ->get(route('pme.clients.show', $client))
        ->assertForbidden();
});

test('la fiche client affiche les totaux, les relances, les devis et la chronologie', function () {
    ['user' => $user, 'company' => $company] = createSmePortfolioOwner();

    $client = makePortfolioClient($company, [
        'name' => 'Dakar Pharma',
        'sector' => 'Santé',
        'phone' => '+221771112233',
        'email' => 'finance@dakarpharma.sn',
    ]);

    makePortfolioInvoice($client, [
        'reference' => 'FAC-100',
        'status' => InvoiceStatus::Paid->value,
        'total' => 500_000,
        'amount_paid' => 500_000,
        'issued_at' => now()->subDays(20),
        'due_at' => now()->subDays(5),
        'paid_at' => now()->subDays(3),
    ]);

    $overdue = makePortfolioInvoice($client, [
        'reference' => 'FAC-101',
        'status' => InvoiceStatus::Overdue->value,
        'total' => 300_000,
        'amount_paid' => 0,
        'issued_at' => now()->subDays(18),
        'due_at' => now()->subDays(10),
        'paid_at' => null,
    ]);

    makePortfolioReminder($overdue, [
        'sent_at' => now()->subDays(2),
        'message_body' => 'Merci de régulariser la facture FAC-101.',
    ]);

    makePortfolioQuote($client, [
        'reference' => 'DEV-777',
        'total' => 120_000,
        'issued_at' => now()->subDays(12),
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.clients.show', ['client' => $client])
        ->assertSee('Dakar Pharma')
        ->assertSee('Informations client')
        ->assertSee('Factures et impayés')
        ->assertSee('Relances')
        ->assertSee('Chronologie des interactions')
        ->assertSee('Merci de régulariser la facture FAC-101.');

    $detail = $component->get('detail');

    expect($detail['row']['total_revenue'])->toBe(800_000)
        ->and($detail['row']['outstanding_amount'])->toBe(300_000)
        ->and($detail['row']['payment_label'])->toBe('À surveiller')
        ->and($detail['contact']['email'])->toBe('finance@dakarpharma.sn')
        ->and($detail['invoices'])->toHaveCount(2)
        ->and($detail['quotes'])->toHaveCount(1)
        ->and($detail['payments'])->toHaveCount(1)
        ->and($detail['reminders'])->toHaveCount(1)
        ->and($detail['timeline'])->not->toBeEmpty();
});

test('la fiche client gere les etats vides sans erreurs', function () {
    ['user' => $user, 'company' => $company] = createSmePortfolioOwner();
    $client = makePortfolioClient($company, ['name' => 'Client Sans Historique']);

    Livewire::actingAs($user)
        ->test('pages::pme.clients.show', ['client' => $client])
        ->assertSee('Aucune facture liée à ce client pour le moment.')
        ->assertSee('Aucun devis pour ce client pour le moment.')
        ->assertSee('Aucun paiement enregistré pour ce client.')
        ->assertSee('Aucune relance n’a encore été envoyée à ce client.');
});

test('la fiche client permet de modifier les informations et de supprimer le secteur vide', function () {
    ['user' => $user, 'company' => $company] = createSmePortfolioOwner();

    $client = makePortfolioClient($company, [
        'name' => 'Dakar Pharma',
        'sector' => 'Santé',
        'phone' => '+221771112233',
        'email' => 'finance@dakarpharma.sn',
        'tax_id' => 'SN999999',
        'address' => 'Dakar',
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.clients.show', ['client' => $client])
        ->call('openEditClientModal')
        ->set('clientName', 'Dakar Pharma Groupe')
        ->set('clientSector', '')
        ->set('clientPhoneCountry', 'CI')
        ->set('clientPhone', '07 08 09 10 11')
        ->set('clientEmail', 'compta@dakarpharma.ci')
        ->set('clientTaxId', 'CI123456')
        ->set('clientAddress', 'Abidjan Plateau')
        ->call('saveClientUpdates')
        ->assertSee('Les informations client ont été mises à jour.')
        ->assertDontSee('Secteur');

    $client->refresh();

    expect($client->name)->toBe('Dakar Pharma Groupe')
        ->and($client->sector)->toBeNull()
        ->and($client->phone)->toBe('+2250708091011')
        ->and($client->email)->toBe('compta@dakarpharma.ci')
        ->and($client->tax_id)->toBe('CI123456')
        ->and($client->address)->toBe('Abidjan Plateau');
});

test('la fiche client ouvre la modale de facture depuis une ligne du tableau', function () {
    ['user' => $user, 'company' => $company] = createSmePortfolioOwner();

    $client = makePortfolioClient($company, ['name' => 'Dakar Pharma']);
    $invoice = makePortfolioInvoice($client, [
        'reference' => 'FAC-404',
        'status' => InvoiceStatus::Overdue->value,
        'total' => 350_000,
        'amount_paid' => 0,
        'issued_at' => now()->subDays(12),
        'due_at' => now()->subDays(2),
        'paid_at' => null,
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.clients.show', ['client' => $client])
        ->call('viewInvoice', $invoice->id)
        ->assertSet('selectedInvoiceId', $invoice->id)
        ->assertSee('FAC-404')
        ->assertSee('Détail des prestations')
        ->call('closeInvoice')
        ->assertSet('selectedInvoiceId', null);
});

test('la fiche client ouvre la modale de facture depuis le widget paiements', function () {
    ['user' => $user, 'company' => $company] = createSmePortfolioOwner();

    $client = makePortfolioClient($company, ['name' => 'Dakar Pharma']);
    $invoice = makePortfolioInvoice($client, [
        'reference' => 'FAC-505',
        'status' => InvoiceStatus::Paid->value,
        'total' => 420_000,
        'amount_paid' => 420_000,
        'issued_at' => now()->subDays(20),
        'due_at' => now()->subDays(5),
        'paid_at' => now()->subDays(1),
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.clients.show', ['client' => $client])
        ->call('viewInvoice', $invoice->id)
        ->assertSet('selectedInvoiceId', $invoice->id)
        ->assertSee('FAC-505')
        ->assertSee('Détail des prestations')
        ->call('closeInvoice')
        ->assertSet('selectedInvoiceId', null);
});

test('la fiche client ouvre la modale de facture depuis la chronologie des interactions', function () {
    ['user' => $user, 'company' => $company] = createSmePortfolioOwner();

    $client = makePortfolioClient($company, ['name' => 'Dakar Pharma']);
    $invoice = makePortfolioInvoice($client, [
        'reference' => 'FAC-606',
        'status' => InvoiceStatus::Paid->value,
        'total' => 275_000,
        'amount_paid' => 275_000,
        'issued_at' => now()->subDays(10),
        'due_at' => now()->subDays(2),
        'paid_at' => now()->subDay(),
    ]);

    makePortfolioReminder($invoice, [
        'sent_at' => now()->subDays(2),
        'message_body' => 'Rappel envoyé pour FAC-606.',
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.clients.show', ['client' => $client]);

    $timeline = collect($component->get('detail')['timeline']);

    expect($timeline->contains(fn (array $event) => $event['invoice_id'] === $invoice->id))->toBeTrue();

    $component
        ->call('viewInvoice', $invoice->id)
        ->assertSet('selectedInvoiceId', $invoice->id)
        ->assertSee('FAC-606')
        ->assertSee('Détail des prestations')
        ->call('closeInvoice')
        ->assertSet('selectedInvoiceId', null);
});
