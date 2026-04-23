<?php

namespace App\Http\Controllers\PME;

use App\Models\PME\Invoice;
use App\Services\PME\PdfService;
use Illuminate\Http\Response;

class InvoicePdfController
{
    public function __invoke(Invoice $invoice, PdfService $pdfService): Response
    {
        return $pdfService->stream($invoice);
    }
}
