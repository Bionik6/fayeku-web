<?php

namespace App\Http\Controllers\PME;

use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Models\PME\InvoiceLine;
use App\Services\PME\InvoiceService;
use App\Services\PME\PdfService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Streams an in-memory PDF preview of an invoice from cached form data —
 * never persists anything to the database. Used by the create/edit form
 * "Aperçu PDF" button so a user can preview without leaving a draft behind.
 */
class InvoicePreviewController
{
    public const CACHE_PREFIX = 'pme.preview.invoice.';

    public function __invoke(string $tempId, PdfService $pdf, InvoiceService $service): Response
    {
        $payload = Cache::get(self::CACHE_PREFIX.$tempId);
        abort_unless(is_array($payload), 404);

        $company = Company::query()->find($payload['company_id'] ?? null);
        abort_unless($company, 404);
        abort_unless(
            (bool) auth()->user()?->companies()->where('companies.id', $company->id)->exists(),
            403,
        );

        $invoice = $this->buildPreviewInvoice($payload, $company, $service);

        return $pdf->stream($invoice);
    }

    /**
     * @param  array{company_id: string, data: array<string, mixed>, lines: array<int, array<string, mixed>>}  $payload
     */
    private function buildPreviewInvoice(array $payload, Company $company, InvoiceService $service): Invoice
    {
        $data = $payload['data'];
        $lines = $payload['lines'];

        $taxRate = (int) ($data['tax_rate'] ?? 0);
        $totals = $service->calculateInvoiceTotals(
            $lines,
            $taxRate,
            (int) ($data['discount'] ?? 0),
            $data['discount_type'] ?? 'percent',
        );
        $deposit = $service->resolveDepositAmount($totals['total'], (int) ($data['deposit_amount'] ?? 0));

        $invoice = new Invoice([
            'company_id' => $company->id,
            'client_id' => $data['client_id'] ?? null,
            'reference' => $data['reference'] ?? '—',
            'currency' => $data['currency'] ?? 'XOF',
            'issued_at' => $data['issued_at'] ?? null,
            'due_at' => $data['due_at'] ?? null,
            'subtotal' => $totals['subtotal'],
            'tax_amount' => $totals['tax_amount'],
            'total' => $totals['total'],
            'discount' => (int) ($data['discount'] ?? 0),
            'discount_type' => $data['discount_type'] ?? 'percent',
            'amount_paid' => $deposit,
            'deposit_amount' => $deposit,
            'notes' => $data['notes'] ?? null,
            'payment_method' => $data['payment_method'] ?? null,
            'payment_details' => $data['payment_details'] ?? null,
        ]);

        $invoice->setRelation('company', $company);

        $client = ! empty($data['client_id'])
            ? Client::query()->where('id', $data['client_id'])->where('company_id', $company->id)->first()
            : null;
        $invoice->setRelation('client', $client);

        $linesCollection = collect($lines)->map(function (array $line) use ($taxRate) {
            return new InvoiceLine([
                'description' => (string) ($line['description'] ?? ''),
                'quantity' => (int) ($line['quantity'] ?? 0),
                'unit_price' => (int) ($line['unit_price'] ?? 0),
                'tax_rate' => $taxRate,
                'total' => (int) ($line['quantity'] ?? 0) * (int) ($line['unit_price'] ?? 0),
            ]);
        });
        $invoice->setRelation('lines', $linesCollection);

        return $invoice;
    }
}
