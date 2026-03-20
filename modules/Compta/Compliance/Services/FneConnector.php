<?php

namespace Modules\Compta\Compliance\Services;

use Illuminate\Support\Facades\Http;
use Modules\Compta\Compliance\DTOs\FiscalCertification;
use Modules\Compta\Compliance\DTOs\FneInvoicePayload;
use Modules\Compta\Compliance\Interfaces\FiscalConnectorInterface;
use Modules\PME\Invoicing\Models\Invoice;

class FneConnector implements FiscalConnectorInterface
{
    public function certify(Invoice $invoice): FiscalCertification
    {
        $url = rtrim(config('fayeku.fne_api_url'), '/\\');

        $response = Http::withToken(env('FNE_API_KEY'))
            ->post("{$url}/external/invoices/sign", FneInvoicePayload::fromInvoice($invoice));

        if (! $response->successful()) {
            throw new \RuntimeException('FNE certification failed: '.$response->body());
        }

        $data = $response->json();

        return new FiscalCertification(
            reference: $data['reference'],
            token: $data['token'],
            balanceSticker: $data['balance_sticker'] ?? null,
            rawResponse: $data,
        );
    }

    public function supportsCountry(string $countryCode): bool
    {
        return $countryCode === 'CI';
    }
}
