<?php

namespace App\Services\PME;

use App\Enums\PME\InvoiceStatus;
use App\Models\PME\Invoice;
use App\Models\PME\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PaymentService
{
    /**
     * @param  array{amount: int, paid_at: \DateTimeInterface|string, method: string, reference?: ?string, notes?: ?string, proof_file_path?: ?string, recorded_by?: ?string}  $data
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
                'proof_file_path' => $data['proof_file_path'] ?? null,
                'recorded_by' => $data['recorded_by'] ?? null,
            ]);

            $this->refreshInvoiceTotals($invoice->fresh());

            return $payment;
        });
    }

    /**
     * Met à jour un paiement existant et resynchronise les totaux de la facture.
     * Lorsque `proof_file_path` est passé explicitement (clé présente, même à null),
     * l'ancienne preuve est retirée du storage si elle a changé / est supprimée.
     *
     * @param  array{amount?: int, paid_at?: \DateTimeInterface|string, method?: string, reference?: ?string, notes?: ?string, proof_file_path?: ?string}  $data
     */
    public function update(Payment $payment, array $data): Payment
    {
        return DB::transaction(function () use ($payment, $data) {
            $oldProof = $payment->proof_file_path;

            $updates = [];
            foreach (['amount', 'paid_at', 'method', 'reference', 'notes'] as $key) {
                if (array_key_exists($key, $data)) {
                    $updates[$key] = $key === 'amount' ? (int) $data[$key] : ($data[$key] ?: null);
                }
            }
            if (array_key_exists('proof_file_path', $data)) {
                $updates['proof_file_path'] = $data['proof_file_path'] ?: null;
            }

            $payment->update($updates);

            if (array_key_exists('proof_file_path', $data) && $oldProof && $oldProof !== ($data['proof_file_path'] ?? null)) {
                if (Storage::exists($oldProof)) {
                    Storage::delete($oldProof);
                }
            }

            if ($payment->invoice) {
                $this->refreshInvoiceTotals($payment->invoice->fresh());
            }

            return $payment->refresh();
        });
    }

    public function delete(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $invoice = $payment->invoice;
            $proofPath = $payment->proof_file_path;
            $payment->delete();

            if ($proofPath && Storage::exists($proofPath)) {
                Storage::delete($proofPath);
            }

            if ($invoice) {
                $this->refreshInvoiceTotals($invoice->fresh());
            }
        });
    }

    private function refreshInvoiceTotals(Invoice $invoice): void
    {
        $amountPaid = (int) $invoice->payments()->sum('amount');
        $depositAmount = (int) $invoice->payments()->where('is_deposit', true)->sum('amount');

        $updates = [
            'amount_paid' => $amountPaid,
            'deposit_amount' => $depositAmount,
        ];

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
