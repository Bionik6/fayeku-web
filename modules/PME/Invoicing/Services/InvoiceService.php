<?php

namespace Modules\PME\Invoicing\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Auth\Models\Company;
use Modules\PME\Invoicing\Enums\InvoiceStatus;
use Modules\PME\Invoicing\Events\InvoiceCreated;
use Modules\PME\Invoicing\Models\Invoice;

class InvoiceService
{
    /**
     * Generate a unique reference in the format FYK-FAC-XXXXXX.
     */
    public function generateReference(Company $company): string
    {
        do {
            $code = Str::upper(Str::random(6));
            $reference = "FYK-FAC-{$code}";
        } while (
            Invoice::query()
                ->where('company_id', $company->id)
                ->where('reference', $reference)
                ->exists()
        );

        return $reference;
    }

    /**
     * Calculate a single line's total (HT after discount).
     *
     * @param  array{quantity: int, unit_price: int, discount: int}  $line
     * @return int Total HT after discount
     */
    public function calculateLineTotal(array $line): int
    {
        $subtotal = (int) $line['quantity'] * (int) $line['unit_price'];
        $discount = (int) ($line['discount'] ?? 0);

        if ($discount > 0) {
            $subtotal = (int) round($subtotal * (100 - $discount) / 100);
        }

        return $subtotal;
    }

    /**
     * Calculate invoice-level totals from lines + global tax rate.
     *
     * @param  array<int, array{quantity: int, unit_price: int, discount: int}>  $lines
     * @param  int  $taxRate  Global tax rate percentage (e.g. 18)
     * @return array{subtotal: int, tax_amount: int, total: int}
     */
    public function calculateInvoiceTotals(array $lines, int $taxRate = 0): array
    {
        $subtotal = 0;

        foreach ($lines as $line) {
            $subtotal += $this->calculateLineTotal($line);
        }

        $taxAmount = $taxRate > 0 ? (int) round($subtotal * $taxRate / 100) : 0;

        return [
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $subtotal + $taxAmount,
        ];
    }

    /**
     * Create an invoice with its lines inside a transaction.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function create(Company $company, array $data, array $lines): Invoice
    {
        $taxRate = (int) ($data['tax_rate'] ?? 0);

        return DB::transaction(function () use ($company, $data, $lines, $taxRate) {
            $totals = $this->calculateInvoiceTotals($lines, $taxRate);

            $invoice = Invoice::query()->create([
                'company_id' => $company->id,
                'client_id' => $data['client_id'],
                'reference' => $data['reference'],
                'subject' => $data['subject'] ?? null,
                'currency' => $data['currency'] ?? 'XOF',
                'status' => InvoiceStatus::Draft,
                'issued_at' => $data['issued_at'],
                'due_at' => $data['due_at'],
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'amount_paid' => 0,
                'notes' => $data['notes'] ?? null,
                'payment_terms' => $data['payment_terms'] ?? null,
                'payment_instructions' => $data['payment_instructions'] ?? null,
            ]);

            $this->createLines($invoice, $lines, $taxRate);

            InvoiceCreated::dispatch($invoice);

            return $invoice;
        });
    }

    /**
     * Update an existing invoice and replace its lines.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function update(Invoice $invoice, array $data, array $lines): Invoice
    {
        $taxRate = (int) ($data['tax_rate'] ?? 0);

        return DB::transaction(function () use ($invoice, $data, $lines, $taxRate) {
            $totals = $this->calculateInvoiceTotals($lines, $taxRate);

            $invoice->update([
                'client_id' => $data['client_id'],
                'subject' => $data['subject'] ?? null,
                'currency' => $data['currency'] ?? 'XOF',
                'issued_at' => $data['issued_at'],
                'due_at' => $data['due_at'],
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'notes' => $data['notes'] ?? null,
                'payment_terms' => $data['payment_terms'] ?? null,
                'payment_instructions' => $data['payment_instructions'] ?? null,
            ]);

            $invoice->lines()->delete();
            $this->createLines($invoice, $lines, $taxRate);

            return $invoice->refresh();
        });
    }

    /**
     * Mark an invoice as sent.
     */
    public function markAsSent(Invoice $invoice): Invoice
    {
        $invoice->update(['status' => InvoiceStatus::Sent]);

        return $invoice;
    }

    /**
     * Determine if an invoice can be edited.
     */
    public function canEdit(Invoice $invoice): bool
    {
        return in_array($invoice->status, [
            InvoiceStatus::Draft,
            InvoiceStatus::Sent,
        ]);
    }

    /**
     * Create invoice lines from an array.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function createLines(Invoice $invoice, array $lines, int $taxRate = 0): void
    {
        foreach ($lines as $line) {
            $invoice->lines()->create([
                'description' => $line['description'],
                'quantity' => (int) $line['quantity'],
                'unit_price' => (int) $line['unit_price'],
                'tax_rate' => $taxRate,
                'discount' => (int) ($line['discount'] ?? 0),
                'total' => $this->calculateLineTotal($line),
            ]);
        }
    }
}
