<?php

namespace App\Events\PME;

use App\Models\PME\Invoice;
use App\Models\PME\Proforma;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProformaConverted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Proforma $proforma,
        public Invoice $invoice,
    ) {}
}
