<?php

namespace App\Enums\PME;

enum ProformaStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case PoReceived = 'po_received';
    case Converted = 'converted';
    case Declined = 'declined';
    case Expired = 'expired';
}
