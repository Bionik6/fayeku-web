<?php

namespace App\Services\Compta;

use App\DTOs\Compta\FiscalCertification;
use App\Enums\Compta\CertificationAuthority;
use App\Interfaces\Compta\FiscalConnectorInterface;
use App\Models\PME\Invoice;

class DGIDConnector implements FiscalConnectorInterface
{
    public function certify(Invoice $invoice): FiscalCertification
    {
        // DGID API not yet published.
        // DO NOT invent endpoints. Update this connector when the DGID publishes their API.
        throw new \RuntimeException(
            'DGID API not yet available. Certification skipped for SN invoices.'
        );
    }

    public function supportsCountry(string $countryCode): bool
    {
        return $countryCode === 'SN';
    }

    public function authority(): CertificationAuthority
    {
        return CertificationAuthority::DGID;
    }
}
