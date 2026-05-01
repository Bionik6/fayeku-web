<?php

use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\PaymentMethod;
use App\Enums\PME\ProposalDocumentStatus;
use App\Enums\PME\ProposalDocumentType;
use App\Interfaces\Shared\WhatsAppProviderInterface;
use App\Mail\Shared\NotificationMail;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Models\PME\Payment;
use App\Models\PME\ProposalDocument;
use App\Models\Shared\Notification;
use App\Services\PME\EmailReminderService;
use App\Services\PME\WhatsAppNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

function createEmailOnlyClient(Company $company, array $overrides = []): Client
{
    return Client::factory()->create(array_merge([
        'company_id' => $company->id,
        'name' => 'Dakar Pharma',
        'phone' => null,
        'email' => 'finance@dakar-pharma.test',
    ], $overrides));
}

function bootstrapEmailFallbackCompany(): Company
{
    return Company::factory()->create([
        'type' => 'sme',
        'name' => 'Khalil Softwares',
        'sender_name' => 'Moussa Diop',
        'sender_role' => 'Directeur commercial',
        'plan' => 'essentiel',
    ]);
}

// ─── WhatsAppNotificationService : fallback email ────────────────────────────

test('sendInvoiceCreated bascule sur email quand le client a uniquement un email', function () {
    Mail::fake();

    $company = bootstrapEmailFallbackCompany();
    $client = createEmailOnlyClient($company);

    $invoice = Invoice::unguarded(fn () => Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'FAC-EMAIL-01',
        'status' => InvoiceStatus::Sent->value,
        'issued_at' => now(),
        'due_at' => now()->addDays(15),
        'subtotal' => 250_000,
        'tax_amount' => 0,
        'total' => 250_000,
        'amount_paid' => 0,
        'currency' => 'XOF',
    ]));

    $this->mock(WhatsAppProviderInterface::class, function (MockInterface $m) {
        $m->shouldNotReceive('sendTemplate');
        $m->shouldNotReceive('send');
    });

    $notif = app(WhatsAppNotificationService::class)->sendInvoiceCreated($invoice, $company);

    expect($notif)->not->toBeNull()
        ->and($notif->channel)->toBe('email')
        ->and($notif->recipient_email)->toBe('finance@dakar-pharma.test')
        ->and($notif->recipient_phone)->toBeNull();

    Mail::assertQueued(NotificationMail::class);

    $queued = Mail::queued(NotificationMail::class)->first();
    expect($queued)->not->toBeNull()
        ->and($queued->subjectLine)->toContain('FAC-EMAIL-01')
        ->and($queued->body)->toContain('Dakar Pharma')
        ->and($queued->ctaUrl)->toContain('/f/')
        ->and($queued->ctaLabel)->toBe('Voir la facture');
});

test('sendProposalSent bascule sur email avec CTA vers le PDF devis', function () {
    Mail::fake();

    $company = bootstrapEmailFallbackCompany();
    $client = createEmailOnlyClient($company);

    $quote = ProposalDocument::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'type' => ProposalDocumentType::Quote,
        'reference' => 'DEV-EMAIL-01',
        'status' => ProposalDocumentStatus::Sent,
        'issued_at' => now(),
        'valid_until' => now()->addDays(30),
        'subtotal' => 500_000,
        'tax_amount' => 0,
        'total' => 500_000,
        'currency' => 'XOF',
    ]);

    $this->mock(WhatsAppProviderInterface::class, function (MockInterface $m) {
        $m->shouldNotReceive('sendTemplate');
    });

    $notif = app(WhatsAppNotificationService::class)->sendProposalSent($quote, $company);

    expect($notif->channel)->toBe('email')
        ->and($notif->template_key)->toBe('notification_quote_sent');

    Mail::assertQueued(NotificationMail::class);

    $queued = Mail::queued(NotificationMail::class)->first();
    expect($queued->ctaUrl)->toContain('/d/')
        ->and($queued->ctaLabel)->toBe('Voir le devis');
});

test('sendInvoicePaidFull bascule sur email', function () {
    Mail::fake();

    $company = bootstrapEmailFallbackCompany();
    $client = createEmailOnlyClient($company);

    $invoice = Invoice::unguarded(fn () => Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'FAC-PAID-EMAIL',
        'status' => InvoiceStatus::Paid->value,
        'issued_at' => now()->subDays(10),
        'due_at' => now()->subDays(5),
        'total' => 200_000,
        'amount_paid' => 200_000,
        'subtotal' => 200_000,
        'tax_amount' => 0,
        'currency' => 'XOF',
    ]));

    $payment = Payment::unguarded(fn () => Payment::create([
        'invoice_id' => $invoice->id,
        'amount' => 200_000,
        'paid_at' => now(),
        'method' => PaymentMethod::Cash->value,
    ]));

    $this->mock(WhatsAppProviderInterface::class, function (MockInterface $m) {
        $m->shouldNotReceive('sendTemplate');
    });

    $notif = app(WhatsAppNotificationService::class)->sendInvoicePaidFull($invoice, $payment, $company);

    expect($notif->channel)->toBe('email');

    Mail::assertQueued(NotificationMail::class);

    $queued = Mail::queued(NotificationMail::class)->first();
    expect($queued->subjectLine)->toContain('FAC-PAID-EMAIL')
        ->and($queued->body)->toContain('200 000');
});

test('WhatsApp est preferé quand le client a un telephone ET un email', function () {
    Mail::fake();

    $company = bootstrapEmailFallbackCompany();
    $client = createEmailOnlyClient($company, ['phone' => '+221771112233']);

    $invoice = Invoice::unguarded(fn () => Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'FAC-BOTH',
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
        $m->shouldReceive('sendTemplate')->once()->andReturnTrue();
    });

    $notif = app(WhatsAppNotificationService::class)->sendInvoiceCreated($invoice, $company);

    expect($notif->channel)->toBe('whatsapp');
    Mail::assertNothingQueued();
});

// ─── EmailReminderService : envoi réel ───────────────────────────────────────

test('EmailReminderService envoie le mail depuis le catalog pour une relance auto', function () {
    Mail::fake();

    $company = bootstrapEmailFallbackCompany();
    $client = createEmailOnlyClient($company);

    $invoice = Invoice::unguarded(fn () => Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'FAC-MAIL-P7',
        'status' => InvoiceStatus::Overdue->value,
        'issued_at' => now()->subDays(14),
        'due_at' => now()->subDays(7),
        'total' => 150_000,
        'amount_paid' => 0,
        'subtotal' => 150_000,
        'tax_amount' => 0,
        'currency' => 'XOF',
    ]));

    $reminder = app(EmailReminderService::class)->send($invoice, null, 7);

    expect($reminder->message_body)->toContain('Dakar Pharma')
        ->and($reminder->message_body)->toContain('FAC-MAIL-P7')
        ->and($reminder->recipient_email)->toBe('finance@dakar-pharma.test');

    Mail::assertQueued(NotificationMail::class);

    $queued = Mail::queued(NotificationMail::class)->first();
    expect($queued->subjectLine)->toContain('FAC-MAIL-P7')
        ->and($queued->subjectLine)->toContain('7 jours')
        ->and($queued->ctaLabel)->toBe('Voir la facture');
});

test('EmailReminderService accepte un templateKey manuel explicite', function () {
    Mail::fake();

    $company = bootstrapEmailFallbackCompany();
    $client = createEmailOnlyClient($company);

    $invoice = Invoice::unguarded(fn () => Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'FAC-MAIL-URG',
        'status' => InvoiceStatus::Overdue->value,
        'issued_at' => now()->subDays(30),
        'due_at' => now()->subDays(20),
        'total' => 400_000,
        'amount_paid' => 0,
        'subtotal' => 400_000,
        'tax_amount' => 0,
        'currency' => 'XOF',
    ]));

    app(EmailReminderService::class)->send($invoice, null, null, 'reminder_manual_urgent');

    Mail::assertQueued(NotificationMail::class);

    $queued = Mail::queued(NotificationMail::class)->first();
    expect($queued->body)->toContain('URGENT')
        ->and($queued->subjectLine)->toContain('impayée');
});

// ─── Pas de contact du tout ──────────────────────────────────────────────────

test('aucune notification envoyée si le client n a ni telephone ni email', function () {
    Mail::fake();

    $company = bootstrapEmailFallbackCompany();
    $client = createEmailOnlyClient($company, ['email' => null]);

    $invoice = Invoice::unguarded(fn () => Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'FAC-NONE',
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

    Mail::assertNothingQueued();
});
