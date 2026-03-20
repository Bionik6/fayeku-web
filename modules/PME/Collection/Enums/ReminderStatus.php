<?php

namespace Modules\PME\Collection\Enums;

enum ReminderStatus: string
{
    case Pending   = 'pending';
    case Sent      = 'sent';
    case Delivered = 'delivered';
    case Failed    = 'failed';
}
