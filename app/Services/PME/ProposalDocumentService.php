<?php

namespace App\Services\PME;

use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\ProposalDocumentStatus;
use App\Enums\PME\ProposalDocumentType;
use App\Events\PME\ProposalDocumentAccepted;
use App\Events\PME\ProposalDocumentConverted;
use App\Models\Auth\Company;
use App\Models\PME\Invoice;
use App\Models\PME\ProposalDocument;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProposalDocumentService
{
    /**
     * Generate a unique reference (FYK-DEV-XXXXXX or FYK-PRO-XXXXXX) scoped to the company.
     */
    public function generateReference(Company $company, ProposalDocumentType $type): string
    {
        do {
            $code = Str::upper(Str::random(6));
            $reference = $type->referencePrefix().$code;
        } while (
            ProposalDocument::query()
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
     * Calculate document-level totals from lines + global discount + global tax rate.
     *
     * Order: subtotal → discount → discountedSubtotal → TVA → total
     *
     * @param  array<int, array{quantity: int, unit_price: int}>  $lines
     * @return array{subtotal: int, discount_amount: int, discounted_subtotal: int, tax_amount: int, total: int}
     */
    public function calculateTotals(array $lines, int $taxRate = 0, int $discount = 0, string $discountType = 'percent'): array
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
     * Create a proposal document with its lines inside a transaction.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function create(Company $company, ProposalDocumentType $type, array $data, array $lines): ProposalDocument
    {
        $taxRate = (int) ($data['tax_rate'] ?? 0);
        $discount = (int) ($data['discount'] ?? 0);
        $discountType = $data['discount_type'] ?? 'percent';

        return DB::transaction(function () use ($company, $type, $data, $lines, $taxRate, $discount, $discountType) {
            $totals = $this->calculateTotals($lines, $taxRate, $discount, $discountType);

            $payload = [
                'company_id' => $company->id,
                'client_id' => $data['client_id'],
                'type' => $type,
                'reference' => $data['reference'],
                'currency' => $data['currency'] ?? 'XOF',
                'status' => ProposalDocumentStatus::Draft,
                'issued_at' => $data['issued_at'],
                'valid_until' => $data['valid_until'],
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'discount' => $discount,
                'discount_type' => $discountType,
                'notes' => $data['notes'] ?? null,
            ];

            if ($type === ProposalDocumentType::Proforma) {
                $payload['dossier_reference'] = $data['dossier_reference'] ?? null;
                $payload['payment_terms'] = $data['payment_terms'] ?? null;
                $payload['delivery_terms'] = $data['delivery_terms'] ?? null;
            }

            $document = ProposalDocument::query()->create($payload);

            $this->createLines($document, $lines, $taxRate);

            return $document;
        });
    }

    /**
     * Update an existing document and replace its lines.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function update(ProposalDocument $document, array $data, array $lines): ProposalDocument
    {
        $taxRate = (int) ($data['tax_rate'] ?? 0);
        $discount = (int) ($data['discount'] ?? 0);
        $discountType = $data['discount_type'] ?? 'percent';

        return DB::transaction(function () use ($document, $data, $lines, $taxRate, $discount, $discountType) {
            $totals = $this->calculateTotals($lines, $taxRate, $discount, $discountType);

            $payload = [
                'client_id' => $data['client_id'],
                'currency' => $data['currency'] ?? 'XOF',
                'issued_at' => $data['issued_at'],
                'valid_until' => $data['valid_until'],
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'discount' => $discount,
                'discount_type' => $discountType,
                'notes' => $data['notes'] ?? null,
            ];

            if ($document->isProforma()) {
                $payload['dossier_reference'] = $data['dossier_reference'] ?? null;
                $payload['payment_terms'] = $data['payment_terms'] ?? null;
                $payload['delivery_terms'] = $data['delivery_terms'] ?? null;
            }

            $document->update($payload);
            $document->lines()->delete();
            $this->createLines($document, $lines, $taxRate);

            return $document->refresh();
        });
    }

    public function markAsSent(ProposalDocument $document): ProposalDocument
    {
        $document->update([
            'status' => ProposalDocumentStatus::Sent,
            'sent_at' => $document->sent_at ?? now(),
        ]);

        return $document;
    }

    public function markAsAccepted(ProposalDocument $document): ProposalDocument
    {
        $this->assertTypeAllows($document, ProposalDocumentStatus::Accepted);
        $document->update([
            'status' => ProposalDocumentStatus::Accepted,
            'accepted_at' => $document->accepted_at ?? now(),
        ]);

        ProposalDocumentAccepted::dispatch($document);

        return $document;
    }

    /**
     * Mark a proforma as having received the purchase order (BC).
     *
     * @param  array{reference?: string|null, received_at?: string|Carbon|null, notes?: string|null}  $purchaseOrderData
     */
    public function markAsPoReceived(ProposalDocument $document, array $purchaseOrderData = []): ProposalDocument
    {
        $this->assertTypeAllows($document, ProposalDocumentStatus::PoReceived);

        $update = ['status' => ProposalDocumentStatus::PoReceived];

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

        $document->update($update);

        return $document;
    }

    public function markAsDeclined(ProposalDocument $document): ProposalDocument
    {
        $document->update([
            'status' => ProposalDocumentStatus::Declined,
            'declined_at' => $document->declined_at ?? now(),
        ]);

        return $document;
    }

    /**
     * Annule un devis ou une proforma avec un motif obligatoire. Refuse si le
     * document est déjà converti — l'annulation passe alors par la facture.
     */
    public function markAsCancelled(ProposalDocument $document, string $reason): ProposalDocument
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('Le motif d\'annulation est requis.');
        }

        if (in_array($document->status, [
            ProposalDocumentStatus::Converted,
            ProposalDocumentStatus::Cancelled,
        ], true)) {
            throw new DomainException(
                $document->isProforma()
                    ? 'Cette proforma ne peut plus être annulée.'
                    : 'Ce devis ne peut plus être annulé.'
            );
        }

        $document->update([
            'status' => ProposalDocumentStatus::Cancelled,
            'cancelled_at' => $document->cancelled_at ?? now(),
            'cancellation_reason' => $reason,
        ]);

        return $document;
    }

    /**
     * Prolonge la date de validité d'un devis/proforma. Si le document était
     * expiré (statut Expired ou Sent avec date dépassée), revient en Sent.
     */
    public function extendValidity(ProposalDocument $document, CarbonInterface $newValidUntil): ProposalDocument
    {
        if ($document->valid_until && $newValidUntil->lte($document->valid_until)) {
            throw new DomainException('La nouvelle date doit être postérieure à l\'actuelle.');
        }

        $update = [
            'valid_until' => $newValidUntil->toDateString(),
            'validity_extended_at' => now(),
        ];

        $wasExpired = $document->status === ProposalDocumentStatus::Expired
            || ($document->status === ProposalDocumentStatus::Sent
                && $document->valid_until
                && $document->valid_until->isPast());

        if ($wasExpired) {
            $update['status'] = ProposalDocumentStatus::Sent;
        }

        $document->update($update);

        return $document;
    }

    /**
     * Archive un document terminal (Refusé, Expiré, Annulé).
     */
    public function archive(ProposalDocument $document): ProposalDocument
    {
        if (! in_array($document->status, ProposalDocumentStatus::archivable(), true)) {
            throw new DomainException('Seuls les documents refusés, expirés ou annulés peuvent être archivés.');
        }

        $document->update(['archived_at' => $document->archived_at ?? now()]);

        return $document;
    }

    /**
     * Duplique un document en nouveau brouillon avec une nouvelle référence et
     * des dates d'émission/validité rafraîchies. Les lignes et les notes sont
     * copiées, mais l'historique (sent_at, accepted_at, etc.) est remis à zéro.
     */
    public function duplicate(ProposalDocument $document, Company $company): ProposalDocument
    {
        $defaultValidityDays = (int) config('fayeku.proposal_default_validity_days', 30);

        return DB::transaction(function () use ($document, $company, $defaultValidityDays) {
            $copy = ProposalDocument::query()->create([
                'company_id' => $company->id,
                'client_id' => $document->client_id,
                'type' => $document->type,
                'reference' => $this->generateReference($company, $document->type),
                'currency' => $document->currency,
                'status' => ProposalDocumentStatus::Draft,
                'issued_at' => now()->toDateString(),
                'valid_until' => now()->addDays($defaultValidityDays)->toDateString(),
                'subtotal' => $document->subtotal,
                'tax_amount' => $document->tax_amount,
                'total' => $document->total,
                'discount' => $document->discount,
                'discount_type' => $document->discount_type,
                'notes' => $document->notes,
                'dossier_reference' => $document->dossier_reference,
                'payment_terms' => $document->payment_terms,
                'delivery_terms' => $document->delivery_terms,
            ]);

            foreach ($document->lines as $line) {
                $copy->lines()->create([
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'tax_rate' => $line->tax_rate,
                    'discount' => $line->discount,
                    'total' => $line->total,
                ]);
            }

            return $copy->refresh();
        });
    }

    public function canEdit(ProposalDocument $document): bool
    {
        return in_array($document->status, ProposalDocumentStatus::editable(), true);
    }

    /**
     * Convert a proposal document into a new draft invoice.
     */
    public function convertToInvoice(ProposalDocument $document, Company $company): Invoice
    {
        abort_if(
            Invoice::query()->where('proposal_document_id', $document->id)->exists(),
            409,
            $document->isProforma()
                ? 'Cette proforma a déjà été convertie en facture.'
                : 'Ce devis a déjà été converti en facture.'
        );

        $invoiceService = app(InvoiceService::class);

        return DB::transaction(function () use ($document, $company, $invoiceService) {
            $reference = $invoiceService->generateReference($company);

            $invoice = Invoice::query()->create([
                'company_id' => $company->id,
                'client_id' => $document->client_id,
                'proposal_document_id' => $document->id,
                'reference' => $reference,
                'currency' => $document->currency ?? 'XOF',
                'status' => InvoiceStatus::Draft,
                'issued_at' => now()->toDateString(),
                'due_at' => now()->addDays(30)->toDateString(),
                'subtotal' => $document->subtotal,
                'tax_amount' => $document->tax_amount,
                'total' => $document->total,
                'discount' => $document->discount ?? 0,
                'discount_type' => $document->discount_type ?? 'percent',
                'amount_paid' => 0,
                'notes' => $document->notes,
                'payment_terms' => $document->isProforma() ? $document->payment_terms : null,
            ]);

            foreach ($document->lines as $line) {
                $invoice->lines()->create([
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'tax_rate' => $line->tax_rate,
                    'total' => $line->total,
                ]);
            }

            // Convertir vaut acceptation explicite. Pour les deux types, si le
            // document est en Brouillon ou Envoyé, on enregistre d'abord
            // l'acceptation (timestamp + event distinct), puis la conversion.
            // Cela garantit la double trace en historique même quand
            // l'utilisateur passe par le raccourci direct "Convertir".
            $needsAcceptance = $document->status !== ProposalDocumentStatus::Accepted
                && $document->status !== ProposalDocumentStatus::PoReceived
                && $document->status !== ProposalDocumentStatus::Converted;

            $now = now();
            $acceptedAt = $document->accepted_at ?? $now;

            if ($needsAcceptance) {
                $document->update([
                    'status' => ProposalDocumentStatus::Accepted,
                    'accepted_at' => $acceptedAt,
                ]);
                ProposalDocumentAccepted::dispatch($document->refresh());
            }

            if ($document->isQuote()) {
                $document->refresh();
            } else {
                $document->update([
                    'status' => ProposalDocumentStatus::Converted,
                    'converted_at' => $document->converted_at ?? $acceptedAt->copy()->addSecond(),
                ]);
            }

            ProposalDocumentConverted::dispatch($document->refresh(), $invoice);

            return $invoice;
        });
    }

    private function assertTypeAllows(ProposalDocument $document, ProposalDocumentStatus $target): void
    {
        if (! $target->isAllowedFor($document->type)) {
            throw new DomainException(
                "Le statut {$target->value} n'est pas autorisé pour un document de type {$document->type->value}."
            );
        }
    }

    /**
     * Create document lines from an array.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function createLines(ProposalDocument $document, array $lines, int $taxRate = 0): void
    {
        foreach ($lines as $line) {
            $document->lines()->create([
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
