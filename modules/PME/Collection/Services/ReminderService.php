<?php

namespace Modules\PME\Collection\Services;

use Illuminate\Support\Facades\DB;
use Modules\Auth\Models\Company;
use Modules\PME\Collection\Enums\ReminderChannel;
use Modules\PME\Collection\Interfaces\ReminderChannelInterface;
use Modules\PME\Collection\Models\Reminder;
use Modules\PME\Invoicing\Models\Invoice;
use Modules\Shared\Services\QuotaService;

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
