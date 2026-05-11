<?php

namespace App\Events\PME;

use App\Models\PME\ProposalDocument;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProposalDocumentAccepted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ProposalDocument $document,
    ) {}
}
