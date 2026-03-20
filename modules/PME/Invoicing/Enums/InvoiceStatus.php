<?php

namespace Modules\PME\Invoicing\Enums;

enum InvoiceStatus: string
{
    case Draft               = 'draft';
    case Sent                = 'sent';
    case Certified           = 'certified';
    case CertificationFailed = 'certification_failed';
    case PartiallyPaid       = 'partially_paid';
    case Paid                = 'paid';
    case Overdue             = 'overdue';
    case Cancelled           = 'cancelled';
}
