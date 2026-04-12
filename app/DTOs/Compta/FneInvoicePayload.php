<?php

namespace App\DTOs\Compta;

use App\Models\PME\Invoice;

class FneInvoicePayload
{
    public static function fromInvoice(Invoice $invoice): array
    {
        return [
            'invoiceType' => 'sale',
            'paymentMethod' => 'mobile-money',
            'template' => 'B2B',
            'clientNcc' => $invoice->client?->tax_id,
            'clientCompanyName' => $invoice->client?->name ?? '',
            'clientPhone' => $invoice->client?->phone ?? '',
            'clientEmail' => $invoice->client?->email ?? '',
            'pointOfSale' => $invoice->company->name,
            'establishment' => $invoice->company->name,
            'items' => $invoice->lines->map(fn ($l) => [
                'taxes' => ['TVA'],
                'description' => $l->description,
                'quantity' => $l->quantity,
                'amount' => $l->unit_price,
                'discount' => 0,
                'measurementUnit' => 'pcs',
            ])->toArray(),
        ];
    }
}
