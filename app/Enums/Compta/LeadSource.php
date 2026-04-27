<?php

namespace App\Enums\Compta;

enum LeadSource: string
{
    case Organic = 'organic';
    case Referral = 'referral';
    case Event = 'event';
    case WhatsAppOutreach = 'whatsapp_outreach';
    case LinkedIn = 'linkedin';
    case Press = 'press';
    case Other = 'other';
}
