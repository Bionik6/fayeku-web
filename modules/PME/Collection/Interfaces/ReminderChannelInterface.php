<?php

namespace Modules\PME\Collection\Interfaces;

use Modules\PME\Collection\Models\Reminder;
use Modules\PME\Invoicing\Models\Invoice;

interface ReminderChannelInterface
{
    public function send(Invoice $invoice): Reminder;
}
