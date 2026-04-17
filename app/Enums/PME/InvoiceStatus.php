<?php

namespace App\Enums\PME;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Certified = 'certified';
    case CertificationFailed = 'certification_failed';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';

    /**
     * Display metadata for UI (label + ring classes).
     *
     * @return array{label: string, class: string, tone: string}
     */
    public function display(): array
    {
        return match ($this) {
            self::Paid => ['label' => 'Payée', 'class' => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20', 'tone' => 'emerald'],
            self::Overdue => ['label' => 'En retard', 'class' => 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20', 'tone' => 'rose'],
            self::PartiallyPaid => ['label' => 'Part. payée', 'class' => 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20', 'tone' => 'amber'],
            self::Sent, self::Certified => ['label' => 'Envoyée', 'class' => 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20', 'tone' => 'blue'],
            self::CertificationFailed => ['label' => 'Certification échouée', 'class' => 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20', 'tone' => 'rose'],
            self::Draft => ['label' => 'Brouillon', 'class' => 'bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-600/20', 'tone' => 'slate'],
            self::Cancelled => ['label' => 'Annulée', 'class' => 'bg-slate-100 text-slate-500 ring-1 ring-inset ring-slate-500/20', 'tone' => 'slate'],
        };
    }
}
