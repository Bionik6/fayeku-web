<?php

namespace App\Services\PME;

use App\Enums\PME\ReminderChannel;
use App\Interfaces\PME\ReminderChannelInterface;
use App\Interfaces\Shared\WhatsAppProviderInterface;
use App\Models\PME\Invoice;
use App\Models\PME\Reminder;
use App\Services\Shared\WhatsAppTemplateCatalog;
use RuntimeException;

class WhatsAppReminderService implements ReminderChannelInterface
{
    public function __construct(
        private readonly WhatsAppProviderInterface $provider,
        private readonly WhatsAppTemplateCatalog $catalog,
    ) {}

    public function send(Invoice $invoice, ?string $messageBody = null, ?int $dayOffset = null, ?string $templateKey = null): Reminder
    {
        $invoice->loadMissing(['client', 'company']);

        $phone = $invoice->client?->phone;

        if (! $phone) {
            throw new RuntimeException('Aucun numero WhatsApp disponible pour ce client.');
        }

        $templateKey ??= $dayOffset !== null
            ? $this->catalog->autoReminderKeyForOffset($dayOffset)
            : null;

        $variables = $this->catalog->invoiceVariables($invoice);
        $body = $templateKey
            ? $this->catalog->render($templateKey, $variables)
            : ($messageBody ?? $this->defaultBody($invoice));

        $delivered = $templateKey
            ? $this->provider->sendTemplate(
                $phone,
                $this->catalog->nameFor($templateKey),
                $variables,
                urlButtonParameter: $this->buildUrlButtonParameter($invoice),
            )
            : $this->provider->send($phone, $body);

        if (! $delivered) {
            throw new RuntimeException("Echec de l'envoi WhatsApp — consultez les logs pour plus de details.");
        }

        return Reminder::query()->create([
            'invoice_id' => $invoice->id,
            'channel' => ReminderChannel::WhatsApp,
            'sent_at' => now(),
            'message_body' => $body,
            'recipient_phone' => $phone,
            'day_offset' => $dayOffset,
        ]);
    }

    private function buildUrlButtonParameter(Invoice $invoice): ?string
    {
        return $invoice->public_code ? $invoice->public_code.'/pdf' : null;
    }

    private function defaultBody(Invoice $invoice): string
    {
        return sprintf(
            'Bonjour, la facture %s de %s FCFA reste en attente. Merci de prévoir votre règlement.',
            $invoice->reference ?? '—',
            number_format((int) $invoice->total, 0, ',', ' '),
        );
    }
}
