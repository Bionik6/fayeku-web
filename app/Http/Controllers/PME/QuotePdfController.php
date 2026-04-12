<?php

namespace App\Http\Controllers\PME;

use Illuminate\Http\Response;
use App\Models\PME\Quote;
use App\Services\PME\PdfService;

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
