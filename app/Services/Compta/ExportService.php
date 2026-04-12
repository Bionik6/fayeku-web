<?php

namespace App\Services\Compta;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use App\Enums\Compta\ExportFormat;
use App\Interfaces\Compta\AccountingExporterInterface;
use App\Models\Compta\ExportHistory;
use App\Models\PME\Invoice;

class ExportService
{
    /**
     * Generate the export file, store it, and update the ExportHistory record.
     */
    public function generate(ExportHistory $exportHistory): ExportHistory
    {
        $invoices = $this->fetchInvoices(
            $exportHistory->client_ids,
            $exportHistory->period,
        );

        $exporter = $this->resolveExporter($exportHistory->format);

        $tempFile = $exporter->export($invoices);
        $filename = $exporter->filename($exportHistory->period);

        $storagePath = 'exports/'.$exportHistory->firm_id.'/'.$filename;
        Storage::disk('local')->put($storagePath, file_get_contents($tempFile));

        @unlink($tempFile);

        $exportHistory->update(['file_path' => $storagePath]);

        return $exportHistory;
    }

    public function resolveExporter(ExportFormat $format): AccountingExporterInterface
    {
        return match ($format) {
            ExportFormat::Excel => new ExcelExporter,
            ExportFormat::Sage100 => new SageExporter,
            ExportFormat::Ebp => new EbpExporter,
        };
    }

    /**
     * @param  array<int, string>  $clientIds
     * @return Collection<int, Invoice>
     */
    public function fetchInvoices(array $clientIds, string $period): Collection
    {
        if (empty($clientIds)) {
            return collect();
        }

        $query = Invoice::query()
            ->whereIn('company_id', $clientIds)
            ->with(['client', 'company'])
            ->orderBy('issued_at');

        $year = (int) substr($period, 0, 4);

        return match (true) {
            str_contains($period, '-T1') => $query->whereYear('issued_at', $year)->whereMonth('issued_at', '>=', 1)->whereMonth('issued_at', '<=', 3)->get(),
            str_contains($period, '-T2') => $query->whereYear('issued_at', $year)->whereMonth('issued_at', '>=', 4)->whereMonth('issued_at', '<=', 6)->get(),
            str_contains($period, '-T3') => $query->whereYear('issued_at', $year)->whereMonth('issued_at', '>=', 7)->whereMonth('issued_at', '<=', 9)->get(),
            str_contains($period, '-T4') => $query->whereYear('issued_at', $year)->whereMonth('issued_at', '>=', 10)->whereMonth('issued_at', '<=', 12)->get(),
            str_contains($period, '-S1') => $query->whereYear('issued_at', $year)->whereMonth('issued_at', '>=', 1)->whereMonth('issued_at', '<=', 6)->get(),
            str_contains($period, '-S2') => $query->whereYear('issued_at', $year)->whereMonth('issued_at', '>=', 7)->whereMonth('issued_at', '<=', 12)->get(),
            strlen($period) === 4 => $query->whereYear('issued_at', $year)->get(),
            default => $query->whereYear('issued_at', $year)->whereMonth('issued_at', (int) substr($period, 5, 2))->get(),
        };
    }
}
