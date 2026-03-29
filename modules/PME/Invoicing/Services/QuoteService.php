<?php

namespace Modules\PME\Invoicing\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Auth\Models\Company;
use Modules\PME\Invoicing\Enums\InvoiceStatus;
use Modules\PME\Invoicing\Enums\QuoteStatus;
use Modules\PME\Invoicing\Events\QuoteAccepted;
use Modules\PME\Invoicing\Models\Invoice;
use Modules\PME\Invoicing\Models\Quote;

class QuoteService
{
    /**
     * Generate a unique reference in the format FYK-DEV-XXXXXX.
     */
    public function generateReference(Company $company): string
    {
        do {
            $code = Str::upper(Str::random(6));
            $reference = "FYK-DEV-{$code}";
        } while (
            Quote::query()
                ->where('company_id', $company->id)
                ->where('reference', $reference)
                ->exists()
        );

        return $reference;
    }

    /**
     * Calculate a single line's total (qty x unit_price).
     *
     * @param  array{quantity: int, unit_price: int}  $line
     */
    public function calculateLineTotal(array $line): int
    {
        return (int) $line['quantity'] * (int) $line['unit_price'];
    }

    /**
     * Calculate quote-level totals from lines + global discount + global tax rate.
     *
     * @param  array<int, array{quantity: int, unit_price: int}>  $lines
     * @param  int  $taxRate  Global tax rate percentage (e.g. 18)
     * @param  int  $discount  Global discount percentage (e.g. 10)
     * @return array{subtotal: int, discount_amount: int, discounted_subtotal: int, tax_amount: int, total: int}
     */
    public function calculateQuoteTotals(array $lines, int $taxRate = 0, int $discount = 0): array
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
     * Create a quote with its lines inside a transaction.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function create(Company $company, array $data, array $lines): Quote
    {
        $taxRate = (int) ($data['tax_rate'] ?? 0);
        $discount = (int) ($data['discount'] ?? 0);

        return DB::transaction(function () use ($company, $data, $lines, $taxRate, $discount) {
            $totals = $this->calculateQuoteTotals($lines, $taxRate, $discount);

            $quote = Quote::query()->create([
                'company_id' => $company->id,
                'client_id' => $data['client_id'],
                'reference' => $data['reference'],
                'currency' => $data['currency'] ?? 'XOF',
                'status' => QuoteStatus::Draft,
                'issued_at' => $data['issued_at'],
                'valid_until' => $data['valid_until'],
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'discount' => $discount,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->createLines($quote, $lines, $taxRate);

            return $quote;
        });
    }

    /**
     * Update an existing quote and replace its lines.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function update(Quote $quote, array $data, array $lines): Quote
    {
        $taxRate = (int) ($data['tax_rate'] ?? 0);
        $discount = (int) ($data['discount'] ?? 0);

        return DB::transaction(function () use ($quote, $data, $lines, $taxRate, $discount) {
            $totals = $this->calculateQuoteTotals($lines, $taxRate, $discount);

            $quote->update([
                'client_id' => $data['client_id'],
                'currency' => $data['currency'] ?? 'XOF',
                'issued_at' => $data['issued_at'],
                'valid_until' => $data['valid_until'],
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'discount' => $discount,
                'notes' => $data['notes'] ?? null,
            ]);

            $quote->lines()->delete();
            $this->createLines($quote, $lines, $taxRate);

            return $quote->refresh();
        });
    }

    /**
     * Mark a quote as sent.
     */
    public function markAsSent(Quote $quote): Quote
    {
        $quote->update(['status' => QuoteStatus::Sent]);

        return $quote;
    }

    /**
     * Mark a quote as accepted.
     */
    public function markAsAccepted(Quote $quote): Quote
    {
        $quote->update(['status' => QuoteStatus::Accepted]);

        return $quote;
    }

    /**
     * Mark a quote as declined.
     */
    public function markAsDeclined(Quote $quote): Quote
    {
        $quote->update(['status' => QuoteStatus::Declined]);

        return $quote;
    }

    /**
     * Determine if a quote can be edited.
     */
    public function canEdit(Quote $quote): bool
    {
        return in_array($quote->status, [
            QuoteStatus::Draft,
            QuoteStatus::Sent,
        ]);
    }

    /**
     * Convert a quote into a new draft invoice.
     */
    public function convertToInvoice(Quote $quote, Company $company): Invoice
    {
        abort_if(
            Invoice::query()->where('quote_id', $quote->id)->exists(),
            409,
            'Ce devis a déjà été converti en facture.'
        );

        $invoiceService = app(InvoiceService::class);

        return DB::transaction(function () use ($quote, $company, $invoiceService) {
            $reference = $invoiceService->generateReference($company);

            $invoice = Invoice::query()->create([
                'company_id' => $company->id,
                'client_id' => $quote->client_id,
                'quote_id' => $quote->id,
                'reference' => $reference,
                'currency' => $quote->currency ?? 'XOF',
                'status' => InvoiceStatus::Draft,
                'issued_at' => now()->toDateString(),
                'due_at' => now()->addDays(30)->toDateString(),
                'subtotal' => $quote->subtotal,
                'tax_amount' => $quote->tax_amount,
                'total' => $quote->total,
                'discount' => $quote->discount ?? 0,
                'amount_paid' => 0,
                'notes' => $quote->notes,
            ]);

            foreach ($quote->lines as $line) {
                $invoice->lines()->create([
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'tax_rate' => $line->tax_rate,
                    'total' => $line->total,
                ]);
            }

            if ($quote->status === QuoteStatus::Sent) {
                $quote->update(['status' => QuoteStatus::Accepted]);
            }

            QuoteAccepted::dispatch($quote, $invoice);

            return $invoice;
        });
    }

    /**
     * Create quote lines from an array.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function createLines(Quote $quote, array $lines, int $taxRate = 0): void
    {
        foreach ($lines as $line) {
            $quote->lines()->create([
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
