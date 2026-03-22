<?php

namespace Modules\Compta\Compliance\Services;

use Modules\Compta\Compliance\DTOs\FiscalCertification;
use Modules\Compta\Compliance\Enums\CertificationAuthority;
use Modules\Compta\Compliance\Interfaces\FiscalConnectorInterface;
use Modules\PME\Invoicing\Models\Invoice;

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
