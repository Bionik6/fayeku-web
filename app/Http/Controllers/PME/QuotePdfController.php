<?php

namespace App\Http\Controllers\PME;

use App\Models\PME\Quote;
use App\Services\PME\PdfService;
use Illuminate\Http\Response;

class QuotePdfController
{
    public function __invoke(Quote $quote, PdfService $pdfService): Response
    {
        return $pdfService->streamQuote($quote);
    }
}
