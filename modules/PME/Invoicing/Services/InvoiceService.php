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
     * Calculate a single line's total (qty × unit_price).
     *
     * @param  array{quantity: int, unit_price: int}  $line
     */
    public function calculateLineTotal(array $line): int
    {
        return (int) $line['quantity'] * (int) $line['unit_price'];
    }

    /**
     * Calculate invoice-level totals from lines + global discount + global tax rate.
     *
     * Order: subtotal → discount → discountedSubtotal → TVA → total
     *
     * @param  array<int, array{quantity: int, unit_price: int}>  $lines
     * @param  int  $taxRate  Global tax rate percentage (e.g. 18)
     * @param  int  $discount  Global discount percentage (e.g. 10)
     * @return array{subtotal: int, discount_amount: int, discounted_subtotal: int, tax_amount: int, total: int}
     */
    public function calculateInvoiceTotals(array $lines, int $taxRate = 0, int $discount = 0): array
    {
        $subtotal = 0;

        foreach ($lines as $line) {
            $subtotal += $this->calculateLineTotal($line);
        }

        $discountAmount = $discount > 0 ? (int) round($subtotal * $discount / 100) : 0;
        $discountedSubtotal = $subtotal - $discountAmount;
        $taxAmount = $taxRate > 0 ? (int) round($discountedSubtotal * $taxRate / 100) : 0;

        return [
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'discounted_subtotal' => $discountedSubtotal,
            'tax_amount' => $taxAmount,
            'total' => $discountedSubtotal + $taxAmount,
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
        $discount = (int) ($data['discount'] ?? 0);

        return DB::transaction(function () use ($company, $data, $lines, $taxRate, $discount) {
            $totals = $this->calculateInvoiceTotals($lines, $taxRate, $discount);

            $invoice = Invoice::query()->create([
                'company_id' => $company->id,
                'client_id' => $data['client_id'],
                'reference' => $data['reference'],
                'currency' => $data['currency'] ?? 'XOF',
                'status' => InvoiceStatus::Draft,
                'issued_at' => $data['issued_at'],
                'due_at' => $data['due_at'],
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'discount' => $discount,
                'amount_paid' => 0,
                'notes' => $data['notes'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'payment_details' => $data['payment_details'] ?? null,
                'reminder_schedule' => $data['reminder_schedule'] ?? null,
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
        $discount = (int) ($data['discount'] ?? 0);

        return DB::transaction(function () use ($invoice, $data, $lines, $taxRate, $discount) {
            $totals = $this->calculateInvoiceTotals($lines, $taxRate, $discount);

            $invoice->update([
                'client_id' => $data['client_id'],
                'currency' => $data['currency'] ?? 'XOF',
                'issued_at' => $data['issued_at'],
                'due_at' => $data['due_at'],
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'discount' => $discount,
                'notes' => $data['notes'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'payment_details' => $data['payment_details'] ?? null,
                'reminder_schedule' => $data['reminder_schedule'] ?? null,
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
                'total' => $this->calculateLineTotal($line),
            ]);
        }
    }
}
