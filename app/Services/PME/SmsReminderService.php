<?php

namespace App\Services\PME;

use App\Enums\PME\ReminderChannel;
use App\Interfaces\PME\ReminderChannelInterface;
use App\Interfaces\Shared\SmsProviderInterface;
use App\Models\PME\Invoice;
use App\Models\PME\Reminder;
use RuntimeException;

class SmsReminderService implements ReminderChannelInterface
{
    public function __construct(private readonly SmsProviderInterface $provider) {}

    public function send(Invoice $invoice, ?string $messageBody = null, ?int $dayOffset = null, ?string $templateKey = null): Reminder
    {
        $invoice->loadMissing('client');

        $phone = $invoice->client?->phone;

        if (! $phone) {
            throw new RuntimeException('Aucun numero SMS disponible pour ce client.');
        }

        $body = $messageBody ?? sprintf(
            'Rappel Fayeku : la facture %s (%s FCFA) est en attente de paiement.',
            $invoice->reference ?? '—',
            number_format((int) $invoice->total, 0, ',', ' '),
        );

        if (! $this->provider->send($phone, $body)) {
            throw new RuntimeException('Echec de l\'envoi SMS — consultez les logs pour plus de details.');
        }

        return Reminder::query()->create([
            'invoice_id' => $invoice->id,
            'channel' => ReminderChannel::Sms,
            'sent_at' => now(),
            'message_body' => $body,
            'recipient_phone' => $phone,
            'day_offset' => $dayOffset,
        ]);
    }
}
