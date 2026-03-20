<?php

namespace Modules\Compta\Compliance\Interfaces;

use Modules\Compta\Compliance\DTOs\FiscalCertification;
use Modules\PME\Invoicing\Models\Invoice;

interface FiscalConnectorInterface
{
    public function certify(Invoice $invoice): FiscalCertification;
    public function supportsCountry(string $countryCode): bool;
}
