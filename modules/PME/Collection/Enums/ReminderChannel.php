<?php

namespace Modules\PME\Collection\Enums;

enum ReminderChannel: string
{
    case WhatsApp = 'whatsapp';
    case Sms = 'sms';
    case Email = 'email';
}
