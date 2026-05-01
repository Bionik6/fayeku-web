<?php

namespace App\Events\PME;

use App\Models\PME\Invoice;
use App\Models\PME\ProposalDocument;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProposalDocumentConverted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ProposalDocument $document,
        public Invoice $invoice,
    ) {}
}
