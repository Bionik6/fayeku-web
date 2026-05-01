<?php

use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\ProformaStatus;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Models\PME\Proforma;
use App\Models\Shared\User;
use App\Services\PME\ProformaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function setupProformaShow(?ProformaStatus $status = null, array $overrides = []): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme']);
    $company->users()->attach($user->id, ['role' => 'owner']);
    $client = Client::factory()->create(['company_id' => $company->id, 'name' => 'Wari Sénégal']);

    $proforma = Proforma::unguarded(fn () => Proforma::create(array_merge([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'FYK-PRO-SHOW01',
        'currency' => 'XOF',
        'status' => ($status ?? ProformaStatus::Sent)->value,
        'issued_at' => now(),
        'valid_until' => now()->addDays(30),
        'subtotal' => 100_000,
        'tax_amount' => 18_000,
        'total' => 118_000,
        'discount' => 0,
        'dossier_reference' => 'DAO 2026/MEF/045',
        'payment_terms' => '30 jours fin de mois',
        'delivery_terms' => '15 jours ouvrés',
    ], $overrides)));

    return compact('user', 'company', 'client', 'proforma');
}

// ─── Access ──────────────────────────────────────────────────────────────────

test('un visiteur non authentifié est redirigé vers la connexion', function () {
    ['proforma' => $p] = setupProformaShow();

    $this->get(route('pme.proformas.show', $p))->assertRedirect(route('login'));
});

test('un utilisateur SME peut accéder à la page show de sa proforma', function () {
    ['user' => $user, 'proforma' => $p] = setupProformaShow();

    $this->actingAs($user)
        ->get(route('pme.proformas.show', $p))
        ->assertOk()
        ->assertSee($p->reference)
        ->assertSee('Wari Sénégal')
        ->assertSee('DAO 2026/MEF/045')
        ->assertSee('30 jours fin de mois');
});

test('un utilisateur ne peut pas voir la proforma d\'une autre société', function () {
    ['proforma' => $p] = setupProformaShow();
    $otherUser = User::factory()->create(['profile_type' => 'sme']);
    $otherCompany = Company::factory()->create(['type' => 'sme']);
    $otherCompany->users()->attach($otherUser->id, ['role' => 'owner']);

    $this->actingAs($otherUser)
        ->get(route('pme.proformas.show', $p))
        ->assertNotFound();
});

// ─── Status transitions ─────────────────────────────────────────────────────

test('markAsPoReceived bascule la proforma envoyée en BC reçu', function () {
    ['user' => $user, 'proforma' => $p] = setupProformaShow(ProformaStatus::Sent);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $p])
        ->call('markAsPoReceived');

    expect($p->fresh()->status)->toBe(ProformaStatus::PoReceived);
});

test('markAsPoReceived n\'a aucun effet si la proforma n\'est pas en Sent', function () {
    ['user' => $user, 'proforma' => $p] = setupProformaShow(ProformaStatus::Draft);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $p])
        ->call('markAsPoReceived');

    expect($p->fresh()->status)->toBe(ProformaStatus::Draft);
});

test('markAsDeclined bascule la proforma envoyée en Refusée', function () {
    ['user' => $user, 'proforma' => $p] = setupProformaShow(ProformaStatus::Sent);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $p])
        ->call('markAsDeclined');

    expect($p->fresh()->status)->toBe(ProformaStatus::Declined);
});

// ─── Conversion en facture ──────────────────────────────────────────────────

test('convertToInvoice depuis la show page redirige vers l\'édition de la facture', function () {
    ['user' => $user, 'proforma' => $p] = setupProformaShow(ProformaStatus::PoReceived);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $p])
        ->call('convertToInvoice')
        ->assertRedirect();

    $invoice = Invoice::query()->where('proforma_id', $p->id)->first();
    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe(InvoiceStatus::Draft);
    expect($p->fresh()->status)->toBe(ProformaStatus::Converted);
});

test('convertToInvoice double appel : 2e tentative est bloquée par le service (toast erreur)', function () {
    ['user' => $user, 'proforma' => $p, 'company' => $company] = setupProformaShow(ProformaStatus::PoReceived);

    // Première conversion OK
    app(ProformaService::class)->convertToInvoice($p, $company);

    // 2e via la page → toast d'erreur, pas de redirection
    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $p->fresh()])
        ->call('convertToInvoice')
        ->assertNoRedirect()
        ->assertDispatched('toast');
});

// ─── Suppression ────────────────────────────────────────────────────────────

test('deleteProforma supprime la proforma en brouillon et redirige vers l\'index', function () {
    ['user' => $user, 'proforma' => $p] = setupProformaShow(ProformaStatus::Draft);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $p])
        ->call('deleteProforma')
        ->assertRedirect(route('pme.quotes.index'));

    expect(Proforma::query()->find($p->id))->toBeNull();
});

test('deleteProforma n\'a aucun effet si la proforma n\'est pas en Draft', function () {
    ['user' => $user, 'proforma' => $p] = setupProformaShow(ProformaStatus::Sent);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $p])
        ->call('deleteProforma');

    expect(Proforma::query()->find($p->id))->not->toBeNull();
});

// ─── Enregistrer un bon de commande (modal) ──────────────────────────────────

test('openPoModal pré-remplit les valeurs et passe en mode modal', function () {
    ['user' => $user, 'proforma' => $p] = setupProformaShow(ProformaStatus::Sent);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $p])
        ->call('openPoModal')
        ->assertSet('showPoModal', true)
        ->assertSet('poReference', '')
        ->assertSet('poReceivedAt', now()->format('Y-m-d'));
});

test('openPoModal n\'ouvre pas si la proforma n\'est pas en Sent', function () {
    ['user' => $user, 'proforma' => $p] = setupProformaShow(ProformaStatus::Draft);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $p])
        ->call('openPoModal')
        ->assertSet('showPoModal', false);
});

test('recordPurchaseOrder persiste référence + date + notes ET passe en PoReceived', function () {
    ['user' => $user, 'proforma' => $p] = setupProformaShow(ProformaStatus::Sent);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $p])
        ->call('openPoModal')
        ->set('poReference', 'BC-2026/0142')
        ->set('poReceivedAt', '2026-04-30')
        ->set('poNotes', 'Validé par M. Diop')
        ->call('recordPurchaseOrder')
        ->assertHasNoErrors()
        ->assertSet('showPoModal', false);

    $fresh = $p->fresh();
    expect($fresh->status)->toBe(ProformaStatus::PoReceived)
        ->and($fresh->po_reference)->toBe('BC-2026/0142')
        ->and($fresh->po_received_at->format('Y-m-d'))->toBe('2026-04-30')
        ->and($fresh->po_notes)->toBe('Validé par M. Diop');
});

test('recordPurchaseOrder valide la référence requise', function () {
    ['user' => $user, 'proforma' => $p] = setupProformaShow(ProformaStatus::Sent);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $p])
        ->call('openPoModal')
        ->set('poReference', '')
        ->call('recordPurchaseOrder')
        ->assertHasErrors(['poReference']);

    expect($p->fresh()->status)->toBe(ProformaStatus::Sent);
});

test('la show page affiche la référence du BC enregistré dans le KPI', function () {
    ['user' => $user, 'proforma' => $p] = setupProformaShow(ProformaStatus::PoReceived, [
        'po_reference' => 'BC-2026/0099',
        'po_received_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('pme.proformas.show', $p))
        ->assertOk()
        ->assertSee('BC-2026/0099');
});

// ─── Envoyer (WhatsApp / Email) ──────────────────────────────────────────────

test('openSendModal pré-remplit le téléphone du client (numéro local) et détecte le pays SN', function () {
    ['user' => $user, 'proforma' => $p, 'client' => $client] = setupProformaShow(ProformaStatus::Sent);
    $client->update(['phone' => '+221771234567']);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $p->fresh()])
        ->call('openSendModal')
        ->assertSet('showSendModal', true)
        ->assertSet('sendChannel', 'whatsapp')
        ->assertSet('sendCountry', 'SN')
        ->assertSet('sendRecipient', '771234567');
});

test('openSendModal détecte le pays CI quand le téléphone client est ivoirien', function () {
    ['user' => $user, 'proforma' => $p, 'client' => $client] = setupProformaShow(ProformaStatus::Sent);
    $client->update(['phone' => '+2250712345678']);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $p->fresh()])
        ->call('openSendModal')
        ->assertSet('sendCountry', 'CI')
        ->assertSet('sendRecipient', '0712345678');
});

test('openSendModal bascule en email si le client n\'a pas de téléphone', function () {
    ['user' => $user, 'proforma' => $p, 'client' => $client] = setupProformaShow(ProformaStatus::Sent);
    $client->update(['phone' => null, 'email' => 'contact@client.sn']);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $p->fresh()])
        ->call('openSendModal')
        ->assertSet('sendChannel', 'email')
        ->assertSet('sendRecipient', 'contact@client.sn');
});

test('le message inclut toujours le lien public PDF de la proforma', function () {
    ['user' => $user, 'proforma' => $p] = setupProformaShow(ProformaStatus::Sent);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $p])
        ->call('openSendModal');

    expect($component->get('sendMessage'))->toContain(route('pme.proformas.pdf', $p->public_code));
});

test('le template proforma suit le format demandé (Bonjour sans civilité, engagement budgétaire)', function () {
    ['user' => $user, 'proforma' => $p, 'company' => $company] = setupProformaShow(ProformaStatus::Sent);
    $company->update([
        'name' => 'Rassoul Electronique Services',
        'sender_name' => 'Moussa Diop',
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $p->fresh()])
        ->call('openSendModal');

    $message = $component->get('sendMessage');

    expect($message)
        ->toStartWith('Bonjour,')
        ->not->toContain('M.')
        ->toContain('Conformément à notre échange')
        ->toContain('facture proforma')
        ->toContain($p->reference)
        ->toContain('TTC')
        ->toContain('valable jusqu\'au')
        ->toContain(route('pme.proformas.pdf', $p->public_code))
        ->toContain('engagement budgétaire')
        ->toEndWith("Cordialement,\nMoussa Diop\nRassoul Electronique Services");
});

test('confirmSend WhatsApp construit wa.me/<international> à partir du local + pays', function () {
    ['user' => $user, 'proforma' => $p, 'client' => $client] = setupProformaShow(ProformaStatus::Sent);
    $client->update(['phone' => '+221771234567']);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $p->fresh()])
        ->call('openSendModal')
        ->call('confirmSend');

    $component->assertDispatched('open-external-url', function ($name, $params) {
        return str_starts_with($params['url'], 'https://wa.me/221771234567?text=');
    });
});

test('confirmSend WhatsApp normalise un numéro saisi sans préfixe (SN)', function () {
    ['user' => $user, 'proforma' => $p] = setupProformaShow(ProformaStatus::Sent);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $p])
        ->call('openSendModal')
        ->set('sendChannel', 'whatsapp')
        ->set('sendCountry', 'SN')
        ->set('sendRecipient', '771234567')
        ->call('confirmSend');

    // AuthService::normalizePhone('771234567', 'SN') = '+221771234567' → digits 221771234567
    $component->assertDispatched('open-external-url', function ($name, $params) {
        return str_starts_with($params['url'], 'https://wa.me/221771234567?text=');
    });
});

test('confirmSend en Email dispatch un open-external-url avec mailto:client@...', function () {
    ['user' => $user, 'proforma' => $p, 'client' => $client] = setupProformaShow(ProformaStatus::Sent);
    $client->update(['email' => 'contact@client.sn']);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $p->fresh()])
        ->call('openSendModal')
        ->set('sendChannel', 'email')
        ->set('sendRecipient', 'contact@client.sn')
        ->call('confirmSend');

    $component->assertDispatched('open-external-url', function ($name, $params) {
        return str_starts_with($params['url'], 'mailto:contact@client.sn?subject=');
    });
});

test('confirmSend sur une proforma Draft la passe automatiquement en Sent + toast', function () {
    ['user' => $user, 'proforma' => $p, 'client' => $client] = setupProformaShow(ProformaStatus::Draft);
    $client->update(['phone' => '+221771234567']);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $p->fresh()])
        ->call('openSendModal')
        ->call('confirmSend')
        ->assertHasNoErrors()
        ->assertDispatched('open-external-url')
        ->assertDispatched('toast', function ($name, $params) {
            return ($params['type'] ?? null) === 'success'
                && str_contains((string) ($params['title'] ?? ''), 'envoyée');
        });

    expect($p->fresh()->status)->toBe(ProformaStatus::Sent);
});

test('confirmSend sur une proforma déjà Sent ne ré-affiche pas le toast de bascule', function () {
    ['user' => $user, 'proforma' => $p, 'client' => $client] = setupProformaShow(ProformaStatus::Sent);
    $client->update(['phone' => '+221771234567']);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $p->fresh()])
        ->call('openSendModal')
        ->call('confirmSend');

    $component->assertDispatched('open-external-url');

    $events = collect($component->effects['dispatches'] ?? []);
    $statusToast = $events->first(fn ($e) => ($e['name'] ?? null) === 'toast' && str_contains((string) ($e['params']['title'] ?? ''), 'envoyée'));
    expect($statusToast)->toBeNull();
});

test('confirmSend valide que le destinataire est rempli', function () {
    ['user' => $user, 'proforma' => $p] = setupProformaShow(ProformaStatus::Sent);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $p])
        ->call('openSendModal')
        ->set('sendRecipient', '')
        ->call('confirmSend')
        ->assertHasErrors(['sendRecipient']);
});

test('confirmSend valide que l\'email est valide quand canal=email', function () {
    ['user' => $user, 'proforma' => $p] = setupProformaShow(ProformaStatus::Sent);

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.show', ['proforma' => $p])
        ->call('openSendModal')
        ->set('sendChannel', 'email')
        ->set('sendRecipient', 'pas-un-email')
        ->call('confirmSend')
        ->assertHasErrors(['sendRecipient']);
});
