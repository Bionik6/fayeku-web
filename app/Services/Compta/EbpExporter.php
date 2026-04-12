<?php

namespace App\Services\Compta;

use Illuminate\Support\Collection;
use App\Interfaces\Compta\AccountingExporterInterface;
use App\Models\PME\Invoice;

class EbpExporter implements AccountingExporterInterface
{
    /** @param Collection<int, Invoice> $invoices */
    public function export(Collection $invoices): string
    {
        // TODO: implement EBP export
        throw new \RuntimeException('EBP export is not yet implemented.');
    }

    public function mimeType(): string
    {
        return 'text/plain';
    }

    public function filename(string $period): string
    {
        return sprintf('export-ebp-%s-%s.txt', $period, now()->format('Ymd-His'));
    }
}
