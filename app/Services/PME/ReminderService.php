<?php

namespace App\Services\PME;

use Illuminate\Support\Facades\DB;
use App\Models\Auth\Company;
use App\Enums\PME\ReminderChannel;
use App\Interfaces\PME\ReminderChannelInterface;
use App\Models\PME\Reminder;
use App\Models\PME\Invoice;
use App\Services\Shared\QuotaService;

class ReminderService
{
    public function __construct(
        private QuotaService $quotaService,
        private WhatsAppReminderService $whatsApp,
        private SmsReminderService $sms,
        private EmailReminderService $email,
    ) {}

    public function send(Invoice $invoice, Company $company, ReminderChannel $channel, ?string $messageBody = null, bool $isManual = false): Reminder
    {
        $this->quotaService->authorize($company, 'reminders');

        return DB::transaction(function () use ($invoice, $company, $channel, $messageBody, $isManual) {
            $reminder = $this->resolveChannel($channel)->send($invoice);

            $updates = ['is_manual' => $isManual];

            if ($messageBody !== null) {
                $updates['message_body'] = $messageBody;
            }

            $reminder->update($updates);

            $this->quotaService->consume($company, 'reminders');

            return $reminder;
        });
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
