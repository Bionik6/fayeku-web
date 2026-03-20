<?php

namespace Modules\PME\Collection\Services;

use Modules\PME\Collection\Interfaces\ReminderChannelInterface;
use Modules\PME\Collection\Models\Reminder;
use Modules\PME\Invoicing\Models\Invoice;

class SmsReminderService implements ReminderChannelInterface
{
    public function send(Invoice $invoice): Reminder
    {
        // TODO: implement SmsReminderService
        throw new \RuntimeException('SmsReminderService::send() not yet implemented.');
    }
}
