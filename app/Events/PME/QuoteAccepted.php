<?php

namespace App\Events\PME;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\PME\Invoice;
use App\Models\PME\Quote;

class QuoteAccepted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Quote $quote,
        public Invoice $invoice,
    ) {}
}
