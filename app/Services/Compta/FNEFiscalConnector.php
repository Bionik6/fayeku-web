<?php

namespace App\Services\Compta;

use Illuminate\Support\Facades\Http;
use App\DTOs\Compta\FiscalCertification;
use App\DTOs\Compta\FneInvoicePayload;
use App\Enums\Compta\CertificationAuthority;
use App\Interfaces\Compta\FiscalConnectorInterface;
use App\Models\PME\Invoice;

class FNEFiscalConnector implements FiscalConnectorInterface
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
            authority: CertificationAuthority::FNE,
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

    public function authority(): CertificationAuthority
    {
        return CertificationAuthority::FNE;
    }
}
