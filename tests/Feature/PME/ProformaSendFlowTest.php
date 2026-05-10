<?php

use App\Enums\PME\ProposalDocumentStatus;
use App\Enums\PME\ProposalDocumentType;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\ProposalDocument;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * @return array{user: User, company: Company}
 */
function createSmeForProformaSend(): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme']);
    $company->users()->attach($user->id, ['role' => 'owner']);

    return compact('user', 'company');
}

function makeProformaForSend(Company $company, array $overrides = []): ProposalDocument
{
    $client = Client::factory()->create(['company_id' => $company->id, 'phone' => '+221770000000']);

    return ProposalDocument::unguarded(fn () => ProposalDocument::create(array_merge([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'type' => ProposalDocumentType::Proforma,
        'reference' => 'FYK-PRO-'.fake()->unique()->bothify('??????'),
        'currency' => 'XOF',
        'status' => ProposalDocumentStatus::Draft->value,
        'issued_at' => now(),
        'valid_until' => now()->addDays(15),
        'subtotal' => 100_000,
        'tax_amount' => 0,
        'total' => 100_000,
    ], $overrides)));
}

// ─── Proforma form: saveAndSend ─────────────────────────────────────────────

test('saveAndSend persiste la proforma en Brouillon et redirige vers show?send=1', function () {
    ['user' => $user, 'company' => $company] = createSmeForProformaSend();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.form')
        ->set('clientId', $client->id)
        ->set('lines.0.description', 'Service')
        ->set('lines.0.quantity', 1)
        ->set('lines.0.unit_price', 50_000)
        ->call('saveAndSend');

    $proforma = ProposalDocument::query()
        ->where('company_id', $company->id)
        ->ofType(ProposalDocumentType::Proforma)
        ->first();

    expect($proforma)->not->toBeNull()
        ->and($proforma->status)->toBe(ProposalDocumentStatus::Draft);
});

test('saveAndSend refuse une proforma à 0 FCFA et ne persiste rien', function () {
    ['user' => $user, 'company' => $company] = createSmeForProformaSend();

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.form')
        ->call('saveAndSend')
        ->assertHasErrors('lines')
        ->assertNoRedirect();

    expect(ProposalDocument::query()->where('company_id', $company->id)->count())->toBe(0);
});

// ─── Proforma show: ?send=1 auto-open + confirmSend ─────────────────────────

test('?send=1 auto-ouvre la modale d\'envoi sur la page show de la proforma', function () {
    ['user' => $user, 'company' => $company] = createSmeForProformaSend();
    $proforma = makeProformaForSend($company);

    Livewire::actingAs($user)
        ->withQueryParams(['send' => '1'])
        ->test('pages::pme.proformas.show', ['proforma' => $proforma])
        ->assertSet('showSendModal', true);
});

test('le message d\'envoi de la proforma utilise le nouveau template avec lien public et validité', function () {
    ['user' => $user, 'company' => $company] = createSmeForProformaSend();
    $client = Client::factory()->create([
        'company_id' => $company->id,
        'name' => 'Orange Sénégal SA',
        'phone' => '+221770000000',
    ]);
    $proforma = makeProformaForSend($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-PRO-MSG001',
    ]);

    $message = Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $proforma])
        ->call('openSendModal')
        ->get('sendMessage');

    expect($message)
        ->toStartWith('Bonjour,')
        ->not->toContain('Orange Sénégal SA')
        ->toContain('Suite à nos échanges, veuillez trouver notre facture proforma n° FYK-PRO-MSG001')
        ->toContain('Consulter la proforma :')
        ->toContain(route('pme.proformas.pdf', $proforma->public_code))
        ->toContain('Cette proforma est valable jusqu\'au')
        ->toContain('peut servir à la validation de votre commande.')
        ->not->toContain('Conformément à notre échange');
});

test('confirmSend transitionne une proforma Draft vers Sent', function () {
    ['user' => $user, 'company' => $company] = createSmeForProformaSend();
    $proforma = makeProformaForSend($company);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $proforma])
        ->call('openSendModal')
        ->call('confirmSend');

    expect($proforma->fresh()->status)->toBe(ProposalDocumentStatus::Sent);
});

test('le bouton Envoyer de la proforma rend une URL wa.me en data-send-url pour WhatsApp', function () {
    ['user' => $user, 'company' => $company] = createSmeForProformaSend();
    $proforma = makeProformaForSend($company);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $proforma])
        ->call('openSendModal');

    $component->assertSeeHtml('data-send-url="https://wa.me/221770000000?text=')
        ->assertSeeHtml('data-can-send="1"');
});

test('le bouton Envoyer de la proforma rend un mailto en data-send-url pour Email', function () {
    ['user' => $user, 'company' => $company] = createSmeForProformaSend();
    $proforma = makeProformaForSend($company);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $proforma])
        ->call('openSendModal')
        ->set('sendChannel', 'email')
        ->set('sendRecipient', 'jean@client.sn');

    $component->assertSeeHtml('data-send-url="mailto:jean@client.sn?subject=')
        ->assertSeeHtml('data-can-send="1"');
});

test('confirmSend de la proforma ne dispatch plus open-external-url', function () {
    ['user' => $user, 'company' => $company] = createSmeForProformaSend();
    $proforma = makeProformaForSend($company);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $proforma])
        ->call('openSendModal')
        ->call('confirmSend')
        ->assertNotDispatched('open-external-url');
});
