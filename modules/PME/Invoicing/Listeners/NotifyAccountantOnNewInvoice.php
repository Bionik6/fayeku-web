<?php

namespace Modules\PME\Invoicing\Listeners;

use Modules\PME\Invoicing\Events\InvoiceCreated;

class NotifyAccountantOnNewInvoice
{
    public function handle(InvoiceCreated $event): void
    {
        // TODO: notify linked accountant firms
    }
}
