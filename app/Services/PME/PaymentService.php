<?php

namespace App\Services\PME;

use App\Enums\PME\InvoiceStatus;
use App\Models\PME\Invoice;
use App\Models\PME\Payment;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    /**
     * @param  array{amount: int, paid_at: \DateTimeInterface|string, method: string, reference?: ?string, notes?: ?string, recorded_by?: ?string}  $data
     */
    public function record(Invoice $invoice, array $data): Payment
    {
        return DB::transaction(function () use ($invoice, $data) {
            $payment = Payment::query()->create([
                'invoice_id' => $invoice->id,
                'amount' => (int) $data['amount'],
                'paid_at' => $data['paid_at'],
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $data['recorded_by'] ?? null,
            ]);

            $this->refreshInvoiceTotals($invoice->fresh());

            return $payment;
        });
    }

    public function delete(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $invoice = $payment->invoice;
            $payment->delete();

            if ($invoice) {
                $this->refreshInvoiceTotals($invoice->fresh());
            }
        });
    }

    private function refreshInvoiceTotals(Invoice $invoice): void
    {
        $amountPaid = (int) $invoice->payments()->sum('amount');

        $updates = ['amount_paid' => $amountPaid];

        if ($amountPaid >= $invoice->total && $invoice->total > 0) {
            $updates['status'] = InvoiceStatus::Paid;
            $updates['paid_at'] = $invoice->paid_at ?? now();
        } elseif ($amountPaid > 0) {
            $updates['status'] = InvoiceStatus::PartiallyPaid;
            $updates['paid_at'] = null;
        } else {
            if ($invoice->status === InvoiceStatus::Paid || $invoice->status === InvoiceStatus::PartiallyPaid) {
                $updates['status'] = $invoice->due_at && $invoice->due_at->isPast()
                    ? InvoiceStatus::Overdue
                    : InvoiceStatus::Sent;
            }
            $updates['paid_at'] = null;
        }

        $invoice->update($updates);
    }
}
