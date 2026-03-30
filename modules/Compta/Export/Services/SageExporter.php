<?php

namespace Modules\Compta\Export\Services;

use Illuminate\Support\Collection;
use Modules\Compta\Export\Interfaces\AccountingExporterInterface;
use Modules\PME\Invoicing\Models\Invoice;

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
