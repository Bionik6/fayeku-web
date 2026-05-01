<?php

use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\PaymentMethod;
use App\Enums\PME\ProposalDocumentStatus;
use App\Enums\PME\ProposalDocumentType;
use App\Interfaces\Shared\WhatsAppProviderInterface;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Models\PME\Payment;
use App\Models\PME\ProposalDocument;
use App\Models\Shared\Notification;
use App\Services\PME\WhatsAppNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

function bootstrapNotifContext(array $companyOverrides = [], array $clientOverrides = []): array
{
    $company = Company::factory()->create(array_merge([
        'type' => 'sme',
        'name' => 'Khalil Softwares',
        'sender_name' => 'Moussa Diop',
        'sender_role' => 'Directeur commercial',
        'plan' => 'essentiel',
    ], $companyOverrides));

    $client = Client::factory()->create(array_merge([
        'company_id' => $company->id,
        'name' => 'Dakar Pharma',
        'phone' => '+221771112233',
        'email' => null,
    ], $clientOverrides));

    return compact('company', 'client');
}

test('sendInvoiceCreated envoie notification_invoice_sent_with_due_date si due_at futur', function () {
    ['company' => $company, 'client' => $client] = bootstrapNotifContext();

    $invoice = Invoice::unguarded(fn () => Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'FAC-NOTIF-01',
        'status' => InvoiceStatus::Sent->value,
        'issued_at' => now(),
        'due_at' => now()->addDays(15),
        'subtotal' => 250_000,
        'tax_amount' => 0,
        'total' => 250_000,
        'amount_paid' => 0,
        'currency' => 'XOF',
    ]));

    $mock = $this->mock(WhatsAppProviderInterface::class, function (MockInterface $m) {
        $m->shouldReceive('sendTemplate')
            ->once()
            ->withArgs(function (string $phone, string $template, array $vars) {
                return $template === 'fayeku_notification_invoice_sent_with_due_date'
                    && $vars['client_name'] === 'Dakar Pharma'
                    && $vars['invoice_number'] === 'FAC-NOTIF-01';
            })
            ->andReturnTrue();
    });

    $notif = app(WhatsAppNotificationService::class)->sendInvoiceCreated($invoice, $company);

    expect($notif)->not->toBeNull()
        ->and($notif->template_key)->toBe('notification_invoice_sent_with_due_date')
        ->and($notif->channel)->toBe('whatsapp');
});

test('sendInvoiceCreated envoie notification_invoice_sent si due_at est aujourd hui', function () {
    ['company' => $company, 'client' => $client] = bootstrapNotifContext();

    $invoice = Invoice::unguarded(fn () => Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'FAC-TODAY',
        'status' => InvoiceStatus::Sent->value,
        'issued_at' => now(),
        'due_at' => now(),
        'subtotal' => 100_000,
        'tax_amount' => 0,
        'total' => 100_000,
        'amount_paid' => 0,
        'currency' => 'XOF',
    ]));

    $mock = $this->mock(WhatsAppProviderInterface::class, function (MockInterface $m) {
        $m->shouldReceive('sendTemplate')
            ->once()
            ->withArgs(fn ($phone, $template) => $template === 'fayeku_notification_invoice_sent')
            ->andReturnTrue();
    });

    app(WhatsAppNotificationService::class)->sendInvoiceCreated($invoice, $company);
});

test('sendInvoicePaidFull envoie paid_full avec payment_date et amount_paid', function () {
    ['company' => $company, 'client' => $client] = bootstrapNotifContext();

    $invoice = Invoice::unguarded(fn () => Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'FAC-PAID',
        'status' => InvoiceStatus::Paid->value,
        'issued_at' => now()->subDays(10),
        'due_at' => now()->subDays(5),
        'total' => 250_000,
        'amount_paid' => 250_000,
        'subtotal' => 250_000,
        'tax_amount' => 0,
        'currency' => 'XOF',
    ]));

    $payment = Payment::unguarded(fn () => Payment::create([
        'invoice_id' => $invoice->id,
        'amount' => 250_000,
        'paid_at' => now(),
        'method' => PaymentMethod::Cash->value,
    ]));

    $this->mock(WhatsAppProviderInterface::class, function (MockInterface $m) {
        $m->shouldReceive('sendTemplate')
            ->once()
            ->withArgs(function ($phone, $template, $vars) {
                return $template === 'fayeku_notification_invoice_paid_full'
                    && str_contains($vars['amount_paid'], '250 000')
                    && ! empty($vars['payment_date']);
            })
            ->andReturnTrue();
    });

    $notif = app(WhatsAppNotificationService::class)->sendInvoicePaidFull($invoice, $payment, $company);

    expect($notif->template_key)->toBe('notification_invoice_paid_full')
        ->and($notif->meta)->toHaveKey('payment_id', $payment->id);
});

test('sendInvoicePartiallyPaid envoie partially_paid avec amount_remaining', function () {
    ['company' => $company, 'client' => $client] = bootstrapNotifContext();

    $invoice = Invoice::unguarded(fn () => Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'FAC-PART',
        'status' => InvoiceStatus::PartiallyPaid->value,
        'issued_at' => now()->subDays(10),
        'due_at' => now()->addDays(5),
        'total' => 300_000,
        'amount_paid' => 100_000,
        'subtotal' => 300_000,
        'tax_amount' => 0,
        'currency' => 'XOF',
    ]));

    $payment = Payment::unguarded(fn () => Payment::create([
        'invoice_id' => $invoice->id,
        'amount' => 100_000,
        'paid_at' => now(),
        'method' => PaymentMethod::Cash->value,
    ]));

    $this->mock(WhatsAppProviderInterface::class, function (MockInterface $m) {
        $m->shouldReceive('sendTemplate')
            ->once()
            ->withArgs(fn ($phone, $template, $vars) => $template === 'fayeku_notification_invoice_partially_paid'
                && str_contains($vars['amount_paid'], '100 000')
                && str_contains($vars['amount_remaining'], '200 000'))
            ->andReturnTrue();
    });

    app(WhatsAppNotificationService::class)->sendInvoicePartiallyPaid($invoice, $payment, $company);
});

test('sendProposalSent envoie notification_quote_sent avec expiry_date', function () {
    ['company' => $company, 'client' => $client] = bootstrapNotifContext();

    $quote = ProposalDocument::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'type' => ProposalDocumentType::Quote,
        'reference' => 'DEV-001',
        'status' => ProposalDocumentStatus::Sent,
        'issued_at' => now(),
        'valid_until' => now()->addDays(30),
        'subtotal' => 500_000,
        'tax_amount' => 0,
        'total' => 500_000,
        'currency' => 'XOF',
    ]);

    $this->mock(WhatsAppProviderInterface::class, function (MockInterface $m) {
        $m->shouldReceive('sendTemplate')
            ->once()
            ->withArgs(fn ($phone, $template, $vars) => $template === 'fayeku_notification_quote_sent'
                && $vars['quote_number'] === 'DEV-001'
                && str_contains($vars['quote_amount'], '500 000')
                && ! empty($vars['expiry_date']))
            ->andReturnTrue();
    });

    $notif = app(WhatsAppNotificationService::class)->sendProposalSent($quote, $company);

    expect($notif->notifiable_type)->toBe(ProposalDocument::class)
        ->and($notif->notifiable_id)->toBe($quote->id);
});

test('sendInvoiceCreated ignore si client sans contact', function () {
    ['company' => $company, 'client' => $client] = bootstrapNotifContext(
        clientOverrides: ['phone' => null, 'email' => null],
    );

    $invoice = Invoice::unguarded(fn () => Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'FAC-NO-CONTACT',
        'status' => InvoiceStatus::Sent->value,
        'issued_at' => now(),
        'due_at' => now()->addDays(10),
        'total' => 100_000,
        'amount_paid' => 0,
        'subtotal' => 100_000,
        'tax_amount' => 0,
        'currency' => 'XOF',
    ]));

    $this->mock(WhatsAppProviderInterface::class, function (MockInterface $m) {
        $m->shouldNotReceive('sendTemplate');
    });

    $notif = app(WhatsAppNotificationService::class)->sendInvoiceCreated($invoice, $company);

    expect($notif)->toBeNull()
        ->and(Notification::count())->toBe(0);
});
