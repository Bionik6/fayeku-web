<?php

/**
 * Tests d'intégration du flux de relance manuelle depuis la fiche facture.
 *
 * Couvre :
 *   - sendReminderNow : succès (3 tons), réinitialisation de previewInvoiceId,
 *     persistance en base, contenu du message selon le ton choisi.
 *   - sendReminderNow : garde-fou week-end → toast warning, aucune relance en base.
 *   - sendReminderNow : échec du provider → toast error (pas "bientôt disponible"),
 *     aucune relance en base.
 *   - Intégrité des paramètres envoyés au provider WhatsApp : exactement 6 paramètres
 *     nommés, sans la clé dupliquée invoice_due_date.
 */

use App\Enums\PME\InvoiceStatus;
use App\Interfaces\Shared\WhatsAppProviderInterface;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Models\PME\Reminder;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * @return array{user: User, company: Company}
 */
function makeReminderOwner(array $companyOverrides = []): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(array_merge([
        'type' => 'sme',
        'name' => 'Sow BTP',
        'sender_name' => 'Ibrahima Ciss',
        'sender_role' => 'Directeur',
    ], $companyOverrides));
    $company->users()->attach($user->id, ['role' => 'owner']);

    return compact('user', 'company');
}

function makeReminderInvoice(Company $company, array $overrides = []): Invoice
{
    $client = Client::factory()->create([
        'company_id' => $company->id,
        'name' => 'Dakar Pharma',
        'phone' => '+221771112233',
    ]);

    return Invoice::unguarded(fn () => Invoice::create(array_merge([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'FAC-REL-'.fake()->unique()->numerify('###'),
        'currency' => 'XOF',
        'status' => InvoiceStatus::Overdue->value,
        'issued_at' => now()->subDays(30),
        'due_at' => now()->subDays(10),
        'subtotal' => 250_000,
        'tax_amount' => 0,
        'total' => 250_000,
        'amount_paid' => 0,
    ], $overrides)));
}

beforeEach(function () {
    // Mercredi matin → hors week-end pour que le garde-fou ne bloque pas.
    $this->travelTo(now()->startOfWeek()->addDays(2)->setHour(10));
});

// ─── Succès : les 3 tons ──────────────────────────────────────────────────────

test('sendReminderNow dispatche un toast de succès et persiste la relance (ton cordial)', function () {
    ['user' => $user, 'company' => $company] = makeReminderOwner();
    $invoice = makeReminderInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->set('previewTone', 'cordial')
        ->call('sendReminderNow')
        ->assertDispatched('toast', type: 'success');

    $reminder = Reminder::query()->where('invoice_id', $invoice->id)->first();

    expect($reminder)->not->toBeNull()
        ->and($reminder->message_body)->toContain('Dakar Pharma')
        ->and($reminder->message_body)->toContain($invoice->reference)
        ->and($reminder->message_body)->toContain('Sow BTP')
        ->and($reminder->message_body)->toContain('Cordialement');
});

test('sendReminderNow persiste la relance avec le bon message pour le ton ferme', function () {
    ['user' => $user, 'company' => $company] = makeReminderOwner();
    $invoice = makeReminderInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->set('previewTone', 'ferme')
        ->call('sendReminderNow')
        ->assertDispatched('toast', type: 'success');

    $reminder = Reminder::query()->where('invoice_id', $invoice->id)->first();

    expect($reminder)->not->toBeNull()
        ->and($reminder->message_body)->toContain('retard de paiement')
        ->and($reminder->message_body)->toContain($invoice->reference)
        ->and($reminder->message_body)->toContain('Sow BTP');
});

test('sendReminderNow persiste la relance avec le bon message pour le ton urgent', function () {
    ['user' => $user, 'company' => $company] = makeReminderOwner();
    $invoice = makeReminderInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->set('previewTone', 'urgent')
        ->call('sendReminderNow')
        ->assertDispatched('toast', type: 'success');

    $reminder = Reminder::query()->where('invoice_id', $invoice->id)->first();

    expect($reminder)->not->toBeNull()
        ->and($reminder->message_body)->toContain('URGENT')
        ->and($reminder->message_body)->toContain($invoice->reference)
        ->and($reminder->message_body)->toContain('Sow BTP');
});

// ─── Réinitialisation de l'état après succès ──────────────────────────────────

test('sendReminderNow réinitialise previewInvoiceId à null après succès', function () {
    ['user' => $user, 'company' => $company] = makeReminderOwner();
    $invoice = makeReminderInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openReminderPreview')
        ->assertSet('previewInvoiceId', $invoice->id)
        ->call('sendReminderNow')
        ->assertSet('previewInvoiceId', null);
});

// ─── Garde-fou week-end ───────────────────────────────────────────────────────

test('sendReminderNow refuse le week-end avec un toast warning et aucune relance en base', function () {
    ['user' => $user, 'company' => $company] = makeReminderOwner();
    $invoice = makeReminderInvoice($company);

    $this->travelTo(now()->startOfWeek()->addDays(5)->setHour(10)); // Samedi

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('sendReminderNow')
        ->assertDispatched('toast', type: 'warning');

    expect(Reminder::query()->where('invoice_id', $invoice->id)->exists())->toBeFalse();
});

// ─── Échec du provider ────────────────────────────────────────────────────────

test('sendReminderNow dispatche un toast error quand le provider WhatsApp échoue', function () {
    ['user' => $user, 'company' => $company] = makeReminderOwner();
    $invoice = makeReminderInvoice($company);

    // Forcer l'échec : le FakeWhatsAppProvider renvoie true en test, on le remplace.
    $this->mock(WhatsAppProviderInterface::class, function (MockInterface $m) {
        $m->shouldReceive('sendTemplate')->andReturnFalse();
        $m->shouldReceive('send')->andReturnFalse();
    });

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('sendReminderNow')
        ->assertDispatched('toast', type: 'error');

    expect(Reminder::query()->where('invoice_id', $invoice->id)->exists())->toBeFalse();
});

test('sendReminderNow ne dispatche pas de toast warning bientôt-disponible en cas d\'échec', function () {
    ['user' => $user, 'company' => $company] = makeReminderOwner();
    $invoice = makeReminderInvoice($company);

    $this->mock(WhatsAppProviderInterface::class, function (MockInterface $m) {
        $m->shouldReceive('sendTemplate')->andReturnFalse();
        $m->shouldReceive('send')->andReturnFalse();
    });

    $component = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('sendReminderNow');

    $component->assertDispatched('toast', type: 'error');
    $component->assertNotDispatched('toast', type: 'warning');
});

// ─── Intégrité des paramètres envoyés au provider ────────────────────────────

test('sendReminderNow envoie exactement 6 paramètres nommés au provider WhatsApp', function () {
    ['user' => $user, 'company' => $company] = makeReminderOwner();
    $invoice = makeReminderInvoice($company);

    $capturedParams = null;

    $this->mock(WhatsAppProviderInterface::class, function (MockInterface $m) use (&$capturedParams) {
        $m->shouldReceive('sendTemplate')
            ->once()
            ->withArgs(function (string $phone, string $template, array $bodyParameters) use (&$capturedParams) {
                $capturedParams = $bodyParameters;

                return true;
            })
            ->andReturnTrue();
    });

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->set('previewTone', 'cordial')
        ->call('sendReminderNow')
        ->assertDispatched('toast', type: 'success');

    expect($capturedParams)->toHaveCount(6)
        ->toHaveKeys(['client_name', 'company_name', 'invoice_number', 'invoice_amount', 'due_date', 'sender_signature'])
        ->not->toHaveKey('invoice_due_date');
});

test('sendReminderNow n\'inclut pas invoice_due_date pour aucun des 3 tons', function () {
    ['user' => $user, 'company' => $company] = makeReminderOwner();

    foreach (['cordial', 'ferme', 'urgent'] as $tone) {
        $invoice = makeReminderInvoice($company);
        $capturedParams = null;

        $this->mock(WhatsAppProviderInterface::class, function (MockInterface $m) use (&$capturedParams) {
            $m->shouldReceive('sendTemplate')
                ->withArgs(function ($phone, $template, array $bodyParameters) use (&$capturedParams) {
                    $capturedParams = $bodyParameters;

                    return true;
                })
                ->andReturnTrue();
        });

        Livewire::actingAs($user)
            ->test('pages::pme.invoices.show', ['invoice' => $invoice])
            ->set('previewTone', $tone)
            ->call('sendReminderNow');

        expect($capturedParams, "Ton «{$tone}»")
            ->not->toHaveKey('invoice_due_date')
            ->toHaveKey('due_date');
    }
});
