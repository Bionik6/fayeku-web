<?php

namespace Modules\PME\Collection\Services;

use Modules\PME\Collection\Interfaces\ReminderChannelInterface;
use Modules\PME\Collection\Models\Reminder;
use Modules\PME\Invoicing\Models\Invoice;

class WhatsAppReminderService implements ReminderChannelInterface
{
    public function send(Invoice $invoice): Reminder
    {
        // TODO: implement WhatsAppReminderService
        throw new \RuntimeException('WhatsAppReminderService::send() not yet implemented.');
    }
}
