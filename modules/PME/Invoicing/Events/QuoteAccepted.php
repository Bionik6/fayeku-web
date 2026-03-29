<?php

namespace Modules\PME\Invoicing\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\PME\Invoicing\Models\Invoice;
use Modules\PME\Invoicing\Models\Quote;

class QuoteAccepted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Quote $quote,
        public Invoice $invoice,
    ) {}
}
