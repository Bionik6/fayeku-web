<?php

namespace App\Services\PME;

use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\ProposalDocumentStatus;
use App\Models\PME\Invoice;
use App\Models\PME\ProposalDocument;
use Carbon\CarbonInterface;

class DocumentLifecycleService
{
    /**
     * @return array<string, mixed>
     */
    public function forInvoice(Invoice $invoice): array
    {
        $remaining = max(0, (int) $invoice->total - (int) $invoice->amount_paid);
        $sentAt = $invoice->sent_at ?? $invoice->issued_at;

        if ($invoice->status === InvoiceStatus::Draft) {
            return $this->draftLifecycle(
                documentLabel: 'Facture',
                documentBadge: 'FACTURE',
                documentClass: 'bg-primary text-white',
                cycles: 5,
                title: 'État : Brouillon — non encore envoyée',
                badgeLabel: 'Brouillon',
                badgeClass: 'bg-slate-100 text-slate-600',
                message: 'Cette facture est encore au stade de brouillon. Envoyez-la pour démarrer le suivi de paiement.'
            );
        }

        if ($invoice->status === InvoiceStatus::Cancelled) {
            return $this->invoiceLifecycle(
                title: 'État : Annulée — sortie de cycle',
                badges: [
                    ['label' => 'Annulée', 'class' => 'bg-slate-100 text-slate-600'],
                ],
                steps: [
                    $this->step('Envoyée', $this->compactDate($sentAt), 'completed', 'muted'),
                    $this->step('Annulée', $this->fullDate($invoice->cancelled_at), 'muted-failed'),
                ],
                note: 'La facture a été annulée et n’est plus comptabilisée dans vos encours.'
            );
        }

        if ($invoice->status === InvoiceStatus::Paid) {
            return $this->invoiceLifecycle(
                title: 'État : Payée — encaissement complet',
                badges: [
                    ['label' => 'Payée', 'class' => 'bg-emerald-50 text-emerald-700'],
                ],
                steps: [
                    $this->step('Envoyée', $this->compactDate($sentAt), 'completed', 'active'),
                    $this->step('Payée', $this->fullDate($invoice->paid_at), 'completed'),
                ]
            );
        }

        if ($invoice->status === InvoiceStatus::PartiallyPaid) {
            return $this->invoiceLifecycle(
                title: 'État : Partiellement payée — paiement en cours',
                badges: [
                    ['label' => 'Partiellement payée', 'class' => 'bg-amber-50 text-amber-700'],
                ],
                steps: [
                    $this->step('Envoyée', $this->compactDate($sentAt), 'completed', 'active'),
                    $this->step(
                        'Partiellement payée',
                        format_money((int) $invoice->amount_paid, $invoice->currency, withLabel: false).' / '.format_money((int) $invoice->total, $invoice->currency, withLabel: false).' reçus',
                        'current',
                        'neutral'
                    ),
                    $this->step('Payée', 'Reste '.format_money($remaining, $invoice->currency, withLabel: false), 'pending'),
                ]
            );
        }

        if ($this->isInvoiceOverdue($invoice)) {
            $days = $invoice->due_at
                ? abs((int) now()->startOfDay()->diffInDays($invoice->due_at->copy()->startOfDay(), false))
                : 0;

            return $this->invoiceLifecycle(
                title: 'État : Envoyée + en retard — alerte temporelle',
                badges: [
                    ['label' => 'Envoyée', 'class' => 'bg-blue-50 text-blue-700'],
                    ['label' => 'En retard · J+'.$days, 'class' => 'bg-rose-50 text-rose-700'],
                ],
                steps: [
                    $this->step('Envoyée · en retard', 'Échue le '.$this->fullDate($invoice->due_at), 'danger', 'neutral'),
                    $this->step('Payée', '—', 'pending'),
                ],
                note: '"En retard" est calculé à partir de la date d’échéance et du solde restant. Le statut comptable reste celui de la facture.'
            );
        }

        return $this->invoiceLifecycle(
            title: 'État : Envoyée — en attente de paiement',
            badges: [
                ['label' => 'Envoyée', 'class' => 'bg-blue-50 text-blue-700'],
            ],
            steps: [
                $this->step('Envoyée', $this->fullDate($sentAt), 'current', 'neutral'),
                $this->step('Payée', $invoice->due_at ? $this->compactDate($invoice->due_at).' prévu' : '—', 'pending'),
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function forQuote(ProposalDocument $quote): array
    {
        $sentAt = $quote->sent_at ?? $quote->issued_at;

        if ($quote->status === ProposalDocumentStatus::Draft) {
            return $this->draftLifecycle(
                documentLabel: 'Devis',
                documentBadge: 'DEVIS',
                documentClass: 'bg-amber-50 text-amber-800',
                cycles: 6,
                title: 'État : Brouillon — non encore envoyé',
                badgeLabel: 'Brouillon',
                badgeClass: 'bg-slate-100 text-slate-600',
                message: 'Ce devis est encore au stade de brouillon. Envoyez-le pour démarrer le suivi commercial.'
            );
        }

        if ($quote->invoice) {
            return $this->proposalLifecycle(
                documentLabel: 'Devis',
                documentBadge: 'DEVIS',
                documentClass: 'bg-amber-50 text-amber-800',
                title: 'État : Facturé — facture créée',
                badges: [
                    ['label' => 'Facturé', 'class' => 'bg-emerald-50 text-emerald-700'],
                ],
                steps: [
                    $this->step('Envoyé', $this->compactDate($sentAt), 'completed', 'active'),
                    $this->step('Accepté', $this->compactDate($quote->accepted_at), 'completed', 'active'),
                    $this->step('Facturé', $quote->invoice->reference ?? '—', 'completed'),
                ]
            );
        }

        if ($quote->status === ProposalDocumentStatus::Declined) {
            return $this->proposalLifecycle(
                documentLabel: 'Devis',
                documentBadge: 'DEVIS',
                documentClass: 'bg-amber-50 text-amber-800',
                title: 'État : Refusé — sortie de cycle',
                badges: [
                    ['label' => 'Refusé', 'class' => 'bg-rose-50 text-rose-700'],
                ],
                steps: [
                    $this->step('Envoyé', $this->compactDate($sentAt), 'completed', 'muted'),
                    $this->step('Refusé', $this->fullDate($quote->declined_at), 'danger'),
                ],
                note: 'Le devis a été marqué comme refusé. Aucune suite possible — duplication recommandée pour repartir d’une nouvelle base.'
            );
        }

        if ($this->isProposalExpired($quote)) {
            return $this->proposalLifecycle(
                documentLabel: 'Devis',
                documentBadge: 'DEVIS',
                documentClass: 'bg-amber-50 text-amber-800',
                title: 'État : Expiré — durée de validité dépassée',
                badges: [
                    ['label' => 'Expiré', 'class' => 'bg-amber-50 text-amber-700'],
                ],
                steps: [
                    $this->step('Envoyé', $this->compactDate($sentAt), 'completed', 'muted'),
                    $this->step('Expiré', $this->fullDate($quote->valid_until), 'warning'),
                ],
                note: 'La date de validité est dépassée sans réponse client. Vous pouvez dupliquer le devis avec une nouvelle date.'
            );
        }

        if ($quote->status === ProposalDocumentStatus::Accepted) {
            return $this->proposalLifecycle(
                documentLabel: 'Devis',
                documentBadge: 'DEVIS',
                documentClass: 'bg-amber-50 text-amber-800',
                title: 'État : Accepté — à convertir en facture',
                badges: [
                    ['label' => 'Accepté', 'class' => 'bg-emerald-50 text-emerald-700'],
                ],
                steps: [
                    $this->step('Envoyé', $this->compactDate($sentAt), 'completed', 'active'),
                    $this->step('Accepté', $this->compactDate($quote->accepted_at), 'current', 'neutral'),
                    $this->step('Facturé', '—', 'pending'),
                ]
            );
        }

        return $this->proposalLifecycle(
            documentLabel: 'Devis',
            documentBadge: 'DEVIS',
            documentClass: 'bg-amber-50 text-amber-800',
            title: 'État : Envoyé — en attente de réponse client',
            badges: [
                ['label' => 'Envoyé', 'class' => 'bg-blue-50 text-blue-700'],
            ],
            steps: [
                $this->step('Envoyé', $this->fullDate($sentAt), 'current', 'neutral'),
                $this->step('Accepté', '—', 'pending', 'neutral'),
                $this->step('Facturé', $quote->valid_until ? $this->compactDate($quote->valid_until).' au plus tard' : '—', 'pending'),
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function forProforma(ProposalDocument $proforma): array
    {
        $sentAt = $proforma->sent_at ?? $proforma->issued_at;

        if ($proforma->status === ProposalDocumentStatus::Draft) {
            return $this->draftLifecycle(
                documentLabel: 'Proforma',
                documentBadge: 'PROFORMA',
                documentClass: 'bg-violet-50 text-violet-700',
                cycles: 6,
                title: 'État : Brouillon — non encore envoyée',
                badgeLabel: 'Brouillon',
                badgeClass: 'bg-slate-100 text-slate-600',
                message: 'Cette proforma est encore au stade de brouillon. Envoyez-la pour démarrer le suivi commercial.'
            );
        }

        if ($proforma->invoice || $proforma->status === ProposalDocumentStatus::Converted) {
            return $this->proposalLifecycle(
                documentLabel: 'Proforma',
                documentBadge: 'PROFORMA',
                documentClass: 'bg-violet-50 text-violet-700',
                title: 'État : Facturée — facture créée',
                badges: [
                    ['label' => 'Facturée', 'class' => 'bg-emerald-50 text-emerald-700'],
                ],
                steps: [
                    $this->step('Envoyée', $this->compactDate($sentAt), 'completed', 'active'),
                    $this->step('BC reçu', $this->compactDate($proforma->po_received_at), 'completed', 'active'),
                    $this->step('Facturée', $proforma->invoice?->reference ?? '—', 'completed'),
                ]
            );
        }

        if ($proforma->status === ProposalDocumentStatus::Declined) {
            return $this->proposalLifecycle(
                documentLabel: 'Proforma',
                documentBadge: 'PROFORMA',
                documentClass: 'bg-violet-50 text-violet-700',
                title: 'État : Refusée — sortie de cycle',
                badges: [
                    ['label' => 'Refusée', 'class' => 'bg-rose-50 text-rose-700'],
                ],
                steps: [
                    $this->step('Envoyée', $this->compactDate($sentAt), 'completed', 'muted'),
                    $this->step('Refusée', $this->fullDate($proforma->declined_at), 'danger'),
                ],
                note: 'La proforma a été marquée comme refusée par le client. Vous pouvez la dupliquer pour proposer une nouvelle offre.'
            );
        }

        if ($this->isProposalExpired($proforma)) {
            return $this->proposalLifecycle(
                documentLabel: 'Proforma',
                documentBadge: 'PROFORMA',
                documentClass: 'bg-violet-50 text-violet-700',
                title: 'État : Expirée — validité dépassée',
                badges: [
                    ['label' => 'Expirée', 'class' => 'bg-amber-50 text-amber-700'],
                ],
                steps: [
                    $this->step('Envoyée', $this->compactDate($sentAt), 'completed', 'muted'),
                    $this->step('Expirée', $this->fullDate($proforma->valid_until), 'warning'),
                ],
                note: 'Aucun BC reçu avant la date de validité. Dupliquez la proforma avec une nouvelle date pour relancer.'
            );
        }

        if ($proforma->status === ProposalDocumentStatus::PoReceived) {
            $poDetail = $proforma->po_reference
                ? $proforma->po_reference.' · '.$this->compactDate($proforma->po_received_at)
                : $this->compactDate($proforma->po_received_at);

            return $this->proposalLifecycle(
                documentLabel: 'Proforma',
                documentBadge: 'PROFORMA',
                documentClass: 'bg-violet-50 text-violet-700',
                title: 'État : BC reçu — prêt à facturer',
                badges: [
                    ['label' => 'BC reçu', 'class' => 'bg-emerald-50 text-emerald-700'],
                ],
                steps: [
                    $this->step('Envoyée', $this->compactDate($sentAt), 'completed', 'active'),
                    $this->step('BC reçu', $poDetail, 'current', 'neutral'),
                    $this->step('Facturée', '—', 'pending'),
                ]
            );
        }

        return $this->proposalLifecycle(
            documentLabel: 'Proforma',
            documentBadge: 'PROFORMA',
            documentClass: 'bg-violet-50 text-violet-700',
            title: 'État : Envoyée — en attente du BC',
            badges: [
                ['label' => 'Envoyée', 'class' => 'bg-blue-50 text-blue-700'],
            ],
            steps: [
                $this->step('Envoyée', $this->fullDate($sentAt), 'current', 'neutral'),
                $this->step('BC reçu', '—', 'pending', 'neutral'),
                $this->step('Facturée', $proforma->valid_until ? $this->compactDate($proforma->valid_until).' au plus tard' : '—', 'pending'),
            ]
        );
    }

    /**
     * @param  array<int, array<string, string>>  $badges
     * @param  array<int, array<string, string>>  $steps
     * @return array<string, mixed>
     */
    private function invoiceLifecycle(string $title, array $badges, array $steps, ?string $note = null): array
    {
        return $this->lifecycle('Facture', 'FACTURE', 'bg-primary text-white', 5, $title, $badges, $steps, $note);
    }

    /**
     * @param  array<int, array<string, string>>  $badges
     * @param  array<int, array<string, string>>  $steps
     * @return array<string, mixed>
     */
    private function proposalLifecycle(
        string $documentLabel,
        string $documentBadge,
        string $documentClass,
        string $title,
        array $badges,
        array $steps,
        ?string $note = null
    ): array {
        return $this->lifecycle($documentLabel, $documentBadge, $documentClass, 6, $title, $badges, $steps, $note);
    }

    /**
     * @return array<string, mixed>
     */
    private function draftLifecycle(
        string $documentLabel,
        string $documentBadge,
        string $documentClass,
        int $cycles,
        string $title,
        string $badgeLabel,
        string $badgeClass,
        string $message
    ): array {
        return $this->lifecycle(
            $documentLabel,
            $documentBadge,
            $documentClass,
            $cycles,
            $title,
            [['label' => $badgeLabel, 'class' => $badgeClass]],
            [],
            null,
            [
                'title' => 'Le cycle de vie démarre à l’envoi',
                'body' => $message,
            ]
        );
    }

    /**
     * @param  array<int, array<string, string>>  $badges
     * @param  array<int, array<string, string>>  $steps
     * @param  array{title: string, body: string}|null  $message
     * @return array<string, mixed>
     */
    private function lifecycle(
        string $documentLabel,
        string $documentBadge,
        string $documentClass,
        int $cycles,
        string $title,
        array $badges,
        array $steps,
        ?string $note = null,
        ?array $message = null
    ): array {
        return [
            'document' => [
                'label' => $documentLabel,
                'badge' => $documentBadge,
                'class' => $documentClass,
            ],
            'cycles' => $cycles,
            'title' => $title,
            'badges' => $badges,
            'steps' => $steps,
            'note' => $note,
            'message' => $message,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function step(string $label, ?string $detail, string $state, string $connector = 'none'): array
    {
        return [
            'label' => $label,
            'detail' => filled($detail) ? (string) $detail : '—',
            'state' => $state,
            'connector' => $connector,
        ];
    }

    private function isInvoiceOverdue(Invoice $invoice): bool
    {
        if (! $invoice->due_at || $invoice->status === InvoiceStatus::Paid || $invoice->status === InvoiceStatus::Cancelled) {
            return false;
        }

        if ((int) $invoice->amount_paid >= (int) $invoice->total) {
            return false;
        }

        return $invoice->status === InvoiceStatus::Overdue
            || ($invoice->status !== InvoiceStatus::Draft && $invoice->due_at->copy()->startOfDay()->lt(now()->startOfDay()));
    }

    private function isProposalExpired(ProposalDocument $document): bool
    {
        if (! $document->valid_until || $document->invoice) {
            return false;
        }

        if (in_array($document->status, [
            ProposalDocumentStatus::Accepted,
            ProposalDocumentStatus::PoReceived,
            ProposalDocumentStatus::Converted,
            ProposalDocumentStatus::Declined,
        ], true)) {
            return false;
        }

        return $document->status === ProposalDocumentStatus::Expired
            || ($document->status === ProposalDocumentStatus::Sent && $document->valid_until->copy()->startOfDay()->lt(now()->startOfDay()));
    }

    private function compactDate(CarbonInterface|string|null $date): string
    {
        return $date ? format_date($date, withYear: false) : '—';
    }

    private function fullDate(CarbonInterface|string|null $date): string
    {
        return $date ? format_date($date) : '—';
    }
}
