<?php

namespace App\Interfaces\Compta;

use App\DTOs\Compta\FiscalCertification;
use App\Enums\Compta\CertificationAuthority;
use App\Models\PME\Invoice;

interface FiscalConnectorInterface
{
    public function certify(Invoice $invoice): FiscalCertification;

    public function supportsCountry(string $countryCode): bool;

    public function authority(): CertificationAuthority;
}
