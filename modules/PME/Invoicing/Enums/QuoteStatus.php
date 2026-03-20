<?php

namespace Modules\PME\Invoicing\Enums;

enum QuoteStatus: string
{
    case Draft    = 'draft';
    case Sent     = 'sent';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired  = 'expired';
}
