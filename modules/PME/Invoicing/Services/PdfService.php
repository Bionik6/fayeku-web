<?php

namespace Modules\PME\Invoicing\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPDF;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Modules\PME\Invoicing\Models\Invoice;

class PdfService
{
    /**
     * Generate a PDF object for the given invoice.
     */
    public function generate(Invoice $invoice): DomPDF
    {
        $invoice->loadMissing(['company', 'client', 'lines']);

        $logoBase64 = null;

        if ($invoice->company->logo_path && Storage::exists($invoice->company->logo_path)) {
            $logoContent = Storage::get($invoice->company->logo_path);

            if ($logoContent) {
                $mime = Storage::mimeType($invoice->company->logo_path) ?: 'image/png';
                $logoBase64 = 'data:'.$mime.';base64,'.base64_encode($logoContent);
            }
        }

        return Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'logoBase64' => $logoBase64,
        ])->setPaper('a4');
    }

    /**
     * Stream the PDF inline in the browser (for preview).
     */
    public function stream(Invoice $invoice): Response
    {
        $filename = "facture-{$invoice->reference}.pdf";

        return $this->generate($invoice)->stream($filename);
    }

    /**
     * Force-download the PDF.
     */
    public function download(Invoice $invoice): Response
    {
        $filename = "facture-{$invoice->reference}.pdf";

        return $this->generate($invoice)->download($filename);
    }

    /**
     * Return the raw PDF binary for attachments.
     */
    public function rawContent(Invoice $invoice): string
    {
        return $this->generate($invoice)->output();
    }
}
