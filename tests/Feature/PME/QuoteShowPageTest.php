<?php

use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\QuoteStatus;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Models\PME\Quote;
use App\Models\Shared\User;
use App\Services\PME\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function setupQuoteShow(?QuoteStatus $status = null, array $overrides = []): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme']);
    $company->users()->attach($user->id, ['role' => 'owner']);
    $client = Client::factory()->create(['company_id' => $company->id, 'name' => 'Sénégal Numérique']);

    $quote = Quote::unguarded(fn () => Quote::create(array_merge([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'FYK-DEV-SHOW01',
        'currency' => 'XOF',
        'status' => ($status ?? QuoteStatus::Sent)->value,
        'issued_at' => now(),
        'valid_until' => now()->addDays(30),
        'subtotal' => 100_000,
        'tax_amount' => 18_000,
        'total' => 118_000,
        'discount' => 0,
    ], $overrides)));

    return compact('user', 'company', 'client', 'quote');
}

// ─── Access ──────────────────────────────────────────────────────────────────

test('un visiteur non authentifié est redirigé vers la connexion', function () {
    ['quote' => $q] = setupQuoteShow();

    $this->get(route('pme.quotes.show', $q))->assertRedirect(route('login'));
});

test('un utilisateur SME peut accéder à la page show de son devis', function () {
    ['user' => $user, 'quote' => $q] = setupQuoteShow();

    $this->actingAs($user)
        ->get(route('pme.quotes.show', $q))
        ->assertOk()
        ->assertSee($q->reference)
        ->assertSee('Sénégal Numérique');
});

test('un utilisateur ne peut pas voir le devis d\'une autre société', function () {
    ['quote' => $q] = setupQuoteShow();
    $otherUser = User::factory()->create(['profile_type' => 'sme']);
    $otherCompany = Company::factory()->create(['type' => 'sme']);
    $otherCompany->users()->attach($otherUser->id, ['role' => 'owner']);

    $this->actingAs($otherUser)
        ->get(route('pme.quotes.show', $q))
        ->assertNotFound();
});

// ─── Status transitions ─────────────────────────────────────────────────────

test('markAsAccepted bascule le devis envoyé en Accepté', function () {
    ['user' => $user, 'quote' => $q] = setupQuoteShow(QuoteStatus::Sent);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $q])
        ->call('markAsAccepted');

    expect($q->fresh()->status)->toBe(QuoteStatus::Accepted);
});

test('markAsDeclined bascule le devis envoyé en Refusé', function () {
    ['user' => $user, 'quote' => $q] = setupQuoteShow(QuoteStatus::Sent);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $q])
        ->call('markAsDeclined');

    expect($q->fresh()->status)->toBe(QuoteStatus::Declined);
});

test('markAsAccepted n\'a aucun effet si le devis n\'est pas en Sent', function () {
    ['user' => $user, 'quote' => $q] = setupQuoteShow(QuoteStatus::Draft);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $q])
        ->call('markAsAccepted');

    expect($q->fresh()->status)->toBe(QuoteStatus::Draft);
});

// ─── Conversion en facture ──────────────────────────────────────────────────

test('convertToInvoice depuis la show page redirige vers l\'édition de la facture', function () {
    ['user' => $user, 'quote' => $q] = setupQuoteShow(QuoteStatus::Sent);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $q])
        ->call('convertToInvoice')
        ->assertRedirect();

    $invoice = Invoice::query()->where('quote_id', $q->id)->first();
    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe(InvoiceStatus::Draft);
});

test('convertToInvoice 2e appel est bloqué (toast erreur, pas de redirection)', function () {
    ['user' => $user, 'quote' => $q, 'company' => $company] = setupQuoteShow(QuoteStatus::Sent);

    app(QuoteService::class)->convertToInvoice($q, $company);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $q->fresh()])
        ->call('convertToInvoice')
        ->assertNoRedirect()
        ->assertDispatched('toast');
});

// ─── Suppression ────────────────────────────────────────────────────────────

test('deleteQuote supprime le devis brouillon et redirige vers l\'index', function () {
    ['user' => $user, 'quote' => $q] = setupQuoteShow(QuoteStatus::Draft);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $q])
        ->call('deleteQuote')
        ->assertRedirect(route('pme.quotes.index'));

    expect(Quote::query()->find($q->id))->toBeNull();
});

test('deleteQuote n\'a aucun effet si le devis n\'est pas en Draft', function () {
    ['user' => $user, 'quote' => $q] = setupQuoteShow(QuoteStatus::Sent);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $q])
        ->call('deleteQuote');

    expect(Quote::query()->find($q->id))->not->toBeNull();
});

// ─── Envoyer (WhatsApp / Email) ──────────────────────────────────────────────

test('openSendModal pré-remplit le téléphone (local) et le canal WhatsApp avec détection pays', function () {
    ['user' => $user, 'quote' => $q, 'client' => $client] = setupQuoteShow(QuoteStatus::Sent);
    $client->update(['phone' => '+221770000000']);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $q->fresh()])
        ->call('openSendModal')
        ->assertSet('showSendModal', true)
        ->assertSet('sendChannel', 'whatsapp')
        ->assertSet('sendCountry', 'SN')
        ->assertSet('sendRecipient', '770000000');
});

test('openSendModal bascule en email si le client n\'a pas de téléphone', function () {
    ['user' => $user, 'quote' => $q, 'client' => $client] = setupQuoteShow(QuoteStatus::Sent);
    $client->update(['phone' => null, 'email' => 'contact@client.sn']);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $q->fresh()])
        ->call('openSendModal')
        ->assertSet('sendChannel', 'email')
        ->assertSet('sendRecipient', 'contact@client.sn');
});

test('le message inclut toujours le lien public PDF du devis', function () {
    ['user' => $user, 'quote' => $q] = setupQuoteShow(QuoteStatus::Sent);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $q])
        ->call('openSendModal');

    expect($component->get('sendMessage'))->toContain(route('pme.quotes.pdf', $q->public_code));
});

test('le template devis suit le format demandé (Bonjour sans civilité, signature dynamique)', function () {
    ['user' => $user, 'quote' => $q, 'company' => $company] = setupQuoteShow(QuoteStatus::Sent);
    $company->update([
        'name' => 'Rassoul Electronique Services',
        'sender_name' => 'Moussa Diop',
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $q->fresh()])
        ->call('openSendModal');

    $message = $component->get('sendMessage');

    expect($message)
        ->toStartWith('Bonjour,')
        ->not->toContain('M.') // pas de civilité M./Mme
        ->toContain('Suite à votre demande')
        ->toContain($q->reference)
        ->toContain('TTC')
        ->toContain('valable jusqu\'au')
        ->toContain(route('pme.quotes.pdf', $q->public_code))
        ->toContain("N'hésitez pas")
        ->toEndWith("Cordialement,\nMoussa Diop\nRassoul Electronique Services");
});

test('le template devis omet le sender_name s\'il n\'est pas renseigné', function () {
    ['user' => $user, 'quote' => $q, 'company' => $company] = setupQuoteShow(QuoteStatus::Sent);
    $company->update(['name' => 'Solo PME', 'sender_name' => null]);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $q->fresh()])
        ->call('openSendModal');

    expect($component->get('sendMessage'))
        ->toEndWith("Cordialement,\nSolo PME");
});

test('confirmSend WhatsApp construit wa.me/<international> à partir du local + pays', function () {
    ['user' => $user, 'quote' => $q, 'client' => $client] = setupQuoteShow(QuoteStatus::Sent);
    $client->update(['phone' => '+221770000000']);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $q->fresh()])
        ->call('openSendModal')
        ->call('confirmSend')
        ->assertDispatched('open-external-url', function ($name, $params) {
            return str_starts_with($params['url'], 'https://wa.me/221770000000?text=');
        });
});

test('confirmSend WhatsApp normalise un numéro local saisi sans préfixe', function () {
    ['user' => $user, 'quote' => $q] = setupQuoteShow(QuoteStatus::Sent);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $q])
        ->call('openSendModal')
        ->set('sendChannel', 'whatsapp')
        ->set('sendCountry', 'SN')
        ->set('sendRecipient', '770000000')
        ->call('confirmSend')
        ->assertDispatched('open-external-url', function ($name, $params) {
            return str_starts_with($params['url'], 'https://wa.me/221770000000?text=');
        });
});

test('REGRESSION : saisie d\'un numéro malien (pays ML) produit wa.me/223...', function () {
    // Bug rapporté : numéro "774273273232" + pays MLI s'ouvrait sur "wa.me/774273273232"
    // (sans le préfixe 223). PhoneNumber::digitsForWhatsApp doit ajouter +223.
    ['user' => $user, 'quote' => $q] = setupQuoteShow(QuoteStatus::Sent);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $q])
        ->call('openSendModal')
        ->set('sendChannel', 'whatsapp')
        ->set('sendCountry', 'ML')
        ->set('sendRecipient', '774273273232')
        ->call('confirmSend')
        ->assertDispatched('open-external-url', function ($name, $params) {
            return str_starts_with($params['url'], 'https://wa.me/223774273273232?text=');
        });
});

test('confirmSend en Email dispatch mailto:client@...', function () {
    ['user' => $user, 'quote' => $q, 'client' => $client] = setupQuoteShow(QuoteStatus::Sent);
    $client->update(['email' => 'jean@client.sn']);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $q->fresh()])
        ->call('openSendModal')
        ->set('sendChannel', 'email')
        ->set('sendRecipient', 'jean@client.sn')
        ->call('confirmSend')
        ->assertDispatched('open-external-url', function ($name, $params) {
            return str_starts_with($params['url'], 'mailto:jean@client.sn?subject=');
        });
});

test('confirmSend sur un devis Draft le passe automatiquement en Sent + toast', function () {
    ['user' => $user, 'quote' => $q, 'client' => $client] = setupQuoteShow(QuoteStatus::Draft);
    $client->update(['phone' => '+221770000000']);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $q->fresh()])
        ->call('openSendModal')
        ->call('confirmSend')
        ->assertHasNoErrors()
        ->assertDispatched('open-external-url')
        ->assertDispatched('toast', function ($name, $params) {
            return ($params['type'] ?? null) === 'success'
                && str_contains((string) ($params['title'] ?? ''), 'envoyé');
        });

    expect($q->fresh()->status)->toBe(QuoteStatus::Sent);
});

test('confirmSend sur un devis déjà Sent ne ré-affiche PAS le toast de bascule statut', function () {
    ['user' => $user, 'quote' => $q, 'client' => $client] = setupQuoteShow(QuoteStatus::Sent);
    $client->update(['phone' => '+221770000000']);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $q->fresh()])
        ->call('openSendModal')
        ->call('confirmSend');

    // L'événement open-external-url est toujours émis (le canal s'ouvre).
    $component->assertDispatched('open-external-url');

    // Mais aucun toast "marqué comme envoyé" — le statut ne change pas.
    $events = collect($component->effects['dispatches'] ?? []);
    $statusToast = $events->first(fn ($e) => ($e['name'] ?? null) === 'toast' && str_contains((string) ($e['params']['title'] ?? ''), 'envoyé'));
    expect($statusToast)->toBeNull();
});

test('confirmSend exige un destinataire', function () {
    ['user' => $user, 'quote' => $q] = setupQuoteShow(QuoteStatus::Sent);

    Livewire::actingAs($user)
        ->test('pages::pme.quotes.show', ['quote' => $q])
        ->call('openSendModal')
        ->set('sendRecipient', '')
        ->call('confirmSend')
        ->assertHasErrors(['sendRecipient']);
});
