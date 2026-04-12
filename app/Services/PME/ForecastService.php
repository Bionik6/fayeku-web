<?php

namespace App\Services\PME;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use App\Enums\PME\InvoiceStatus;
use App\Models\PME\Invoice;

class ForecastService
{
    /**
     * @param  Collection<int, Invoice>  $receivables
     * @param  array<string, array<string, mixed>>  $clientProfiles
     * @return array<int, array<string, mixed>>
     */
    public function rows(Collection $receivables, array $clientProfiles): array
    {
        return $receivables
            ->map(function (Invoice $invoice) use ($clientProfiles): array {
                $clientProfile = $clientProfiles[$invoice->client_id] ?? [];
                $confidence = $this->confidenceLevel($invoice, $clientProfiles);
                $remaining = max(0, (int) $invoice->total - (int) $invoice->amount_paid);
                $estimatedDate = $this->estimatedEntryDate($invoice, $clientProfile);
                $daysOverdue = $this->daysOverdue($invoice);

                return [
                    'id' => $invoice->id,
                    'invoice_id' => $invoice->id,
                    'client_id' => $invoice->client_id,
                    'document' => $invoice->reference ?? '—',
                    'client_name' => $invoice->client?->name ?? '—',
                    'total' => (int) $invoice->total,
                    'remaining' => $remaining,
                    'amount_paid' => (int) $invoice->amount_paid,
                    'due_at' => $invoice->due_at?->copy(),
                    'due_at_label' => format_date($invoice->due_at),
                    'delay_label' => $this->delayLabel($invoice),
                    'days_overdue' => $daysOverdue,
                    'confidence_score' => $confidence['score'],
                    'confidence_label' => $confidence['label'],
                    'confidence_tone' => $confidence['tone'],
                    'estimated_amount' => (int) round($remaining * $confidence['score'] / 100),
                    'estimated_date' => $estimatedDate,
                    'estimated_date_label' => format_date($estimatedDate),
                    'status_value' => $invoice->status->value,
                    'status_label' => $this->statusLabel($invoice->status),
                    'status_tone' => $this->statusTone($invoice->status),
                    'base_label' => $this->baseLabel($invoice, $clientProfile, $daysOverdue),
                    'reminders_count' => $invoice->reminders->count(),
                    'last_reminder_at' => $invoice->reminders->first()?->sent_at?->copy(),
                    'last_reminder_label' => $invoice->reminders->first()?->sent_at?->locale('fr_FR')->translatedFormat('j M. Y'),
                ];
            })
            ->sortBy([
                ['estimated_date', 'asc'],
                ['confidence_score', 'asc'],
                ['document', 'asc'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, array<string, mixed>>  $clientProfiles
     * @return array{label: string, score: int, tone: string}
     */
    public function confidenceLevel(Invoice $invoice, array $clientProfiles = []): array
    {
        $invoice->loadMissing('reminders');

        $clientProfile = $clientProfiles[$invoice->client_id] ?? [];
        $score = (int) ($clientProfile['payment_score'] ?? 60);
        $daysOverdue = $this->daysOverdue($invoice);

        if (! $invoice->due_at || $invoice->due_at->greaterThanOrEqualTo(now()->startOfDay())) {
            $score += 10;
        }

        if ((int) $invoice->amount_paid > 0) {
            $score += 10;
        }

        if ($daysOverdue >= 1 && $daysOverdue <= 15) {
            $score -= 10;
        } elseif ($daysOverdue >= 16 && $daysOverdue <= 30) {
            $score -= 20;
        } elseif ($daysOverdue >= 31 && $daysOverdue <= 60) {
            $score -= 35;
        } elseif ($daysOverdue > 60) {
            $score -= 50;
        }

        $recentReminder = $invoice->reminders
            ->first(fn ($reminder) => $reminder->sent_at && $reminder->sent_at->greaterThanOrEqualTo(now()->subDays(14)));

        if ($recentReminder) {
            $score += 5;
        }

        if ($daysOverdue > 7 && $invoice->reminders->isEmpty()) {
            $score -= 10;
        }

        $score = max(5, min(95, $score));

        return [
            'label' => match (true) {
                $score >= 85 => 'Fort probable',
                $score >= 60 => 'Moyen',
                default => 'Risqué',
            },
            'score' => $score,
            'tone' => match (true) {
                $score >= 85 => 'emerald',
                $score >= 60 => 'amber',
                default => 'rose',
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $clientProfile
     */
    private function estimatedEntryDate(Invoice $invoice, array $clientProfile): CarbonInterface
    {
        $today = now()->startOfDay();
        $estimatedDate = $invoice->due_at?->copy()->startOfDay() ?? $today->copy();
        $daysOverdue = $this->daysOverdue($invoice);
        $averageLateDays = (int) ($clientProfile['average_late_days'] ?? 0);

        if ($averageLateDays > 0) {
            $estimatedDate = $estimatedDate->addDays($averageLateDays);
        } elseif ($daysOverdue > 0) {
            $estimatedDate = match (true) {
                $daysOverdue <= 15 => $today->copy()->addDays(7),
                $daysOverdue <= 30 => $today->copy()->addDays(15),
                default => $today->copy()->addDays(30),
            };
        }

        if ((int) $invoice->amount_paid > 0) {
            $estimatedDate = $estimatedDate->subDays(3);
        }

        if ($estimatedDate->lessThan($today)) {
            return $today;
        }

        return $estimatedDate;
    }

    private function daysOverdue(Invoice $invoice): int
    {
        if (! $invoice->due_at || $invoice->due_at->greaterThanOrEqualTo(now()->startOfDay())) {
            return 0;
        }

        return (int) $invoice->due_at->copy()->startOfDay()->diffInDays(now()->startOfDay(), true);
    }

    private function delayLabel(Invoice $invoice): string
    {
        if (! $invoice->due_at) {
            return '—';
        }

        if ($invoice->due_at->isToday()) {
            return 'Aujourd’hui';
        }

        if ($invoice->due_at->isFuture()) {
            return 'Dans '.(int) now()->startOfDay()->diffInDays($invoice->due_at->copy()->startOfDay(), true).'j';
        }

        return 'J+'.$this->daysOverdue($invoice);
    }

    /**
     * @param  array<string, mixed>  $clientProfile
     */
    private function baseLabel(Invoice $invoice, array $clientProfile, int $daysOverdue): string
    {
        $parts = [];

        if ((int) ($clientProfile['payment_score'] ?? 0) > 0) {
            $parts[] = 'score client '.(int) $clientProfile['payment_score'].'%';
        } else {
            $parts[] = 'score par défaut 60%';
        }

        if ($daysOverdue > 0) {
            $parts[] = 'retard '.($daysOverdue).'j';
        } elseif ($invoice->due_at) {
            $parts[] = 'échéance '.($invoice->due_at->isToday() ? 'aujourd’hui' : 'à venir');
        }

        if ((int) $invoice->amount_paid > 0) {
            $parts[] = 'paiement partiel';
        }

        if ($invoice->reminders->isNotEmpty()) {
            $parts[] = $invoice->reminders->count().' relance(s)';
        }

        return ucfirst(implode(' · ', $parts));
    }

    private function statusLabel(InvoiceStatus $status): string
    {
        return match ($status) {
            InvoiceStatus::Sent => 'Envoyée',
            InvoiceStatus::Certified => 'Certifiée',
            InvoiceStatus::CertificationFailed => 'Certification à revoir',
            InvoiceStatus::PartiallyPaid => 'Partiellement encaissée',
            InvoiceStatus::Overdue => 'En retard',
            default => ucfirst($status->value),
        };
    }

    private function statusTone(InvoiceStatus $status): string
    {
        return match ($status) {
            InvoiceStatus::PartiallyPaid => 'teal',
            InvoiceStatus::Overdue => 'rose',
            default => 'amber',
        };
    }
}
