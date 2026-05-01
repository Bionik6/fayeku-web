<?php

namespace App\Services\PME;

use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\ProformaStatus;
use App\Events\PME\ProformaConverted;
use App\Models\Auth\Company;
use App\Models\PME\Invoice;
use App\Models\PME\Proforma;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProformaService
{
    /**
     * Generate a unique reference in the format FYK-PRO-XXXXXX.
     */
    public function generateReference(Company $company): string
    {
        do {
            $code = Str::upper(Str::random(6));
            $reference = "FYK-PRO-{$code}";
        } while (
            Proforma::query()
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
     * Calculate proforma-level totals from lines + global discount + global tax rate.
     *
     * Order: subtotal → discount → discountedSubtotal → TVA → total
     *
     * @param  array<int, array{quantity: int, unit_price: int}>  $lines
     * @param  int  $taxRate  Global tax rate percentage (e.g. 18)
     * @param  int  $discount  Discount value — percentage (e.g. 10) or fixed amount in smallest unit
     * @param  string  $discountType  'percent' or 'fixed'
     * @return array{subtotal: int, discount_amount: int, discounted_subtotal: int, tax_amount: int, total: int}
     */
    public function calculateProformaTotals(array $lines, int $taxRate = 0, int $discount = 0, string $discountType = 'percent'): array
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
     * Create a proforma with its lines inside a transaction.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function create(Company $company, array $data, array $lines): Proforma
    {
        $taxRate = (int) ($data['tax_rate'] ?? 0);
        $discount = (int) ($data['discount'] ?? 0);
        $discountType = $data['discount_type'] ?? 'percent';

        return DB::transaction(function () use ($company, $data, $lines, $taxRate, $discount, $discountType) {
            $totals = $this->calculateProformaTotals($lines, $taxRate, $discount, $discountType);

            $proforma = Proforma::query()->create([
                'company_id' => $company->id,
                'client_id' => $data['client_id'],
                'reference' => $data['reference'],
                'currency' => $data['currency'] ?? 'XOF',
                'status' => ProformaStatus::Draft,
                'issued_at' => $data['issued_at'],
                'valid_until' => $data['valid_until'],
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'discount' => $discount,
                'discount_type' => $discountType,
                'dossier_reference' => $data['dossier_reference'] ?? null,
                'payment_terms' => $data['payment_terms'] ?? null,
                'delivery_terms' => $data['delivery_terms'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->createLines($proforma, $lines, $taxRate);

            return $proforma;
        });
    }

    /**
     * Update an existing proforma and replace its lines.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function update(Proforma $proforma, array $data, array $lines): Proforma
    {
        $taxRate = (int) ($data['tax_rate'] ?? 0);
        $discount = (int) ($data['discount'] ?? 0);
        $discountType = $data['discount_type'] ?? 'percent';

        return DB::transaction(function () use ($proforma, $data, $lines, $taxRate, $discount, $discountType) {
            $totals = $this->calculateProformaTotals($lines, $taxRate, $discount, $discountType);

            $proforma->update([
                'client_id' => $data['client_id'],
                'currency' => $data['currency'] ?? 'XOF',
                'issued_at' => $data['issued_at'],
                'valid_until' => $data['valid_until'],
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'discount' => $discount,
                'discount_type' => $discountType,
                'dossier_reference' => $data['dossier_reference'] ?? null,
                'payment_terms' => $data['payment_terms'] ?? null,
                'delivery_terms' => $data['delivery_terms'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $proforma->lines()->delete();
            $this->createLines($proforma, $lines, $taxRate);

            return $proforma->refresh();
        });
    }

    /**
     * Mark a proforma as sent.
     */
    public function markAsSent(Proforma $proforma): Proforma
    {
        $proforma->update(['status' => ProformaStatus::Sent]);

        return $proforma;
    }

    /**
     * Mark a proforma as having received the purchase order (BC).
     *
     * @param  array{reference?: string|null, received_at?: string|Carbon|null, notes?: string|null}  $purchaseOrderData
     */
    public function markAsPoReceived(Proforma $proforma, array $purchaseOrderData = []): Proforma
    {
        $update = ['status' => ProformaStatus::PoReceived];

        if (array_key_exists('reference', $purchaseOrderData)) {
            $update['po_reference'] = $purchaseOrderData['reference'] !== ''
                ? $purchaseOrderData['reference']
                : null;
        }

        if (array_key_exists('received_at', $purchaseOrderData)) {
            $update['po_received_at'] = $purchaseOrderData['received_at'] ?: null;
        }

        if (array_key_exists('notes', $purchaseOrderData)) {
            $update['po_notes'] = $purchaseOrderData['notes'] !== ''
                ? $purchaseOrderData['notes']
                : null;
        }

        $proforma->update($update);

        return $proforma;
    }

    /**
     * Mark a proforma as declined.
     */
    public function markAsDeclined(Proforma $proforma): Proforma
    {
        $proforma->update(['status' => ProformaStatus::Declined]);

        return $proforma;
    }

    /**
     * Determine if a proforma can be edited.
     */
    public function canEdit(Proforma $proforma): bool
    {
        return in_array($proforma->status, [
            ProformaStatus::Draft,
            ProformaStatus::Sent,
        ]);
    }

    /**
     * Convert a proforma into a new draft invoice.
     */
    public function convertToInvoice(Proforma $proforma, Company $company): Invoice
    {
        abort_if(
            Invoice::query()->where('proforma_id', $proforma->id)->exists(),
            409,
            'Cette proforma a déjà été convertie en facture.'
        );

        $invoiceService = app(InvoiceService::class);

        return DB::transaction(function () use ($proforma, $company, $invoiceService) {
            $reference = $invoiceService->generateReference($company);

            $invoice = Invoice::query()->create([
                'company_id' => $company->id,
                'client_id' => $proforma->client_id,
                'proforma_id' => $proforma->id,
                'reference' => $reference,
                'currency' => $proforma->currency ?? 'XOF',
                'status' => InvoiceStatus::Draft,
                'issued_at' => now()->toDateString(),
                'due_at' => now()->addDays(30)->toDateString(),
                'subtotal' => $proforma->subtotal,
                'tax_amount' => $proforma->tax_amount,
                'total' => $proforma->total,
                'discount' => $proforma->discount ?? 0,
                'discount_type' => $proforma->discount_type ?? 'percent',
                'amount_paid' => 0,
                'notes' => $proforma->notes,
                'payment_terms' => $proforma->payment_terms,
            ]);

            foreach ($proforma->lines as $line) {
                $invoice->lines()->create([
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'tax_rate' => $line->tax_rate,
                    'total' => $line->total,
                ]);
            }

            $proforma->update(['status' => ProformaStatus::Converted]);

            ProformaConverted::dispatch($proforma, $invoice);

            return $invoice;
        });
    }

    /**
     * Create proforma lines from an array.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function createLines(Proforma $proforma, array $lines, int $taxRate = 0): void
    {
        foreach ($lines as $line) {
            $proforma->lines()->create([
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
