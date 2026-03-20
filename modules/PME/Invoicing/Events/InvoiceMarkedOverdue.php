<?php

namespace Modules\PME\Invoicing\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoiceMarkedOverdue
{
    use Dispatchable, SerializesModels;
    // TODO: add constructor properties
}
