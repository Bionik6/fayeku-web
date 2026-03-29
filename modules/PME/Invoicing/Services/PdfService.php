<?php

namespace Modules\PME\Invoicing\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPDF;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Modules\PME\Invoicing\Models\Invoice;
use Modules\PME\Invoicing\Models\Quote;

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

    /**
     * Generate a PDF object for the given quote.
     */
    public function generateQuote(Quote $quote): DomPDF
    {
        $quote->loadMissing(['company', 'client', 'lines']);

        $logoBase64 = null;

        if ($quote->company->logo_path && Storage::exists($quote->company->logo_path)) {
            $logoContent = Storage::get($quote->company->logo_path);

            if ($logoContent) {
                $mime = Storage::mimeType($quote->company->logo_path) ?: 'image/png';
                $logoBase64 = 'data:'.$mime.';base64,'.base64_encode($logoContent);
            }
        }

        return Pdf::loadView('pdf.quote', [
            'quote' => $quote,
            'logoBase64' => $logoBase64,
        ])->setPaper('a4');
    }

    /**
     * Stream the quote PDF inline in the browser.
     */
    public function streamQuote(Quote $quote): Response
    {
        $filename = "devis-{$quote->reference}.pdf";

        return $this->generateQuote($quote)->stream($filename);
    }

    /**
     * Force-download the quote PDF.
     */
    public function downloadQuote(Quote $quote): Response
    {
        $filename = "devis-{$quote->reference}.pdf";

        return $this->generateQuote($quote)->download($filename);
    }
}
