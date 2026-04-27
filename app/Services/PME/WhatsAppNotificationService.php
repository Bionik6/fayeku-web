<?php

namespace App\Services\PME;

use App\Exceptions\Shared\QuotaExceededException;
use App\Interfaces\Shared\WhatsAppProviderInterface;
use App\Mail\Shared\NotificationMail;
use App\Models\Auth\Company;
use App\Models\PME\Invoice;
use App\Models\PME\Payment;
use App\Models\PME\Quote;
use App\Models\Shared\Notification;
use App\Services\Shared\QuotaService;
use App\Services\Shared\WhatsAppTemplateCatalog;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class WhatsAppNotificationService
{
    public function __construct(
        private readonly QuotaService $quotaService,
        private readonly WhatsAppProviderInterface $provider,
        private readonly WhatsAppTemplateCatalog $catalog,
    ) {}

    /**
     * Notification "nouvelle facture disponible" — bascule automatique entre
     * le template avec ou sans date d'échéance selon qu'elle est aujourd'hui ou future.
     */
    public function sendInvoiceCreated(Invoice $invoice, Company $company): ?Notification
    {
        $invoice->loadMissing(['client', 'company']);

        $templateKey = $this->invoiceCreatedTemplateKey($invoice);

        return $this->dispatch(
            notifiable: $invoice,
            company: $company,
            templateKey: $templateKey,
            variables: $this->catalog->invoiceVariables($invoice, $company),
            urlButtonParameter: $invoice->public_code ? $invoice->public_code.'/pdf' : null,
            recipientPhone: $invoice->client?->phone,
            recipientEmail: $invoice->client?->email,
        );
    }

    public function sendInvoicePartiallyPaid(Invoice $invoice, Payment $payment, Company $company): ?Notification
    {
        $invoice->loadMissing(['client', 'company']);

        $remaining = max(0, (int) $invoice->total - (int) $invoice->amount_paid);

        $variables = array_merge($this->catalog->invoiceVariables($invoice, $company), [
            'amount_paid' => CurrencyService::format((int) $payment->amount, $invoice->currency ?? 'XOF', withLabel: true),
            'amount_remaining' => CurrencyService::format($remaining, $invoice->currency ?? 'XOF', withLabel: true),
            'payment_date' => $payment->paid_at instanceof CarbonInterface
                ? $payment->paid_at->locale('fr')->translatedFormat('d F Y')
                : '',
        ]);

        return $this->dispatch(
            notifiable: $invoice,
            company: $company,
            templateKey: 'notification_invoice_partially_paid',
            variables: $variables,
            urlButtonParameter: $invoice->public_code ? $invoice->public_code.'/pdf' : null,
            recipientPhone: $invoice->client?->phone,
            recipientEmail: $invoice->client?->email,
            meta: ['payment_id' => $payment->id],
        );
    }

    public function sendInvoicePaidFull(Invoice $invoice, Payment $payment, Company $company): ?Notification
    {
        $invoice->loadMissing(['client', 'company']);

        $variables = array_merge($this->catalog->invoiceVariables($invoice, $company), [
            'amount_paid' => CurrencyService::format((int) $payment->amount, $invoice->currency ?? 'XOF', withLabel: true),
            'payment_date' => $payment->paid_at instanceof CarbonInterface
                ? $payment->paid_at->locale('fr')->translatedFormat('d F Y')
                : '',
        ]);

        return $this->dispatch(
            notifiable: $invoice,
            company: $company,
            templateKey: 'notification_invoice_paid_full',
            variables: $variables,
            urlButtonParameter: $invoice->public_code ? $invoice->public_code.'/pdf' : null,
            recipientPhone: $invoice->client?->phone,
            recipientEmail: $invoice->client?->email,
            meta: ['payment_id' => $payment->id],
        );
    }

    public function sendQuoteSent(Quote $quote, Company $company): ?Notification
    {
        $quote->loadMissing(['client', 'company']);
        $currency = $quote->currency ?? 'XOF';

        $variables = [
            'client_name' => $quote->client?->name ?? 'Cher client',
            'company_name' => $company->name ?? '',
            'quote_number' => $quote->reference ?? '',
            'quote_amount' => CurrencyService::format((int) $quote->total, $currency, withLabel: true),
            'expiry_date' => $quote->valid_until instanceof CarbonInterface
                ? $quote->valid_until->locale('fr')->translatedFormat('d F Y')
                : '',
            'sender_signature' => $company->composeSenderSignature(),
        ];

        return $this->dispatch(
            notifiable: $quote,
            company: $company,
            templateKey: 'notification_quote_sent',
            variables: $variables,
            urlButtonParameter: $quote->public_code ? $quote->public_code.'/pdf' : null,
            recipientPhone: $quote->client?->phone,
            recipientEmail: $quote->client?->email,
        );
    }

    /**
     * @param  array<string, string>  $variables
     * @param  array<string, mixed>  $meta
     */
    private function dispatch(
        Model $notifiable,
        Company $company,
        string $templateKey,
        array $variables,
        ?string $urlButtonParameter,
        ?string $recipientPhone,
        ?string $recipientEmail,
        array $meta = [],
    ): ?Notification {
        if (! $recipientPhone && ! $recipientEmail) {
            Log::info('[Notification] Client sans contact — notification ignorée.', [
                'template_key' => $templateKey,
                'notifiable' => $notifiable::class.':'.$notifiable->getKey(),
            ]);

            return null;
        }

        if (config('fayeku.demo')) {
            return $this->simulateNotification(
                $notifiable, $company, $templateKey, $variables, $recipientPhone, $recipientEmail, $meta,
            );
        }

        try {
            $this->quotaService->authorize($company, 'reminders');
        } catch (QuotaExceededException) {
            Log::warning('[Notification] Quota dépassé — notification non envoyée.', [
                'company_id' => $company->id,
                'template_key' => $templateKey,
            ]);

            return null;
        }

        $body = $this->catalog->render($templateKey, $variables);

        // Route : WhatsApp si téléphone, sinon fallback email via Resend.
        if ($recipientPhone) {
            $delivered = $this->provider->sendTemplate(
                $recipientPhone,
                $this->catalog->nameFor($templateKey),
                $variables,
                urlButtonParameter: $urlButtonParameter,
            );

            if (! $delivered) {
                Log::error('[Notification] Echec d\'envoi WhatsApp.', [
                    'template_key' => $templateKey,
                    'recipient_phone' => $recipientPhone,
                ]);

                return null;
            }

            return $this->persist($notifiable, $company, $templateKey, 'whatsapp', $body, $recipientPhone, $recipientEmail, $meta);
        }

        return $this->dispatchEmail($notifiable, $company, $templateKey, $variables, $body, $recipientEmail, $meta);
    }

    /**
     * @param  array<string, string>  $variables
     * @param  array<string, mixed>  $meta
     */
    private function dispatchEmail(
        Model $notifiable,
        Company $company,
        string $templateKey,
        array $variables,
        string $body,
        string $recipientEmail,
        array $meta,
    ): ?Notification {
        $subject = $this->catalog->renderSubject($templateKey, $variables);
        $cta = $this->emailCtaFor($notifiable);

        try {
            Mail::to($recipientEmail)->send(new NotificationMail(
                subjectLine: $subject,
                body: $body,
                companyName: $company->name ?? 'Fayeku',
                ctaUrl: $cta['url'] ?? null,
                ctaLabel: $cta['label'] ?? null,
            ));
        } catch (Throwable $e) {
            Log::error('[Notification] Echec d\'envoi email.', [
                'template_key' => $templateKey,
                'recipient_email' => $recipientEmail,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        return $this->persist($notifiable, $company, $templateKey, 'email', $body, null, $recipientEmail, $meta);
    }

    /**
     * Persiste une trace de notification sans appeler le canal externe ni
     * consommer le quota — utilisé en mode démo pour conserver un historique
     * crédible côté UI sans qu'aucun message ne parte réellement.
     *
     * @param  array<string, string>  $variables
     * @param  array<string, mixed>  $meta
     */
    private function simulateNotification(
        Model $notifiable,
        Company $company,
        string $templateKey,
        array $variables,
        ?string $recipientPhone,
        ?string $recipientEmail,
        array $meta,
    ): Notification {
        $body = $this->catalog->render($templateKey, $variables);
        $channel = $recipientPhone ? 'whatsapp' : 'email';

        Log::info('[Demo] Notification simulée — aucun envoi externe.', [
            'template_key' => $templateKey,
            'notifiable' => $notifiable::class.':'.$notifiable->getKey(),
            'channel' => $channel,
        ]);

        return Notification::query()->create([
            'company_id' => $company->id,
            'notifiable_type' => $notifiable::class,
            'notifiable_id' => $notifiable->getKey(),
            'template_key' => $templateKey,
            'channel' => $channel,
            'sent_at' => now(),
            'message_body' => $body,
            'recipient_phone' => $recipientPhone,
            'recipient_email' => $channel === 'email' ? $recipientEmail : null,
            'meta' => $meta !== [] ? $meta : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function persist(
        Model $notifiable,
        Company $company,
        string $templateKey,
        string $channel,
        string $body,
        ?string $recipientPhone,
        ?string $recipientEmail,
        array $meta,
    ): Notification {
        return DB::transaction(function () use ($notifiable, $company, $templateKey, $channel, $body, $recipientPhone, $recipientEmail, $meta) {
            $this->quotaService->consume($company, 'reminders');

            return Notification::query()->create([
                'company_id' => $company->id,
                'notifiable_type' => $notifiable::class,
                'notifiable_id' => $notifiable->getKey(),
                'template_key' => $templateKey,
                'channel' => $channel,
                'sent_at' => now(),
                'message_body' => $body,
                'recipient_phone' => $recipientPhone,
                'recipient_email' => $recipientEmail,
                'meta' => $meta !== [] ? $meta : null,
            ]);
        });
    }

    /**
     * @return array{url: string, label: string}|null
     */
    private function emailCtaFor(Model $notifiable): ?array
    {
        if ($notifiable instanceof Invoice && $notifiable->public_code) {
            return [
                'url' => route('pme.invoices.pdf', ['invoice' => $notifiable->public_code]),
                'label' => 'Voir la facture',
            ];
        }

        if ($notifiable instanceof Quote && $notifiable->public_code) {
            return [
                'url' => route('pme.quotes.pdf', ['quote' => $notifiable->public_code]),
                'label' => 'Voir le devis',
            ];
        }

        return null;
    }

    private function invoiceCreatedTemplateKey(Invoice $invoice): string
    {
        if (! $invoice->due_at instanceof CarbonInterface) {
            return 'notification_invoice_sent';
        }

        return $invoice->due_at->isSameDay(now())
            ? 'notification_invoice_sent'
            : 'notification_invoice_sent_with_due_date';
    }
}
