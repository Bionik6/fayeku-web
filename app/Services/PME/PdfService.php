<?php

namespace App\Services\PME;

use App\Models\Auth\Company;
use App\Models\PME\Invoice;
use App\Models\PME\ProposalDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPDF;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class PdfService
{
    public function generate(Invoice $invoice): DomPDF
    {
        $invoice->loadMissing(['company', 'client', 'lines']);

        return Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'logoBase64' => $this->logoBase64($invoice->company),
        ])->setPaper('a4');
    }

    public function stream(Invoice $invoice): Response
    {
        return $this->generate($invoice)->stream("facture-{$invoice->reference}.pdf");
    }

    public function download(Invoice $invoice): Response
    {
        return $this->generate($invoice)->download("facture-{$invoice->reference}.pdf");
    }

    public function rawContent(Invoice $invoice): string
    {
        return $this->generate($invoice)->output();
    }

    public function generateDocument(ProposalDocument $document): DomPDF
    {
        $document->loadMissing(['company', 'client', 'lines']);

        return Pdf::loadView('pdf.proposal-document', [
            'document' => $document,
            'logoBase64' => $this->logoBase64($document->company),
        ])->setPaper('a4');
    }

    public function streamDocument(ProposalDocument $document): Response
    {
        return $this->generateDocument($document)->stream($this->documentFilename($document));
    }

    public function downloadDocument(ProposalDocument $document): Response
    {
        return $this->generateDocument($document)->download($this->documentFilename($document));
    }

    private function documentFilename(ProposalDocument $document): string
    {
        $prefix = $document->isProforma() ? 'proforma' : 'devis';

        return "{$prefix}-{$document->reference}.pdf";
    }

    private function logoBase64(?Company $company): ?string
    {
        if (! $company || ! $company->logo_path || ! Storage::exists($company->logo_path)) {
            return null;
        }

        $content = Storage::get($company->logo_path);

        if (! $content) {
            return null;
        }

        $mime = Storage::mimeType($company->logo_path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($content);
    }
}
