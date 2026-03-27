<?php

namespace Modules\PME\Invoicing\Http\Controllers;

use Illuminate\Http\Response;
use Modules\PME\Invoicing\Models\Invoice;
use Modules\PME\Invoicing\Services\PdfService;

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
