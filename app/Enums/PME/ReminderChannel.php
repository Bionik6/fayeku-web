<?php

namespace App\Enums\PME;

enum ReminderChannel: string
{
    case WhatsApp = 'whatsapp';
    case Sms = 'sms';
    case Email = 'email';
}
