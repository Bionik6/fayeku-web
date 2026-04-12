<?php

namespace App\Services\Compta;

use App\Interfaces\Compta\FiscalConnectorInterface;
use App\Enums\PME\InvoiceStatus;
use App\Models\PME\Invoice;

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

            $data = array_filter([
                'reference' => $cert->reference,
                'token' => $cert->token,
                'certified_at' => now()->toIso8601String(),
                'balance_sticker' => $cert->balanceSticker,
                'raw_response' => $cert->rawResponse ?: null,
            ], fn ($v) => $v !== null);

            $invoice->update([
                'certification_authority' => $cert->authority->value,
                'certification_data' => $data,
                'status' => InvoiceStatus::Certified,
            ]);
        } catch (\RuntimeException $e) {
            $invoice->update(['status' => InvoiceStatus::CertificationFailed]);
            throw $e;
        }
    }
}
