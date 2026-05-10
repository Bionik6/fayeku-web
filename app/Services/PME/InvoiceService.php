<?php

namespace App\Services\PME;

use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\PaymentMethod;
use App\Events\PME\InvoiceCreated;
use App\Models\Auth\Company;
use App\Models\PME\Invoice;
use App\Models\PME\Payment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvoiceService
{
    /**
     * @return Collection<int, Invoice>
     */
    public function openReceivables(Company $company): Collection
    {
        return Invoice::query()
            ->where('company_id', $company->id)
            ->whereIn('status', [
                InvoiceStatus::Sent,
                InvoiceStatus::Certified,
                InvoiceStatus::CertificationFailed,
                InvoiceStatus::PartiallyPaid,
                InvoiceStatus::Overdue,
            ])
            ->with([
                'client',
                'reminders' => fn ($query) => $query->orderByDesc('sent_at'),
            ])
            ->orderByRaw('COALESCE(due_at, issued_at) asc')
            ->get();
    }

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
     * @param  int  $discount  Discount value — percentage (e.g. 10) or fixed amount in smallest unit
     * @param  string  $discountType  'percent' or 'fixed'
     * @return array{subtotal: int, discount_amount: int, discounted_subtotal: int, tax_amount: int, total: int}
     */
    public function calculateInvoiceTotals(array $lines, int $taxRate = 0, int $discount = 0, string $discountType = 'percent'): array
    {
        $subtotal = 0;

        foreach ($lines as $line) {
            $subtotal += $this->calculateLineTotal($line);
        }

        if ($discount <= 0) {
            $discountAmount = 0;
        } elseif ($discountType === 'fixed') {
            $discountAmount = min($discount, $subtotal);
        } else {
            $discountAmount = (int) round($subtotal * $discount / 100);
        }

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
     * Resolve the final deposit amount in the smallest currency unit.
     * Capped at the total TTC (a deposit can never exceed the invoice).
     */
    public function resolveDepositAmount(int $total, ?int $deposit): int
    {
        if (! $deposit || $deposit <= 0 || $total <= 0) {
            return 0;
        }

        return min($deposit, $total);
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
        $discountType = $data['discount_type'] ?? 'percent';
        $depositAmount = isset($data['deposit_amount']) ? (int) $data['deposit_amount'] : 0;

        return DB::transaction(function () use ($company, $data, $lines, $taxRate, $discount, $discountType, $depositAmount) {
            $totals = $this->calculateInvoiceTotals($lines, $taxRate, $discount, $discountType);
            $resolvedDeposit = $this->resolveDepositAmount($totals['total'], $depositAmount);

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
                'discount_type' => $discountType,
                'amount_paid' => $resolvedDeposit,
                'deposit_amount' => $resolvedDeposit,
                'notes' => $data['notes'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'payment_details' => $data['payment_details'] ?? null,
                'reminders_enabled' => $data['reminders_enabled'] ?? true,
            ]);

            $this->createLines($invoice, $lines, $taxRate);
            $this->syncDepositPayment($invoice, $resolvedDeposit, $data);

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
        $discountType = $data['discount_type'] ?? 'percent';
        $depositAmount = isset($data['deposit_amount']) ? (int) $data['deposit_amount'] : 0;

        return DB::transaction(function () use ($invoice, $data, $lines, $taxRate, $discount, $discountType, $depositAmount) {
            $totals = $this->calculateInvoiceTotals($lines, $taxRate, $discount, $discountType);
            $resolvedDeposit = $this->resolveDepositAmount($totals['total'], $depositAmount);

            $invoice->update([
                'client_id' => $data['client_id'],
                'currency' => $data['currency'] ?? 'XOF',
                'issued_at' => $data['issued_at'],
                'due_at' => $data['due_at'],
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'discount' => $discount,
                'discount_type' => $discountType,
                'deposit_amount' => $resolvedDeposit,
                'notes' => $data['notes'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'payment_details' => $data['payment_details'] ?? null,
                'reminders_enabled' => $data['reminders_enabled'] ?? $invoice->reminders_enabled,
            ]);

            $invoice->lines()->delete();
            $this->createLines($invoice, $lines, $taxRate);
            $this->syncDepositPayment($invoice->fresh(), $resolvedDeposit, $data);
            $this->refreshAmountPaid($invoice->fresh());

            return $invoice->refresh();
        });
    }

    /**
     * Mark an invoice as sent. If an acompte covers the total, the invoice
     * is marked Paid; otherwise PartiallyPaid when amount_paid > 0.
     */
    public function markAsSent(Invoice $invoice): Invoice
    {
        $amountPaid = (int) $invoice->amount_paid;
        $total = (int) $invoice->total;

        $status = match (true) {
            $amountPaid >= $total && $total > 0 => InvoiceStatus::Paid,
            $amountPaid > 0 => InvoiceStatus::PartiallyPaid,
            default => InvoiceStatus::Sent,
        };

        $updates = [
            'status' => $status,
            'sent_at' => $invoice->sent_at ?? now(),
        ];

        if ($status === InvoiceStatus::Paid) {
            $updates['paid_at'] = $invoice->paid_at ?? now();
        }

        $invoice->update($updates);

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

    /**
     * Replace any existing deposit Payment with a fresh one matching the
     * resolved deposit amount. Removes the deposit Payment when amount is 0.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncDepositPayment(Invoice $invoice, int $resolvedDeposit, array $data): void
    {
        $invoice->payments()->where('is_deposit', true)->delete();

        if ($resolvedDeposit <= 0) {
            return;
        }

        Payment::query()->create([
            'invoice_id' => $invoice->id,
            'amount' => $resolvedDeposit,
            'is_deposit' => true,
            'paid_at' => $data['issued_at'] ?? now(),
            'method' => $this->mapPaymentMethod($data['payment_method'] ?? null),
            'notes' => __('Acompte versé à la création'),
            'recorded_by' => auth()->id(),
        ]);
    }

    /**
     * Recalculate amount_paid from the sum of all attached payments.
     */
    private function refreshAmountPaid(Invoice $invoice): void
    {
        $invoice->update([
            'amount_paid' => (int) $invoice->payments()->sum('amount'),
        ]);
    }

    /**
     * Map the invoice's payment_method string (used in the form: wave,
     * orange_money, cash, bank_transfer) to the PaymentMethod enum used
     * on the payments table.
     */
    private function mapPaymentMethod(?string $method): PaymentMethod
    {
        return match ($method) {
            'wave', 'orange_money' => PaymentMethod::MobileMoney,
            'bank_transfer' => PaymentMethod::Transfer,
            'cash' => PaymentMethod::Cash,
            default => PaymentMethod::Cash,
        };
    }
}
