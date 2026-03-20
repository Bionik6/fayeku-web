<?php

namespace Modules\PME\Collection\Services;

use Modules\PME\Collection\Interfaces\ReminderChannelInterface;
use Modules\PME\Collection\Models\Reminder;
use Modules\PME\Invoicing\Models\Invoice;

class EmailReminderService implements ReminderChannelInterface
{
    public function send(Invoice $invoice): Reminder
    {
        // TODO: implement EmailReminderService
        throw new \RuntimeException('EmailReminderService::send() not yet implemented.');
    }
}
