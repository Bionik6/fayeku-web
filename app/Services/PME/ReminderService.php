<?php

namespace App\Services\PME;

use App\Enums\PME\ReminderChannel;
use App\Enums\PME\ReminderMode;
use App\Interfaces\PME\ReminderChannelInterface;
use App\Models\Auth\Company;
use App\Models\PME\Invoice;
use App\Models\PME\Reminder;
use App\Services\Shared\QuotaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReminderService
{
    public function __construct(
        private QuotaService $quotaService,
        private WhatsAppReminderService $whatsApp,
        private SmsReminderService $sms,
        private EmailReminderService $email,
    ) {}

    public function send(
        Invoice $invoice,
        Company $company,
        ReminderChannel $channel,
        ?string $messageBody = null,
        ReminderMode $mode = ReminderMode::Manual,
        ?int $dayOffset = null,
        ?string $templateKey = null,
    ): Reminder {
        $invoice->loadMissing('client');

        // Garde-fou : un client sans le contact requis pour le canal ne peut pas
        // recevoir de relance. Cette défense couvre tous les chemins (manuel,
        // automatique, file d'attente, démo).
        if (! $invoice->client?->canReceiveReminderOn($channel)) {
            throw new \RuntimeException(
                "Le client n'a pas le contact requis pour le canal {$channel->value}."
            );
        }

        if (config('fayeku.demo')) {
            return $this->simulateReminder($invoice, $channel, $messageBody, $mode, $dayOffset);
        }

        $this->quotaService->authorize($company, 'reminders');

        return DB::transaction(function () use ($invoice, $company, $channel, $messageBody, $mode, $dayOffset, $templateKey) {
            $reminder = $this->resolveChannel($channel)->send($invoice, $messageBody, $dayOffset, $templateKey);

            $reminder->update(['mode' => $mode]);

            $this->quotaService->consume($company, 'reminders');

            return $reminder;
        });
    }

    /**
     * Persiste une trace de relance sans appeler le canal externe ni consommer
     * le quota — utilisé en mode démo pour que l'historique du recouvrement
     * reste crédible sans qu'aucun message ne parte réellement.
     */
    private function simulateReminder(
        Invoice $invoice,
        ReminderChannel $channel,
        ?string $messageBody,
        ReminderMode $mode,
        ?int $dayOffset,
    ): Reminder {
        $invoice->loadMissing('client');

        Log::info('[Demo] Relance simulée — aucun envoi externe.', [
            'invoice_id' => $invoice->id,
            'channel' => $channel->value,
            'mode' => $mode->value,
            'day_offset' => $dayOffset,
        ]);

        return Reminder::query()->create([
            'invoice_id' => $invoice->id,
            'channel' => $channel,
            'mode' => $mode,
            'day_offset' => $dayOffset,
            'sent_at' => now(),
            'message_body' => $messageBody,
            'recipient_phone' => $channel !== ReminderChannel::Email ? $invoice->client?->phone : null,
            'recipient_email' => $channel === ReminderChannel::Email ? $invoice->client?->email : null,
        ]);
    }

    private function resolveChannel(ReminderChannel $channel): ReminderChannelInterface
    {
        return match ($channel) {
            ReminderChannel::WhatsApp => $this->whatsApp,
            ReminderChannel::Sms => $this->sms,
            ReminderChannel::Email => $this->email,
        };
    }
}
