<?php

use App\Enums\PME\InvoiceStatus;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Services\Shared\MetaTemplateFetcher;
use App\Services\Shared\WhatsAppTemplateCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

function makeCatalogWithFetcher(?string $metaBody): WhatsAppTemplateCatalog
{
    $fetcher = Mockery::mock(MetaTemplateFetcher::class);
    $fetcher->shouldReceive('getBody')->andReturn($metaBody);

    return new WhatsAppTemplateCatalog($fetcher);
}

// ─── nameFor ────────────────────────────────────────────────────────────────

test('nameFor retourne le nom Meta configuré', function () {
    $catalog = makeCatalogWithFetcher(null);

    expect($catalog->nameFor('reminder_manual_cordial'))
        ->toBe('fayeku_reminder_invoice_due_manual_cordial');
});

test('nameFor throw pour une clé inconnue', function () {
    $catalog = makeCatalogWithFetcher(null);

    expect(fn () => $catalog->nameFor('ghost_template'))
        ->toThrow(InvalidArgumentException::class, 'Template WhatsApp inconnu');
});

// ─── render : Meta > local ──────────────────────────────────────────────────

test('render utilise le body Meta avec substitution {{var}}', function () {
    $catalog = makeCatalogWithFetcher('Bonjour {{client_name}}, facture {{invoice_number}} !');

    $rendered = $catalog->render('reminder_manual_cordial', [
        'client_name' => 'Dakar Pharma',
        'invoice_number' => 'FAC-001',
    ]);

    expect($rendered)->toBe('Bonjour Dakar Pharma, facture FAC-001 !');
});

test('render fallback sur le body local avec substitution {var} quand Meta renvoie null', function () {
    $catalog = makeCatalogWithFetcher(null);

    $rendered = $catalog->render('reminder_manual_cordial', [
        'client_name' => 'Dakar Pharma',
        'company_name' => 'Sow BTP',
        'invoice_number' => 'FAC-LOCAL',
        'invoice_amount' => '100 000 FCFA',
        'due_date' => '15 avril 2026',
        'sender_signature' => "L'équipe Sow BTP",
    ]);

    expect($rendered)
        ->toContain('Dakar Pharma')
        ->toContain('FAC-LOCAL')
        ->toContain('Sow BTP');
});

test('render fallback sur le body local aussi quand Meta renvoie une chaîne vide', function () {
    $catalog = makeCatalogWithFetcher('');

    $rendered = $catalog->render('reminder_manual_cordial', [
        'client_name' => 'Dakar',
        'company_name' => 'Sow',
        'invoice_number' => 'FAC-1',
        'invoice_amount' => '100',
        'due_date' => '01/01',
        'sender_signature' => 'Sow',
    ]);

    expect($rendered)->toContain('Dakar');
});

test('render throw si ni Meta ni fallback local ne fournissent un body', function () {
    $catalog = makeCatalogWithFetcher(null);

    config()->set('whatsapp-templates.broken_key', ['name' => 'meta_name']);

    expect(fn () => $catalog->render('broken_key', []))
        ->toThrow(InvalidArgumentException::class);
});

// ─── renderSubject ──────────────────────────────────────────────────────────

test('renderSubject substitue les variables {var} du sujet local', function () {
    $catalog = makeCatalogWithFetcher(null);

    $subject = $catalog->renderSubject('reminder_manual_cordial', [
        'invoice_number' => 'FAC-SUB',
    ]);

    expect($subject)->toBe('Rappel — facture FAC-SUB');
});

test('renderSubject renvoie un sujet générique si non configuré', function () {
    $catalog = makeCatalogWithFetcher(null);

    config()->set('whatsapp-templates.no_subject_key', [
        'name' => 'meta_name',
        'body' => 'body',
    ]);

    expect($catalog->renderSubject('no_subject_key', []))
        ->toBe('Fayeku — notification');
});

// ─── offset → clé auto ─────────────────────────────────────────────────────

test('autoReminderKeyForOffset mappe chaque offset connu', function () {
    $catalog = makeCatalogWithFetcher(null);

    expect($catalog->autoReminderKeyForOffset(-3))->toBe('reminder_auto_m3')
        ->and($catalog->autoReminderKeyForOffset(0))->toBe('reminder_auto_0')
        ->and($catalog->autoReminderKeyForOffset(3))->toBe('reminder_auto_p3')
        ->and($catalog->autoReminderKeyForOffset(7))->toBe('reminder_auto_p7')
        ->and($catalog->autoReminderKeyForOffset(15))->toBe('reminder_auto_p15')
        ->and($catalog->autoReminderKeyForOffset(30))->toBe('reminder_auto_p30')
        ->and($catalog->autoReminderKeyForOffset(60))->toBe('reminder_auto_p60')
        ->and($catalog->autoReminderKeyForOffset(42))->toBeNull();
});

// ─── tone → clé manuel ─────────────────────────────────────────────────────

test('manualReminderKeyForTone mappe les 3 tons (cordial par défaut)', function () {
    $catalog = makeCatalogWithFetcher(null);

    expect($catalog->manualReminderKeyForTone('cordial'))->toBe('reminder_manual_cordial')
        ->and($catalog->manualReminderKeyForTone('ferme'))->toBe('reminder_manual_firm')
        ->and($catalog->manualReminderKeyForTone('urgent'))->toBe('reminder_manual_urgent')
        ->and($catalog->manualReminderKeyForTone('inconnu'))->toBe('reminder_manual_cordial');
});

// ─── invoiceVariables ──────────────────────────────────────────────────────

test('invoiceVariables expose les 7 clés attendues depuis l\'invoice', function () {
    $company = Company::factory()->create([
        'type' => 'sme',
        'name' => 'Khalil Softwares',
        'sender_name' => 'Moussa Diop',
        'sender_role' => 'Directeur commercial',
    ]);

    $client = Client::factory()->create([
        'company_id' => $company->id,
        'name' => 'Dakar Pharma',
    ]);

    $invoice = Invoice::unguarded(fn () => Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'FAC-VAR-01',
        'status' => InvoiceStatus::Sent->value,
        'issued_at' => now(),
        'due_at' => now()->addDays(15)->startOfDay(),
        'total' => 250_000,
        'amount_paid' => 0,
        'subtotal' => 250_000,
        'tax_amount' => 0,
        'currency' => 'XOF',
    ]));

    $catalog = makeCatalogWithFetcher(null);

    $vars = $catalog->invoiceVariables($invoice);

    expect($vars)
        ->toHaveKeys(['client_name', 'company_name', 'invoice_number', 'invoice_amount', 'invoice_due_date', 'due_date', 'sender_signature'])
        ->and($vars['client_name'])->toBe('Dakar Pharma')
        ->and($vars['company_name'])->toBe('Khalil Softwares')
        ->and($vars['invoice_number'])->toBe('FAC-VAR-01')
        ->and($vars['invoice_amount'])->toContain('250 000')
        ->and($vars['due_date'])->toBe($vars['invoice_due_date'])
        ->and($vars['sender_signature'])->toBe('Moussa Diop, Directeur commercial Khalil Softwares');
});

test('invoiceVariables fallback proprement sur les valeurs par défaut', function () {
    $company = Company::factory()->create([
        'type' => 'sme',
        'name' => 'Default Co',
        'sender_name' => null,
        'sender_role' => null,
    ]);

    $invoice = Invoice::unguarded(fn () => Invoice::create([
        'company_id' => $company->id,
        'client_id' => null,
        'reference' => null,
        'status' => InvoiceStatus::Draft->value,
        'issued_at' => now(),
        'due_at' => null,
        'total' => 0,
        'amount_paid' => 0,
        'subtotal' => 0,
        'tax_amount' => 0,
        'currency' => 'XOF',
    ]));

    $catalog = makeCatalogWithFetcher(null);

    $vars = $catalog->invoiceVariables($invoice);

    expect($vars['client_name'])->toBe('Cher client')
        ->and($vars['invoice_number'])->toBe('')
        ->and($vars['due_date'])->toBe('')
        ->and($vars['sender_signature'])->toBe("L'équipe Default Co");
});

// ─── renderManualReminder ──────────────────────────────────────────────────

test('renderManualReminder rend via le body Meta quand dispo', function () {
    $fetcher = $this->mock(MetaTemplateFetcher::class, function (MockInterface $m) {
        $m->shouldReceive('getBody')
            ->withArgs(fn (string $name) => $name === 'fayeku_reminder_invoice_due_manual_urgent')
            ->andReturn('URGENT {{client_name}} — {{invoice_number}}');
    });

    $catalog = new WhatsAppTemplateCatalog($fetcher);

    $company = Company::factory()->create(['type' => 'sme', 'name' => 'Co']);
    $client = Client::factory()->create(['company_id' => $company->id, 'name' => 'Acme']);
    $invoice = Invoice::unguarded(fn () => Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'FAC-URG',
        'status' => InvoiceStatus::Overdue->value,
        'issued_at' => now()->subDays(20),
        'due_at' => now()->subDays(5),
        'total' => 100_000,
        'amount_paid' => 0,
        'subtotal' => 100_000,
        'tax_amount' => 0,
        'currency' => 'XOF',
    ]));

    expect($catalog->renderManualReminder($invoice, $company, 'urgent'))
        ->toBe('URGENT Acme — FAC-URG');
});
