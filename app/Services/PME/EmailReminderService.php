<?php

namespace App\Services\PME;

use App\Enums\PME\ReminderChannel;
use App\Interfaces\PME\ReminderChannelInterface;
use App\Models\PME\Reminder;
use App\Models\PME\Invoice;

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
