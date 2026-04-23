<?php

namespace App\Services\PME;

use App\Enums\PME\ReminderChannel;
use App\Interfaces\PME\ReminderChannelInterface;
use App\Mail\Shared\NotificationMail;
use App\Models\PME\Invoice;
use App\Models\PME\Reminder;
use App\Services\Shared\WhatsAppTemplateCatalog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class EmailReminderService implements ReminderChannelInterface
{
    public function __construct(private readonly WhatsAppTemplateCatalog $catalog) {}

    public function send(Invoice $invoice, ?string $messageBody = null, ?int $dayOffset = null, ?string $templateKey = null): Reminder
    {
        $invoice->loadMissing(['client', 'company']);

        $email = $invoice->client?->email;

        if (! $email) {
            throw new RuntimeException('Aucune adresse email disponible pour ce client.');
        }

        $templateKey ??= $dayOffset !== null
            ? $this->catalog->autoReminderKeyForOffset($dayOffset)
            : null;

        [$subject, $body] = $this->resolveContent($invoice, $templateKey, $messageBody);

        $ctaUrl = $invoice->public_code
            ? route('pme.invoices.pdf', ['invoice' => $invoice->public_code])
            : null;

        try {
            Mail::to($email)->send(new NotificationMail(
                subjectLine: $subject,
                body: $body,
                companyName: $invoice->company?->name ?? 'Fayeku',
                ctaUrl: $ctaUrl,
                ctaLabel: 'Voir la facture',
            ));
        } catch (Throwable $e) {
            Log::error('[EmailReminder] Echec d\'envoi email.', [
                'recipient_email' => $email,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Echec de l\'envoi email — consultez les logs pour plus de details.', 0, $e);
        }

        return Reminder::query()->create([
            'invoice_id' => $invoice->id,
            'channel' => ReminderChannel::Email,
            'sent_at' => now(),
            'message_body' => $body,
            'recipient_email' => $email,
            'day_offset' => $dayOffset,
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveContent(Invoice $invoice, ?string $templateKey, ?string $messageBody): array
    {
        if ($templateKey) {
            $variables = $this->catalog->invoiceVariables($invoice);

            return [
                $this->catalog->renderSubject($templateKey, $variables),
                $this->catalog->render($templateKey, $variables),
            ];
        }

        $fallbackBody = $messageBody ?? sprintf(
            'Rappel de paiement : la facture %s d\'un montant de %s FCFA reste en attente.',
            $invoice->reference ?? '—',
            number_format((int) $invoice->total, 0, ',', ' '),
        );

        return [
            'Rappel — facture '.($invoice->reference ?? 'Fayeku'),
            $fallbackBody,
        ];
    }
}
