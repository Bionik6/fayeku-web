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

class ReminderService
{
    public function __construct(
        private QuotaService $quotaService,
        private WhatsAppReminderService $whatsApp,
        private SmsReminderService $sms,
        private EmailReminderService $email,
    ) {}

    public function send(Invoice $invoice, Company $company, ReminderChannel $channel, ?string $messageBody = null, ReminderMode $mode = ReminderMode::Manual, ?int $dayOffset = null): Reminder
    {
        $this->quotaService->authorize($company, 'reminders');

        return DB::transaction(function () use ($invoice, $company, $channel, $messageBody, $mode, $dayOffset) {
            $reminder = $this->resolveChannel($channel)->send($invoice);

            $updates = ['mode' => $mode];

            if ($messageBody !== null) {
                $updates['message_body'] = $messageBody;
            }

            if ($dayOffset !== null) {
                $updates['day_offset'] = $dayOffset;
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
