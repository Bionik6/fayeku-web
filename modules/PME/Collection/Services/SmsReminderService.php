<?php

namespace Modules\PME\Collection\Services;

use Modules\PME\Collection\Enums\ReminderChannel;
use Modules\PME\Collection\Interfaces\ReminderChannelInterface;
use Modules\PME\Collection\Models\Reminder;
use Modules\PME\Invoicing\Models\Invoice;

class SmsReminderService implements ReminderChannelInterface
{
    public function send(Invoice $invoice): Reminder
    {
        $invoice->loadMissing('client');

        if (! $invoice->client?->phone) {
            throw new \RuntimeException('Aucun numero SMS disponible pour ce client.');
        }

        return Reminder::query()->create([
            'invoice_id' => $invoice->id,
            'channel' => ReminderChannel::Sms,
            'sent_at' => now(),
            'message_body' => sprintf(
                'Rappel Fayeku : la facture %s (%s FCFA) est en attente de paiement.',
                $invoice->reference ?? '—',
                number_format((int) $invoice->total, 0, ',', ' ')
            ),
            'recipient_phone' => $invoice->client->phone,
        ]);
    }
}
