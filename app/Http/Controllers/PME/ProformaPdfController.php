<?php

namespace App\Http\Controllers\PME;

use App\Models\PME\Proforma;
use App\Services\PME\PdfService;
use Illuminate\Http\Response;

class ProformaPdfController
{
    public function __invoke(Proforma $proforma, PdfService $pdfService): Response
    {
        return $pdfService->streamProforma($proforma);
    }
}
