<?php

namespace Modules\Compta\Compliance\Services;

use Modules\Compta\Compliance\Interfaces\FiscalConnectorInterface;
use Modules\PME\Invoicing\Models\Invoice;

class ComplianceService
{
    /** @param FiscalConnectorInterface[] $connectors */
    public function __construct(private array $connectors) {}

    public function certify(Invoice $invoice): void
    {
        $country = $invoice->company->country_code;
        $connector = collect($this->connectors)->first(
            fn ($c) => $c->supportsCountry($country)
        );

        if (! $connector) {
            return; // no connector for this country — skip gracefully
        }

        try {
            $cert = $connector->certify($invoice);
            $invoice->update([
                'fne_reference' => $cert->reference,
                'fne_token' => $cert->token,
                'fne_certified_at' => now(),
                'fne_balance_sticker' => $cert->balanceSticker,
                'fne_raw_response' => $cert->rawResponse,
                'status' => 'certified',
            ]);
        } catch (\RuntimeException $e) {
            $invoice->update(['status' => 'certification_failed']);
            throw $e;
        }
    }
}
