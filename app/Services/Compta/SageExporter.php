<?php

namespace App\Services\Compta;

use Illuminate\Support\Collection;
use App\Interfaces\Compta\AccountingExporterInterface;
use App\Models\PME\Invoice;

class SageExporter implements AccountingExporterInterface
{
    /** @param Collection<int, Invoice> $invoices */
    public function export(Collection $invoices): string
    {
        // TODO: implement Sage 100 export
        throw new \RuntimeException('Sage 100 export is not yet implemented.');
    }

    public function mimeType(): string
    {
        return 'text/plain';
    }

    public function filename(string $period): string
    {
        return sprintf('export-sage100-%s-%s.txt', $period, now()->format('Ymd-His'));
    }
}
