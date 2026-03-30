<?php

namespace Modules\Compta\Export\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\Compta\Export\Models\ExportHistory;
use Modules\Compta\Export\Services\ExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportDownloadController extends Controller
{
    public function __invoke(Request $request, ExportHistory $exportHistory, ExportService $exportService): StreamedResponse
    {
        $firm = $request->user()?->accountantFirm();

        abort_unless($firm && $exportHistory->firm_id === $firm->id, 403);

        if (! $exportHistory->file_path || ! Storage::disk('local')->exists($exportHistory->file_path)) {
            abort(404);
        }

        $exporter = $exportService->resolveExporter($exportHistory->format);

        return Storage::disk('local')->download(
            $exportHistory->file_path,
            $exporter->filename($exportHistory->period),
            ['Content-Type' => $exporter->mimeType()],
        );
    }
}
