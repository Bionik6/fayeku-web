<?php

namespace Modules\PME\Collection\Services;

use Modules\PME\Collection\Enums\ReminderChannel;
use Modules\PME\Collection\Interfaces\ReminderChannelInterface;
use Modules\PME\Collection\Models\Reminder;
use Modules\PME\Invoicing\Models\Invoice;

class WhatsAppReminderService implements ReminderChannelInterface
{
    public function send(Invoice $invoice): Reminder
    {
        $invoice->loadMissing('client');

        if (! $invoice->client?->phone) {
            throw new \RuntimeException('Aucun numero WhatsApp disponible pour ce client.');
        }

        return Reminder::query()->create([
            'invoice_id' => $invoice->id,
            'channel' => ReminderChannel::WhatsApp,
            'sent_at' => now(),
            'message_body' => sprintf(
                'Bonjour, la facture %s de %s FCFA reste en attente. Merci de prévoir votre règlement.',
                $invoice->reference ?? '—',
                number_format((int) $invoice->total, 0, ',', ' ')
            ),
            'recipient_phone' => $invoice->client->phone,
        ]);
    }
}
