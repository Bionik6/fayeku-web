<?php

namespace Modules\PME\Invoicing\Http\Controllers;

use Illuminate\Http\Response;
use Modules\PME\Invoicing\Models\Quote;
use Modules\PME\Invoicing\Services\PdfService;

class QuotePdfController
{
    public function __invoke(Quote $quote, PdfService $pdfService): Response
    {
        abort_unless(
            auth()->user()->companies()->where('companies.id', $quote->company_id)->exists(),
            403
        );

        return $pdfService->streamQuote($quote);
    }
}
