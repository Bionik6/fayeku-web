<?php

use App\Enums\PME\InvoiceStatus;
use App\Interfaces\Shared\WhatsAppProviderInterface;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Services\PME\WhatsAppReminderService;
use App\Services\Shared\WhatsAppTemplateCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $companyOverrides
 * @return array{company: Company, invoice: Invoice}
 */
function bootstrapWhatsAppInvoice(string $reference = 'FAC-WA-01', array $companyOverrides = []): array
{
    $company = Company::factory()->create(array_merge([
        'type' => 'sme',
        'name' => 'Sow BTP',
        'sender_name' => 'Ibrahima Ciss',
        'sender_role' => 'Manager',
    ], $companyOverrides));

    $client = Client::factory()->create([
        'company_id' => $company->id,
        'name' => 'Dakar Pharma',
        'phone' => '+221771112233',
    ]);

    $invoice = Invoice::unguarded(fn () => Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => $reference,
        'status' => InvoiceStatus::Overdue->value,
        'issued_at' => now()->subDays(20),
        'due_at' => now()->subDays(3),
        'subtotal' => 250_000,
        'tax_amount' => 0,
        'total' => 250_000,
        'amount_paid' => 0,
        'currency' => 'XOF',
    ]));

    return compact('company', 'invoice');
}

test('WhatsAppReminderService resout le template auto a partir du dayOffset', function () {
    ['invoice' => $invoice] = bootstrapWhatsAppInvoice('FAC-WA-P15');

    $mock = $this->mock(WhatsAppProviderInterface::class, function (MockInterface $m) use ($invoice) {
        $m->shouldReceive('sendTemplate')
            ->once()
            ->withArgs(function (string $phone, string $template, array $bodyParameters, ?string $urlButton) use ($invoice) {
                return $phone === '+221771112233'
                    && $template === 'fayeku_reminder_invoice_due_auto_p15'
                    && $bodyParameters['client_name'] === 'Dakar Pharma'
                    && $bodyParameters['company_name'] === 'Sow BTP'
                    && $bodyParameters['invoice_number'] === 'FAC-WA-P15'
                    && str_contains($bodyParameters['invoice_amount'], '250 000')
                    && $bodyParameters['sender_signature'] === 'Ibrahima Ciss, Manager Sow BTP'
                    && $urlButton === $invoice->public_code.'/pdf';
            })
            ->andReturnTrue();
        $m->shouldNotReceive('send');
    });

    $service = new WhatsAppReminderService($mock, app(WhatsAppTemplateCatalog::class));

    $reminder = $service->send($invoice, null, 15);

    expect($reminder->day_offset)->toBe(15)
        ->and($reminder->message_body)->toContain('Dakar Pharma')
        ->and($reminder->message_body)->toContain('FAC-WA-P15')
        ->and($reminder->message_body)->toContain('Ibrahima Ciss, Manager Sow BTP');
});

test('WhatsAppReminderService utilise templateKey explicite pour les tons manuels', function () {
    ['invoice' => $invoice] = bootstrapWhatsAppInvoice('FAC-WA-MAN');

    $mock = $this->mock(WhatsAppProviderInterface::class, function (MockInterface $m) {
        $m->shouldReceive('sendTemplate')
            ->once()
            ->withArgs(fn (string $phone, string $template) => $template === 'fayeku_reminder_invoice_due_manual_urgent')
            ->andReturnTrue();
        $m->shouldNotReceive('send');
    });

    $service = new WhatsAppReminderService($mock, app(WhatsAppTemplateCatalog::class));

    $reminder = $service->send($invoice, null, null, 'reminder_manual_urgent');

    expect($reminder->message_body)->toContain('URGENT');
});

test('WhatsAppReminderService fallback sur send texte quand aucun template ne matche', function () {
    ['invoice' => $invoice] = bootstrapWhatsAppInvoice('FAC-WA-02');

    $mock = $this->mock(WhatsAppProviderInterface::class, function (MockInterface $m) {
        $m->shouldReceive('send')
            ->once()
            ->with('+221771112233', 'Corps rendu')
            ->andReturnTrue();
        $m->shouldNotReceive('sendTemplate');
    });

    $service = new WhatsAppReminderService($mock, app(WhatsAppTemplateCatalog::class));
    $service->send($invoice, 'Corps rendu', 99);
});

test('WhatsAppReminderService propage une erreur quand le provider retourne false', function () {
    ['invoice' => $invoice] = bootstrapWhatsAppInvoice('FAC-WA-03');

    $mock = $this->mock(WhatsAppProviderInterface::class, function (MockInterface $m) {
        $m->shouldReceive('send')->andReturnFalse();
    });

    $service = new WhatsAppReminderService($mock, app(WhatsAppTemplateCatalog::class));

    expect(fn () => $service->send($invoice, 'Corps rendu'))
        ->toThrow(RuntimeException::class);
});
