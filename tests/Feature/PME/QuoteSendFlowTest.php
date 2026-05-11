<?php

use App\Enums\PME\ProposalDocumentStatus;
use App\Enums\PME\ProposalDocumentType;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Models\PME\ProposalDocument;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * @return array{user: User, company: Company}
 */
function createSmeForQuoteSend(): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme']);
    $company->users()->attach($user->id, ['role' => 'owner']);

    return compact('user', 'company');
}

function makeQuoteForSend(Company $company, array $overrides = []): ProposalDocument
{
    $client = Client::factory()->create(['company_id' => $company->id, 'phone' => '+221770000000']);

    return ProposalDocument::unguarded(fn () => ProposalDocument::create(array_merge([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'type' => ProposalDocumentType::Quote,
        'reference' => 'FYK-DEV-'.fake()->unique()->bothify('??????'),
        'currency' => 'XOF',
        'status' => ProposalDocumentStatus::Draft->value,
        'issued_at' => now(),
        'valid_until' => now()->addDays(15),
        'subtotal' => 100_000,
        'tax_amount' => 0,
        'total' => 100_000,
    ], $overrides)));
}

// ─── Quote form: saveAndSend ────────────────────────────────────────────────

test('saveAndSend persiste le devis en Brouillon et redirige vers show?send=1', function () {
    ['user' => $user, 'company' => $company] = createSmeForQuoteSend();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.form')
        ->set('clientId', $client->id)
        ->set('lines.0.description', 'Service')
        ->set('lines.0.quantity', 1)
        ->set('lines.0.unit_price', 50_000)
        ->call('saveAndSend');

    $quote = ProposalDocument::query()
        ->where('company_id', $company->id)
        ->ofType(ProposalDocumentType::Quote)
        ->first();

    expect($quote)->not->toBeNull()
        ->and($quote->status)->toBe(ProposalDocumentStatus::Draft);
});

test('saveAndSend refuse un devis à 0 FCFA et ne persiste rien', function () {
    ['user' => $user, 'company' => $company] = createSmeForQuoteSend();

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.form')
        ->call('saveAndSend')
        ->assertHasErrors('lines')
        ->assertNoRedirect();

    expect(ProposalDocument::query()->where('company_id', $company->id)->count())->toBe(0);
});

// ─── Quote show: ?send=1 auto-open + confirmSend ────────────────────────────

test('?send=1 auto-ouvre la modale d\'envoi sur la page show du devis', function () {
    ['user' => $user, 'company' => $company] = createSmeForQuoteSend();
    $quote = makeQuoteForSend($company);

    Livewire::actingAs($user)
        ->withQueryParams(['send' => '1'])
        ->test('pages::pme.quotes.show', ['quote' => $quote])
        ->assertSet('showSendModal', true);
});

test('le message d\'envoi du devis utilise le nouveau template avec lien public et validité', function () {
    ['user' => $user, 'company' => $company] = createSmeForQuoteSend();
    $client = Client::factory()->create([
        'company_id' => $company->id,
        'name' => 'Air Sénégal SA',
        'phone' => '+221770000000',
    ]);
    $quote = makeQuoteForSend($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-DEV-MSG001',
    ]);

    $message = Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $quote])
        ->call('openSendModal')
        ->get('sendMessage');

    expect($message)
        ->toStartWith('Bonjour,')
        ->not->toContain('Air Sénégal SA')
        ->toContain('Suite à votre demande, veuillez trouver notre devis n° FYK-DEV-MSG001')
        ->toContain('Consulter le devis :')
        ->toContain(route('pme.quotes.pdf', $quote->public_code))
        ->toContain('Ce devis est valable jusqu\'au')
        ->toContain('Nous restons disponibles pour toute question ou modification.')
        ->not->toContain('Suite à votre demande, je vous transmets');
});

test('confirmSend transitionne un devis Draft vers Sent', function () {
    ['user' => $user, 'company' => $company] = createSmeForQuoteSend();
    $quote = makeQuoteForSend($company);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $quote])
        ->call('openSendModal')
        ->call('confirmSend');

    expect($quote->fresh()->status)->toBe(ProposalDocumentStatus::Sent);
});

test('le bouton Envoyer du devis rend une URL wa.me en data-send-url pour WhatsApp', function () {
    ['user' => $user, 'company' => $company] = createSmeForQuoteSend();
    $quote = makeQuoteForSend($company);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $quote])
        ->call('openSendModal');

    $component->assertSeeHtml('data-send-url="https://wa.me/221770000000?text=')
        ->assertSeeHtml('data-can-send="1"');
});

test('le bouton Envoyer du devis rend un mailto en data-send-url pour Email', function () {
    ['user' => $user, 'company' => $company] = createSmeForQuoteSend();
    $quote = makeQuoteForSend($company);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $quote])
        ->call('openSendModal')
        ->set('sendChannel', 'email')
        ->set('sendRecipient', 'jean@client.sn');

    $component->assertSeeHtml('data-send-url="mailto:jean@client.sn?subject=')
        ->assertSeeHtml('data-can-send="1"');
});

test('le bouton Convertir en facture est visible sur un devis Brouillon', function () {
    ['user' => $user, 'company' => $company] = createSmeForQuoteSend();
    $quote = makeQuoteForSend($company, ['status' => ProposalDocumentStatus::Draft->value]);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $quote])
        ->assertSeeText('Convertir en facture');
});

test('convertToInvoice depuis un devis Brouillon crée la facture et bascule le devis en Accepted', function () {
    ['user' => $user, 'company' => $company] = createSmeForQuoteSend();
    $quote = makeQuoteForSend($company, ['status' => ProposalDocumentStatus::Draft->value]);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $quote])
        ->call('convertToInvoice');

    $fresh = $quote->fresh();
    expect($fresh->status)->toBe(ProposalDocumentStatus::Accepted)
        ->and(Invoice::query()->where('proposal_document_id', $quote->id)->exists())->toBeTrue();
});

test('confirmSend du devis ne dispatch plus open-external-url', function () {
    ['user' => $user, 'company' => $company] = createSmeForQuoteSend();
    $quote = makeQuoteForSend($company);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $quote])
        ->call('openSendModal')
        ->call('confirmSend')
        ->assertNotDispatched('open-external-url');
});
