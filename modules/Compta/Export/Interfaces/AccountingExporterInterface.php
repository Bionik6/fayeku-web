<?php

namespace Modules\Compta\Export\Interfaces;

use Illuminate\Support\Collection;

interface AccountingExporterInterface
{
    public function export(Collection $invoices): string;

    public function mimeType(): string;

    public function filename(string $period): string;
}
