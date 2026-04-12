<?php

namespace App\Interfaces\PME;

use App\Models\PME\Reminder;
use App\Models\PME\Invoice;

interface ReminderChannelInterface
{
    public function send(Invoice $invoice): Reminder;
}
