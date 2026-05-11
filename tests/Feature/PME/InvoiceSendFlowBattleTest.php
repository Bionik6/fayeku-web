<?php

use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\PaymentMethod;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Models\PME\Payment;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Battle test du flow d'envoi de facture — couvre la mise en forme du message
 * (gras sur les infos capitales), les transitions de statut, le rendu du bouton
 * d'envoi WhatsApp/Email, et les cas limites (sans client, sans téléphone,
 * facture déjà émise, etc.).
 *
 * @return array{user: User, company: Company}
 */
function battleSme(): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme', 'name' => 'Acme SARL', 'sender_name' => 'Aïssatou']);
    $company->users()->attach($user->id, ['role' => 'owner']);

    return ['user' => $user, 'company' => $company];
}

function battleInvoice(Company $company, array $overrides = []): Invoice
{
    $client = Client::factory()->create([
        'company_id' => $company->id,
        'name' => 'Sonatel SA',
        'phone' => '+221770000001',
        'email' => 'compta@sonatel.sn',
    ]);

    return Invoice::unguarded(fn () => Invoice::create(array_merge([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'FYK-FAC-BAT001',
        'currency' => 'XOF',
        'status' => InvoiceStatus::Draft->value,
        'issued_at' => now()->subDay(),
        'due_at' => now()->addDays(15),
        'subtotal' => 1_000_000,
        'tax_amount' => 180_000,
        'total' => 1_180_000,
        'amount_paid' => 0,
    ], $overrides)));
}

// ─── Mise en forme du message ────────────────────────────────────────────────

it('met la référence facture, le montant et l\'échéance en gras dans le message', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $invoice = battleInvoice($company);

    $msg = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->get('sendMessage');

    expect($msg)
        ->toContain('*FYK-FAC-BAT001*')
        ->toContain('*1 180 000 FCFA*');

    // L'échéance est entourée d'astérisques (format de date variable selon locale)
    expect($msg)->toMatch('/Échéance de paiement : \*[^*]+\*\./');
});

it('avec acompte, met l\'acompte et le reste à payer en gras', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $invoice = battleInvoice($company, [
        'amount_paid' => 300_000,
        'deposit_amount' => 300_000,
    ]);
    Payment::query()->create([
        'invoice_id' => $invoice->id,
        'amount' => 300_000,
        'is_deposit' => true,
        'paid_at' => now()->subDay(),
        'method' => PaymentMethod::Cash,
    ]);

    $msg = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->get('sendMessage');

    expect($msg)
        ->toContain('Acompte déjà versé : *300 000 FCFA*')
        ->toContain('Reste à payer : *880 000 FCFA*')
        ->toContain("d'un montant total de *1 180 000 FCFA* TTC");
});

it('le message reste plain text — pas de balises HTML, juste les astérisques markdown', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $invoice = battleInvoice($company);

    $msg = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->get('sendMessage');

    expect($msg)
        ->not->toContain('<b>')
        ->not->toContain('<strong>')
        ->not->toContain('<br');
});

// ─── Canal & URL externe ─────────────────────────────────────────────────────

it('par défaut, ouvre la modale en canal WhatsApp quand le client a un téléphone', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $invoice = battleInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->assertSet('sendChannel', 'whatsapp')
        ->assertSet('sendRecipient', '770000001')
        ->assertSet('sendCountry', 'SN');
});

it('bascule en canal Email si le client n\'a pas de téléphone', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $client = Client::factory()->create([
        'company_id' => $company->id,
        'phone' => null,
        'email' => 'compta@client.sn',
    ]);
    $invoice = battleInvoice($company, ['client_id' => $client->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->assertSet('sendChannel', 'email')
        ->assertSet('sendRecipient', 'compta@client.sn');
});

it('la sélection email rebuild le destinataire et l\'URL mailto', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $invoice = battleInvoice($company);

    // Setting sendChannel via Livewire déclenche automatiquement updatedSendChannel.
    $component = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->set('sendChannel', 'email');

    expect($component->get('sendRecipient'))->toBe('compta@sonatel.sn');
    $component->assertSeeHtml('mailto:compta@sonatel.sn?subject=');
});

it('le bouton Envoyer rend une URL wa.me/<international> incluant le préfixe pays', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $invoice = battleInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->assertSeeHtml('https://wa.me/221770000001');
});

it('le bouton expose data-can-send=0 quand le destinataire est vide', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $client = Client::factory()->create([
        'company_id' => $company->id,
        'phone' => null,
        'email' => null,
    ]);
    $invoice = battleInvoice($company, ['client_id' => $client->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->assertSeeHtml('data-can-send="0"');
});

it('la modale d\'envoi expose data-prefix sur l\'input pays pour le rebuild côté client', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $invoice = battleInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->assertSeeHtml(':data-prefix="selected?.prefix"');
});

// ─── Transitions de statut ───────────────────────────────────────────────────

it('confirmSend depuis Draft sans acompte → Sent', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $invoice = battleInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->call('confirmSend');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
});

it('confirmSend depuis Draft avec acompte couvrant la totalité → Paid', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $invoice = battleInvoice($company, [
        'subtotal' => 100_000,
        'tax_amount' => 0,
        'total' => 100_000,
        'amount_paid' => 100_000,
        'deposit_amount' => 100_000,
    ]);
    Payment::query()->create([
        'invoice_id' => $invoice->id,
        'amount' => 100_000,
        'is_deposit' => true,
        'paid_at' => now(),
        'method' => PaymentMethod::Cash,
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->call('confirmSend');

    $fresh = $invoice->fresh();
    expect($fresh->status)->toBe(InvoiceStatus::Paid);
    expect($fresh->paid_at)->not->toBeNull();
});

it('confirmSend depuis Draft avec acompte partiel → PartiallyPaid', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $invoice = battleInvoice($company, [
        'subtotal' => 100_000,
        'tax_amount' => 0,
        'total' => 100_000,
        'amount_paid' => 30_000,
        'deposit_amount' => 30_000,
    ]);
    Payment::query()->create([
        'invoice_id' => $invoice->id,
        'amount' => 30_000,
        'is_deposit' => true,
        'paid_at' => now(),
        'method' => PaymentMethod::Cash,
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->call('confirmSend');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::PartiallyPaid);
});

it('confirmSend sur une facture déjà Sent ne re-bascule pas et ne dispatche pas de toast', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $invoice = battleInvoice($company, [
        'status' => InvoiceStatus::Sent->value,
        'sent_at' => now()->subDay(),
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->call('confirmSend');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);

    $events = collect($component->effects['dispatches'] ?? []);
    $statusToast = $events->first(fn ($e) => ($e['name'] ?? null) === 'toast'
        && str_contains((string) ($e['params']['title'] ?? ''), 'envoyée'));
    expect($statusToast)->toBeNull();
});

// ─── Modale : ouverture & fermeture ──────────────────────────────────────────

it('openSendModal ne fait rien sur une facture Paid', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $invoice = battleInvoice($company, [
        'status' => InvoiceStatus::Paid->value,
        'paid_at' => now(),
        'amount_paid' => 1_180_000,
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->assertSet('showSendModal', false);
});

it('openSendModal ne fait rien sur une facture Cancelled', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $invoice = battleInvoice($company, [
        'status' => InvoiceStatus::Cancelled->value,
        'cancelled_at' => now(),
        'cancellation_reason' => 'Doublon',
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->assertSet('showSendModal', false);
});

it('closeSendModal ferme la modale sans modifier le statut', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $invoice = battleInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->assertSet('showSendModal', true)
        ->call('closeSendModal')
        ->assertSet('showSendModal', false);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Draft);
});

// ─── Bouton principal vs entrée query string ────────────────────────────────

it('le bouton principal d\'une facture Draft est Envoyer la facture et ouvre la modale d\'envoi', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $invoice = battleInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->assertSeeText('Envoyer la facture')
        ->assertSeeHtml('data-action="primary-send"')
        ->assertSeeHtml('wire:click="openSendModal"');
});

it('?send=1 dans l\'URL auto-ouvre la modale d\'envoi sur un Draft', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $invoice = battleInvoice($company);

    Livewire::actingAs($user)
        ->withQueryParams(['send' => '1'])
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->assertSet('showSendModal', true);
});

// ─── Le message respecte les contraintes de format ───────────────────────────

it('le message ne contient pas d\'astérisques résiduels sans paire de fermeture', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $invoice = battleInvoice($company, [
        'amount_paid' => 100_000,
        'deposit_amount' => 100_000,
    ]);
    Payment::query()->create([
        'invoice_id' => $invoice->id,
        'amount' => 100_000,
        'is_deposit' => true,
        'paid_at' => now(),
        'method' => PaymentMethod::Cash,
    ]);

    $msg = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->get('sendMessage');

    // Le nombre d'astérisques doit être pair (chaque ouvrant a son fermant).
    expect(substr_count($msg, '*') % 2)->toBe(0);
});

it('le message contient le lien public PDF', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $invoice = battleInvoice($company);

    $msg = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->get('sendMessage');

    expect($msg)->toContain(route('pme.invoices.pdf', $invoice->public_code));
});

// ─── Canal Email — éditable + flow d'envoi natif ────────────────────────────

it('l\'input email utilise wire:model.live.debounce pour rester en phase avec sendRecipient', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $invoice = battleInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->set('sendChannel', 'email')
        ->assertSeeHtml('wire:model.live.debounce.300ms="sendRecipient"');
});

it('l\'input email expose name="sendRecipient" pour que le click handler retrouve sa valeur DOM', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $invoice = battleInvoice($company);

    $html = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->set('sendChannel', 'email')
        ->html();

    // Sans name="sendRecipient", document.querySelector côté JS retournerait null
    // et le recipient resterait vide → bouton no-op (bug constaté en prod).
    expect($html)->toMatch('/<input[^>]+name="sendRecipient"[^>]+type="email"|<input[^>]+type="email"[^>]+name="sendRecipient"/');
});

it('changer le mail dans la modale met à jour sendRecipient et la fallback URL mailto', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $invoice = battleInvoice($company);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->set('sendChannel', 'email')
        ->set('sendRecipient', 'dsi@autre-client.sn');

    expect($component->get('sendRecipient'))->toBe('dsi@autre-client.sn');
    $component
        ->assertSeeHtml('mailto:dsi@autre-client.sn?subject=')
        ->assertSeeHtml('data-can-send="1"');
});

it('le click handler email construit mailto:<recipient>?subject=...&body=... et invoque window.location.href', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $invoice = battleInvoice($company);

    $html = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->set('sendChannel', 'email')
        ->html();

    // Branche email : on construit mailto: et on l'invoque via window.location.href,
    // pas window.open(_blank) qui crée un onglet fantôme dans Chrome.
    expect($html)
        ->toContain("'mailto:'")
        ->toContain('window.location.href = url');
});

it('le click handler WhatsApp utilise window.open(_blank) — pas le anchor', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $invoice = battleInvoice($company);

    $html = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->html();

    expect($html)
        ->toContain("url = 'https://wa.me/'")
        ->toContain("window.open(url, '_blank', 'noopener,noreferrer')");
});

it('confirmSend en canal Email bascule un Draft en Sent (même flow que WhatsApp)', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $invoice = battleInvoice($company);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->set('sendChannel', 'email')
        ->call('confirmSend');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
});

it('le sujet du mailto est "Facture <ref> — <Entreprise>" URL-encodé', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $invoice = battleInvoice($company);
    $expectedSubject = "Facture {$invoice->reference} — Acme SARL";

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->set('sendChannel', 'email')
        ->assertSeeHtml('data-send-subject="'.rawurlencode($expectedSubject).'"');
});

it('le body du mailto fallback inclut le message email URL-encodé', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $invoice = battleInvoice($company);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->set('sendChannel', 'email');

    // En canal email, le message commence par "Vous trouverez la facture" (template plain text).
    $encodedSnippet = rawurlencode('Vous trouverez la facture');
    $component->assertSeeHtml($encodedSnippet);
});

it('quand le client n\'a ni téléphone ni email, le bouton Envoyer reste disabled (data-can-send=0)', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $client = Client::factory()->create([
        'company_id' => $company->id,
        'phone' => null,
        'email' => null,
    ]);
    $invoice = battleInvoice($company, ['client_id' => $client->id]);

    Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->set('sendChannel', 'email')
        ->assertSet('sendRecipient', '')
        ->assertSeeHtml('data-can-send="0"');
});

it('basculer du canal WhatsApp à Email puis modifier l\'email reconstruit l\'URL mailto avec la nouvelle valeur', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $invoice = battleInvoice($company);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->assertSet('sendChannel', 'whatsapp')
        ->set('sendChannel', 'email')
        ->assertSet('sendRecipient', 'compta@sonatel.sn')
        ->set('sendRecipient', 'autre@destinataire.sn');

    $component->assertSeeHtml('mailto:autre@destinataire.sn?subject=');
});

// ─── Template du message email ────────────────────────────────────────────

it('le message email est en plain text — pas d\'astérisques markdown', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $invoice = battleInvoice($company);

    $message = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->set('sendChannel', 'email')
        ->get('sendMessage');

    expect($message)->not->toContain('*');
});

it('le message email contient les formulations attendues (Vous trouverez, en ligne, répondre à cet email)', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $invoice = battleInvoice($company);

    $message = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->set('sendChannel', 'email')
        ->get('sendMessage');

    expect($message)
        ->toStartWith('Bonjour,')
        ->toContain("Vous trouverez la facture {$invoice->reference}")
        ->toContain("d'un montant de 1 180 000 FCFA")
        ->toContain('à régler avant le')
        ->toContain('Consulter la facture en ligne :')
        ->toContain(route('pme.invoices.pdf', $invoice->public_code))
        ->toContain('Moyens de paiement acceptés :')
        ->toContain("Pour toute question, n'hésitez pas à répondre directement à cet email.")
        ->toEndWith("Cordialement,\nAïssatou\nAcme SARL");
});

it('le message email avec acompte mentionne l\'acompte versé et le reste à payer en plain text', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $invoice = battleInvoice($company, [
        'amount_paid' => 300_000,
        'deposit_amount' => 300_000,
    ]);
    Payment::query()->create([
        'invoice_id' => $invoice->id,
        'amount' => 300_000,
        'is_deposit' => true,
        'paid_at' => now()->subDay(),
        'method' => PaymentMethod::Cash,
    ]);

    $message = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->set('sendChannel', 'email')
        ->get('sendMessage');

    expect($message)
        ->toContain("d'un montant total de 1 180 000 FCFA TTC")
        ->toContain('Un acompte de 300 000 FCFA a déjà été versé, reste à payer 880 000 FCFA')
        ->not->toContain('*');
});

it('basculer WhatsApp ↔ Email régénère le message dans le bon format à chaque switch', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $invoice = battleInvoice($company);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal');

    // WhatsApp par défaut : contient les astérisques
    expect($component->get('sendMessage'))->toContain('*'.$invoice->reference.'*');

    // Bascule en email : plain text, formulation différente
    $component->set('sendChannel', 'email');
    expect($component->get('sendMessage'))
        ->toContain("Vous trouverez la facture {$invoice->reference}")
        ->not->toContain('*');

    // Retour en WhatsApp : les astérisques reviennent
    $component->set('sendChannel', 'whatsapp');
    expect($component->get('sendMessage'))
        ->toContain('*'.$invoice->reference.'*')
        ->toContain('Veuillez trouver notre facture');
});

it('sendEmailSubject est exposé sur le show et utilisé par la modale', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $invoice = battleInvoice($company);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice]);

    expect($component->get('sendEmailSubject'))->toBe("Facture {$invoice->reference} — Acme SARL");
});

// ─── Le message se termine par la signature ────────────────────────────────

it('le message se termine par la signature de l\'entreprise', function () {
    ['user' => $user, 'company' => $company] = battleSme();
    $invoice = battleInvoice($company);

    $msg = Livewire::actingAs($user)
        ->test('pages::pme.invoices.show', ['invoice' => $invoice])
        ->call('openSendModal')
        ->get('sendMessage');

    expect($msg)->toEndWith("Cordialement,\nAïssatou\nAcme SARL");
});
