<?php

namespace App\Http\Controllers\PME;

use Illuminate\Http\Response;
use App\Models\PME\Invoice;
use App\Services\PME\PdfService;

class InvoicePdfController
{
    public function __invoke(Invoice $invoice, PdfService $pdfService): Response
    {
        abort_unless(
            auth()->user()->companies()->where('companies.id', $invoice->company_id)->exists(),
            403
        );

        return $pdfService->stream($invoice);
    }
}
