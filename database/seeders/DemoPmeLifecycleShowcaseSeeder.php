<?php

namespace Database\Seeders;

use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\ProposalDocumentStatus;
use App\Enums\PME\ProposalDocumentType;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Models\PME\InvoiceLine;
use App\Models\PME\ProposalDocument;
use App\Models\PME\ProposalDocumentLine;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Crée un panel exhaustif de documents pour valider visuellement la carte
 * <x-documents.lifecycle-card> sur les pages show de Diop Services SARL :
 *
 * - 6 factures couvrant Brouillon, Envoyée, Envoyée+En retard (dérivé),
 *   Partiellement payée, Payée, Annulée
 * - 6 devis couvrant Brouillon, Envoyé, Accepté, Facturé (lié à une facture),
 *   Refusé, Expiré (dérivé d'un Sent dont la validité est dépassée)
 * - 6 proformas couvrant Brouillon, Envoyée, BC reçu, Facturée (lié à
 *   une facture), Refusée, Expirée (dérivée)
 *
 * Toutes les références utilisent le préfixe `LC` (Lifecycle) suivi du
 * scénario pour faciliter le repérage : FYK-FAC-LC-OVERDUE, FYK-DEV-LC-EXPIRED…
 */
class DemoPmeLifecycleShowcaseSeeder extends Seeder
{
    private Company $company;

    private Client $client;

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->company = Company::query()
                ->where('type', 'sme')
                ->where('name', 'Diop Services SARL')
                ->firstOrFail();

            $this->client = Client::create([
                'company_id' => $this->company->id,
                'name' => 'Cycle de vie Showcase SARL',
                'phone' => '+221338600999',
                'email' => 'showcase@cycle-de-vie.test',
                'address' => 'Rue Showcase, Plateau, Dakar',
                'tax_id' => 'SN2026SHC0099',
                'rccm' => 'SN-DKR-2026-B-99999',
            ]);

            $this->seedInvoices();
            $this->seedQuotes();
            $this->seedProformas();
        });
    }

    private function seedInvoices(): void
    {
        // Brouillon — non encore envoyée
        $this->invoice('FYK-FAC-LC-DRAFT', InvoiceStatus::Draft, 1_000_000,
            issuedAt: now()->subDay()->toDateString(),
            dueAt: now()->addDays(29)->toDateString(),
            paidAt: null,
            lines: [['Mission de cadrage — brouillon', 1, 1_000_000]],
        );

        // Envoyée — en attente de paiement (dans les délais)
        $this->invoice('FYK-FAC-LC-SENT', InvoiceStatus::Sent, 2_000_000,
            issuedAt: now()->subDays(2)->toDateString(),
            dueAt: now()->addDays(28)->toDateString(),
            paidAt: null,
            lines: [['Développement portail client — sprint 1', 1, 2_000_000]],
        );

        // Envoyée + en retard (dérivé) — Sent avec due_at dans le passé et solde non réglé
        $this->invoice('FYK-FAC-LC-OVERDUE', InvoiceStatus::Sent, 2_000_000,
            issuedAt: now()->subDays(40)->toDateString(),
            dueAt: now()->subDays(12)->toDateString(),
            paidAt: null,
            lines: [['Audit infrastructure — facture en retard', 1, 2_000_000]],
        );

        // Partiellement payée — paiement en cours
        $this->invoice('FYK-FAC-LC-PARTIAL', InvoiceStatus::PartiallyPaid, 2_000_000,
            issuedAt: now()->subDays(7)->toDateString(),
            dueAt: now()->addDays(23)->toDateString(),
            paidAt: null,
            amountPaid: 1_200_000,
            lines: [['Refonte site institutionnel — phase 1', 1, 2_000_000]],
        );

        // Payée — encaissement complet
        $this->invoice('FYK-FAC-LC-PAID', InvoiceStatus::Paid, 1_500_000,
            issuedAt: now()->subDays(20)->toDateString(),
            dueAt: now()->subDays(5)->toDateString(),
            paidAt: now()->subDays(3)->toDateTimeString(),
            lines: [['Migration cloud — soldée', 1, 1_500_000]],
        );

        // Annulée — sortie de cycle
        $this->invoice('FYK-FAC-LC-CANCELLED', InvoiceStatus::Cancelled, 800_000,
            issuedAt: now()->subDays(10)->toDateString(),
            dueAt: now()->addDays(20)->toDateString(),
            paidAt: null,
            cancelledAt: now()->subDays(3)->toDateTimeString(),
            lines: [['Atelier RGPD — annulé', 1, 800_000]],
        );
    }

    private function seedQuotes(): void
    {
        // Brouillon
        $this->quote('FYK-DEV-LC-DRAFT', ProposalDocumentStatus::Draft, 900_000,
            issuedAt: now()->subDay()->toDateString(),
            validUntil: now()->addDays(29)->toDateString(),
            lines: [['Mission conseil cybersécurité — chiffrage initial', 1, 900_000]],
        );

        // Envoyé — en attente de réponse client
        $this->quote('FYK-DEV-LC-SENT', ProposalDocumentStatus::Sent, 1_400_000,
            issuedAt: now()->subDays(3)->toDateString(),
            validUntil: now()->addDays(27)->toDateString(),
            lines: [['Diagnostic SI — proposition commerciale', 1, 1_400_000]],
        );

        // Accepté — à convertir en facture
        $this->quote('FYK-DEV-LC-ACCEPTED', ProposalDocumentStatus::Accepted, 2_500_000,
            issuedAt: now()->subDays(8)->toDateString(),
            validUntil: now()->addDays(22)->toDateString(),
            acceptedAt: now()->subDay()->toDateTimeString(),
            lines: [['Plateforme e-commerce — devis accepté', 1, 2_500_000]],
        );

        // Facturé — devis lié à une facture
        $facturedQuote = $this->quote('FYK-DEV-LC-FACTURED', ProposalDocumentStatus::Accepted, 1_800_000,
            issuedAt: now()->subDays(15)->toDateString(),
            validUntil: now()->addDays(15)->toDateString(),
            acceptedAt: now()->subDays(8)->toDateTimeString(),
            lines: [['Refonte intranet — devis facturé', 1, 1_800_000]],
        );
        $this->invoice('FYK-FAC-LC-FROM-DEV', InvoiceStatus::Sent, 1_800_000,
            issuedAt: now()->subDays(6)->toDateString(),
            dueAt: now()->addDays(24)->toDateString(),
            paidAt: null,
            lines: [['Refonte intranet — facture issue du devis', 1, 1_800_000]],
            proposalDocumentId: $facturedQuote->id,
        );

        // Refusé — sortie de cycle
        $this->quote('FYK-DEV-LC-DECLINED', ProposalDocumentStatus::Declined, 1_200_000,
            issuedAt: now()->subDays(12)->toDateString(),
            validUntil: now()->addDays(18)->toDateString(),
            declinedAt: now()->subDays(5)->toDateTimeString(),
            lines: [['Audit conformité — refusé par le client', 1, 1_200_000]],
        );

        // Expiré (dérivé) — Sent dont la validité est dépassée
        $this->quote('FYK-DEV-LC-EXPIRED', ProposalDocumentStatus::Sent, 950_000,
            issuedAt: now()->subDays(45)->toDateString(),
            validUntil: now()->subDays(13)->toDateString(),
            lines: [['Maintenance applicative — devis périmé', 1, 950_000]],
        );
    }

    private function seedProformas(): void
    {
        // Brouillon
        $this->proforma('FYK-PRO-LC-DRAFT', ProposalDocumentStatus::Draft, 1_100_000,
            issuedAt: now()->subDay()->toDateString(),
            validUntil: now()->addDays(29)->toDateString(),
            lines: [['Mission audit — proforma brouillon', 1, 1_100_000]],
        );

        // Envoyée — en attente du BC
        $this->proforma('FYK-PRO-LC-SENT', ProposalDocumentStatus::Sent, 1_700_000,
            issuedAt: now()->subDays(4)->toDateString(),
            validUntil: now()->addDays(26)->toDateString(),
            lines: [['Marché public — proforma transmise', 1, 1_700_000]],
        );

        // BC reçu — prêt à facturer
        $this->proforma('FYK-PRO-LC-PO', ProposalDocumentStatus::PoReceived, 2_300_000,
            issuedAt: now()->subDays(9)->toDateString(),
            validUntil: now()->addDays(21)->toDateString(),
            poReference: 'BC-2026/0142',
            poReceivedAt: now()->subDays(2)->toDateString(),
            poNotes: 'Bon de commande signé reçu par mail.',
            lines: [['Audit DSI ministère — BC reçu', 1, 2_300_000]],
        );

        // Facturée — proforma liée à une facture
        $facturedProforma = $this->proforma('FYK-PRO-LC-FACTURED', ProposalDocumentStatus::Converted, 3_100_000,
            issuedAt: now()->subDays(20)->toDateString(),
            validUntil: now()->addDays(10)->toDateString(),
            poReference: 'BC-2026/0091',
            poReceivedAt: now()->subDays(15)->toDateString(),
            lines: [['Déploiement ERP — proforma convertie', 1, 3_100_000]],
        );
        $this->invoice('FYK-FAC-LC-FROM-PRO', InvoiceStatus::Sent, 3_100_000,
            issuedAt: now()->subDays(10)->toDateString(),
            dueAt: now()->addDays(20)->toDateString(),
            paidAt: null,
            lines: [['Déploiement ERP — facture issue de la proforma', 1, 3_100_000]],
            proposalDocumentId: $facturedProforma->id,
        );

        // Refusée
        $this->proforma('FYK-PRO-LC-DECLINED', ProposalDocumentStatus::Declined, 1_400_000,
            issuedAt: now()->subDays(14)->toDateString(),
            validUntil: now()->addDays(16)->toDateString(),
            declinedAt: now()->subDays(6)->toDateTimeString(),
            lines: [['Marché ONG — proforma refusée', 1, 1_400_000]],
        );

        // Expirée (dérivée) — Sent dont la validité est dépassée
        $this->proforma('FYK-PRO-LC-EXPIRED', ProposalDocumentStatus::Sent, 1_050_000,
            issuedAt: now()->subDays(50)->toDateString(),
            validUntil: now()->subDays(18)->toDateString(),
            lines: [['Étude faisabilité — validité dépassée', 1, 1_050_000]],
        );
    }

    /**
     * @param  array<int, array{string, int, int}>  $lines
     */
    private function invoice(
        string $reference,
        InvoiceStatus $status,
        int $subtotal,
        string $issuedAt,
        string $dueAt,
        ?string $paidAt,
        array $lines = [],
        int $amountPaid = 0,
        ?string $sentAt = null,
        ?string $cancelledAt = null,
        ?string $proposalDocumentId = null,
    ): Invoice {
        $taxAmount = (int) round($subtotal * 0.18);
        $total = $subtotal + $taxAmount;

        if ($status === InvoiceStatus::Paid) {
            $amountPaid = $total;
        }

        if ($sentAt === null && $status !== InvoiceStatus::Draft) {
            $sentAt = $issuedAt;
        }

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'proposal_document_id' => $proposalDocumentId,
            'reference' => $reference,
            'status' => $status,
            'issued_at' => $issuedAt,
            'sent_at' => $sentAt,
            'due_at' => $dueAt,
            'paid_at' => $paidAt,
            'cancelled_at' => $cancelledAt,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'amount_paid' => $amountPaid,
        ]);

        foreach ($lines as [$description, $quantity, $unitPrice]) {
            InvoiceLine::create([
                'invoice_id' => $invoice->id,
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_rate' => 18,
                'total' => $quantity * $unitPrice,
            ]);
        }

        return $invoice;
    }

    /**
     * @param  array<int, array{string, int, int}>  $lines
     */
    private function quote(
        string $reference,
        ProposalDocumentStatus $status,
        int $subtotal,
        string $issuedAt,
        string $validUntil,
        array $lines = [],
        ?string $sentAt = null,
        ?string $acceptedAt = null,
        ?string $declinedAt = null,
    ): ProposalDocument {
        $taxAmount = (int) round($subtotal * 0.18);
        $total = $subtotal + $taxAmount;

        if ($sentAt === null && $status !== ProposalDocumentStatus::Draft) {
            $sentAt = $issuedAt;
        }

        $quote = ProposalDocument::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => ProposalDocumentType::Quote,
            'reference' => $reference,
            'status' => $status,
            'issued_at' => $issuedAt,
            'valid_until' => $validUntil,
            'sent_at' => $sentAt,
            'accepted_at' => $acceptedAt,
            'declined_at' => $declinedAt,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ]);

        foreach ($lines as [$description, $quantity, $unitPrice]) {
            ProposalDocumentLine::create([
                'proposal_document_id' => $quote->id,
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_rate' => 18,
                'total' => $quantity * $unitPrice,
            ]);
        }

        return $quote;
    }

    /**
     * @param  array<int, array{string, int, int}>  $lines
     */
    private function proforma(
        string $reference,
        ProposalDocumentStatus $status,
        int $subtotal,
        string $issuedAt,
        string $validUntil,
        array $lines = [],
        ?string $sentAt = null,
        ?string $declinedAt = null,
        ?string $convertedAt = null,
        ?string $poReference = null,
        ?string $poReceivedAt = null,
        ?string $poNotes = null,
    ): ProposalDocument {
        $taxAmount = (int) round($subtotal * 0.18);
        $total = $subtotal + $taxAmount;

        if ($sentAt === null && $status !== ProposalDocumentStatus::Draft) {
            $sentAt = $issuedAt;
        }
        if ($convertedAt === null && $status === ProposalDocumentStatus::Converted) {
            $convertedAt = $poReceivedAt
                ? Carbon::parse($poReceivedAt)->addDays(3)->toDateTimeString()
                : Carbon::parse($issuedAt)->addDays(10)->toDateTimeString();
        }

        $proforma = ProposalDocument::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => ProposalDocumentType::Proforma,
            'reference' => $reference,
            'status' => $status,
            'issued_at' => $issuedAt,
            'valid_until' => $validUntil,
            'sent_at' => $sentAt,
            'declined_at' => $declinedAt,
            'converted_at' => $convertedAt,
            'po_reference' => $poReference,
            'po_received_at' => $poReceivedAt,
            'po_notes' => $poNotes,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ]);

        foreach ($lines as [$description, $quantity, $unitPrice]) {
            ProposalDocumentLine::create([
                'proposal_document_id' => $proforma->id,
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_rate' => 18,
                'total' => $quantity * $unitPrice,
            ]);
        }

        return $proforma;
    }
}
