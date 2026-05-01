<?php

namespace App\Http\Controllers\PME;

use App\Http\Controllers\Controller;
use App\Models\PME\ProposalDocument;
use App\Services\PME\PdfService;
use Illuminate\Http\Response;

class ProposalDocumentPdfController extends Controller
{
    public function __invoke(ProposalDocument $document, PdfService $pdfService): Response
    {
        return $pdfService->streamDocument($document);
    }
}
