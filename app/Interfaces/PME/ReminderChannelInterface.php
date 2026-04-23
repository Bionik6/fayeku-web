<?php

namespace App\Interfaces\PME;

use App\Models\PME\Invoice;
use App\Models\PME\Reminder;

interface ReminderChannelInterface
{
    public function send(Invoice $invoice, ?string $messageBody = null, ?int $dayOffset = null, ?string $templateKey = null): Reminder;
}
