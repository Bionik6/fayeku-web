<?php

namespace App\Services\PME;

use App\Models\PME\DunningTemplate;
use App\Models\PME\Invoice;
use Carbon\CarbonInterface;

class DunningTemplateRenderer
{
    public function render(DunningTemplate $template, Invoice $invoice): string
    {
        $client = $invoice->client;
        $company = $invoice->company;

        $currency = $invoice->currency ?? 'XOF';

        $replacements = [
            '{contact_name}' => $client?->name ?? 'Cher client',
            '{invoice_reference}' => $invoice->reference ?? '',
            '{amount}' => CurrencyService::format((int) $invoice->total, $currency, withLabel: false),
            '{currency}' => CurrencyService::label($currency),
            '{due_date}' => $invoice->due_at instanceof CarbonInterface
                ? $invoice->due_at->locale('fr')->translatedFormat('d F Y')
                : '',
            '{signature}' => $company?->name ?? '',
        ];

        return strtr($template->body, $replacements);
    }
}
