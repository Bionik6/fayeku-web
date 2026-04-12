<?php

namespace Modules\PME\Collection\Services;

use Modules\PME\Collection\Enums\ReminderChannel;
use Modules\PME\Collection\Interfaces\ReminderChannelInterface;
use Modules\PME\Collection\Models\Reminder;
use Modules\PME\Invoicing\Models\Invoice;

class EmailReminderService implements ReminderChannelInterface
{
    public function send(Invoice $invoice): Reminder
    {
        $invoice->loadMissing('client');

        if (! $invoice->client?->email) {
            throw new \RuntimeException('Aucune adresse email disponible pour ce client.');
        }

        return Reminder::query()->create([
            'invoice_id' => $invoice->id,
            'channel' => ReminderChannel::Email,
            'sent_at' => now(),
            'message_body' => sprintf(
                'Objet : rappel de paiement %s. Montant en attente : %s FCFA.',
                $invoice->reference ?? '—',
                number_format((int) $invoice->total, 0, ',', ' ')
            ),
            'recipient_email' => $invoice->client->email,
        ]);
    }
}
