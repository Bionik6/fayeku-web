<?php

namespace App\Listeners\PME;

use App\Events\PME\InvoiceCreated;

class NotifyAccountantOnNewInvoice
{
    public function handle(InvoiceCreated $event): void
    {
        // TODO: notify linked accountant firms
    }
}
