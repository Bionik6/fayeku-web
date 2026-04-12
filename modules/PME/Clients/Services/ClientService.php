<?php

namespace Modules\PME\Clients\Services;

use Illuminate\Support\Collection;
use Modules\Auth\Models\Company;
use Modules\PME\Clients\Models\Client;
use Modules\PME\Collection\Enums\ReminderChannel;
use Modules\PME\Invoicing\Enums\InvoiceStatus;
use Modules\PME\Invoicing\Enums\QuoteStatus;
use Modules\PME\Invoicing\Models\Invoice;
use Modules\Shared\Models\User;

class ClientService
{
    /** @return array<string, string> */
    public function periodOptions(): array
    {
        return [
            '30d' => '30 derniers jours',
            '90d' => '90 derniers jours',
            'year' => 'Année en cours',
            'all' => 'Tout l’historique',
        ];
    }

    /** @return array<string, string> */
    public function sortOptions(): array
    {
        return [
            'revenue_desc' => 'Trier par CA',
            'outstanding_desc' => 'Trier par impayés',
            'score_desc' => 'Trier par score',
            'delay_desc' => 'Trier par délai moyen',
            'name_asc' => 'Trier par nom',
        ];
    }

    public function companyForUser(User $user): ?Company
    {
        return $user->smeCompany();
    }

    /** @return array<int, array<string, mixed>> */
    public function portfolioRows(Company $company, string $period = 'year'): array
    {
        $periodStart = $this->resolvePeriodStart($period);

        $rows = $this->loadClients($company)
            ->map(fn (Client $client) => $this->buildRow($client, $periodStart))
            ->values();

        $averageRevenue = (int) round(
            $rows->filter(fn (array $row) => $row['period_revenue'] > 0)->avg('period_revenue') ?? 0
        );

        $totalOutstanding = (int) $rows->sum('outstanding_amount');
        $totalRevenue = (int) $rows->sum('total_revenue');

        return $rows
            ->map(function (array $row) use ($averageRevenue, $totalOutstanding, $totalRevenue): array {
                $row['is_big_account'] = $row['total_revenue'] > 0
                    && $row['total_revenue'] >= max($averageRevenue, 1);
                $row['outstanding_share'] = $totalOutstanding > 0
                    ? (int) round($row['outstanding_amount'] / $totalOutstanding * 100)
                    : 0;
                $row['revenue_share'] = $totalRevenue > 0
                    ? (int) round($row['total_revenue'] / $totalRevenue * 100)
                    : 0;

                return $row;
            })
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{
     *     active_clients: int,
     *     average_revenue_per_client: int,
     *     best_payer: ?array{name: string, support: string, score_label: string},
     *     watch_client: ?array{name: string, support: string, score_label: string},
     *     outstanding_total: int
     * }
     */
    public function summary(array $rows): array
    {
        $collection = collect($rows);
        $activeRows = $collection->filter(fn (array $row) => $row['is_active']);

        $bestPayer = $activeRows
            ->filter(fn (array $row) => $row['total_collected'] > 0)
            ->sortBy([
                ['payment_score', 'desc'],
                ['average_payment_days', 'asc'],
                ['total_collected', 'desc'],
            ])
            ->first();

        $watchClient = $activeRows
            ->sortBy([
                ['is_watch', 'desc'],
                ['outstanding_amount', 'desc'],
                ['payment_score', 'asc'],
            ])
            ->first();

        return [
            'active_clients' => $activeRows->count(),
            'average_revenue_per_client' => $activeRows->isNotEmpty()
                ? (int) round($activeRows->avg('period_revenue') ?? 0)
                : 0,
            'best_payer' => $bestPayer ? [
                'name' => $bestPayer['name'],
                'support' => $bestPayer['average_payment_days'] > 0
                    ? 'Délai moyen : '.$bestPayer['average_payment_days'].'j'
                    : 'Paiements réguliers',
                'score_label' => $bestPayer['payment_label'],
            ] : null,
            'watch_client' => $watchClient ? [
                'name' => $watchClient['name'],
                'support' => $watchClient['outstanding_amount'] > 0
                    ? format_money($watchClient['outstanding_amount']).' en attente'
                    : ($watchClient['average_late_days'] > 0
                        ? $watchClient['average_late_days'].'j de retard moyen'
                        : 'Aucun signal critique'),
                'score_label' => $watchClient['payment_label'],
            ] : null,
            'outstanding_total' => (int) $collection->sum('outstanding_amount'),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    public function segmentCounts(array $rows): array
    {
        $collection = collect($rows);

        return [
            'all' => $collection->count(),
            'reliable' => $collection->where('is_reliable', true)->count(),
            'watch' => $collection->where('is_watch', true)->count(),
            'frequent_delays' => $collection->where('has_frequent_delays', true)->count(),
            'inactive' => $collection->where('is_inactive', true)->count(),
            'big_accounts' => $collection->where('is_big_account', true)->count(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{title: string, body: string, tone: string}|null
     */
    public function insight(array $rows): ?array
    {
        $collection = collect($rows);

        $exposureClient = $collection
            ->filter(fn (array $row) => $row['outstanding_amount'] > 0)
            ->sortByDesc('outstanding_share')
            ->first();

        if ($exposureClient && $exposureClient['outstanding_share'] >= 30) {
            return [
                'title' => 'Exposition au risque',
                'body' => sprintf(
                    '%s représente %s%% de vos montants en attente.',
                    $exposureClient['name'],
                    $exposureClient['outstanding_share']
                ),
                'tone' => $exposureClient['outstanding_share'] >= 45 ? 'rose' : 'amber',
            ];
        }

        $revenueClient = $collection
            ->filter(fn (array $row) => $row['total_revenue'] > 0)
            ->sortByDesc('revenue_share')
            ->first();

        if ($revenueClient && $revenueClient['revenue_share'] >= 40) {
            return [
                'title' => 'Portefeuille concentré',
                'body' => sprintf(
                    '%s concentre %s%% de votre CA client.',
                    $revenueClient['name'],
                    $revenueClient['revenue_share']
                ),
                'tone' => 'teal',
            ];
        }

        return null;
    }

    /**
     * @return array{
     *     row: array<string, mixed>,
     *     contact: array<string, string>,
     *     invoices: array<int, array<string, mixed>>,
     *     quotes: array<int, array<string, mixed>>,
     *     payments: array<int, array<string, mixed>>,
     *     reminders: array<int, array<string, mixed>>,
     *     timeline: array<int, array<string, mixed>>,
     *     exposure: array{share: int, total_outstanding: int}
     * }
     */
    public function detail(Client $client): array
    {
        $client->loadMissing([
            'company',
            'invoices.reminders',
            'quotes',
        ]);

        $row = collect($this->portfolioRows($client->company, 'all'))
            ->firstWhere('id', $client->id);

        $invoices = $client->invoices
            ->filter(fn (Invoice $invoice) => ! in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Cancelled], true))
            ->sortByDesc('issued_at')
            ->values();

        $quotes = $client->quotes
            ->sortByDesc('issued_at')
            ->values();

        $reminders = $invoices
            ->flatMap(function (Invoice $invoice): Collection {
                return $invoice->reminders->each(
                    fn ($reminder) => $reminder->setRelation('invoice', $invoice)
                );
            })
            ->sortByDesc('sent_at')
            ->values();

        $payments = $invoices
            ->filter(fn (Invoice $invoice) => $invoice->amount_paid > 0)
            ->sortByDesc(fn (Invoice $invoice) => $invoice->paid_at ?? $invoice->updated_at)
            ->values()
            ->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'reference' => $invoice->reference ?? '—',
                'amount' => (int) $invoice->amount_paid,
                'paid_at_label' => $invoice->paid_at
                    ? format_date($invoice->updated_at, withTime: true)
                    : 'Paiement enregistré',
                'status' => $this->invoiceStatusLabel($invoice->status),
            ])
            ->all();

        $timeline = collect()
            ->merge($invoices->map(fn (Invoice $invoice) => [
                'invoice_id' => $invoice->id,
                'quote_id' => null,
                'date' => $invoice->created_at,
                'title' => 'Facture envoyée',
                'body' => ($invoice->reference ?? '—').' · '.format_money($invoice->total),
            ]))
            ->merge($invoices->filter(fn (Invoice $invoice) => $invoice->paid_at)->map(fn (Invoice $invoice) => [
                'invoice_id' => $invoice->id,
                'quote_id' => null,
                'date' => $invoice->paid_at,
                'title' => 'Paiement reçu',
                'body' => ($invoice->reference ?? '—').' · '.format_money((int) $invoice->amount_paid),
            ]))
            ->merge($quotes->map(fn ($quote) => [
                'invoice_id' => null,
                'quote_id' => $quote->id,
                'date' => $quote->created_at,
                'title' => 'Devis envoyé',
                'body' => ($quote->reference ?? '—').' · '.format_money($quote->total),
            ]))
            ->merge($invoices->flatMap(fn (Invoice $invoice) => $invoice->reminders->filter(fn ($reminder) => $reminder->sent_at)->map(
                fn ($reminder) => [
                    'invoice_id' => $invoice->id,
                    'quote_id' => null,
                    'date' => $reminder->sent_at,
                    'title' => 'Relance envoyée',
                    'body' => ($invoice->reference ?? '—').' · '.$this->channelLabel($reminder->channel),
                ]
            )))
            ->filter(fn (array $event) => $event['date'] !== null)
            ->sortByDesc('date')
            ->take(12)
            ->values()
            ->map(fn (array $event) => [
                'invoice_id' => $event['invoice_id'],
                'quote_id' => $event['quote_id'],
                'title' => $event['title'],
                'body' => $event['body'],
                'date_label' => format_date($event['date'], withTime: true),
            ])
            ->all();

        return [
            'row' => $row ?? $this->buildRow($client, null),
            'contact' => [
                'phone' => $client->phone ?? '—',
                'email' => $client->email ?? '—',
                'tax_id' => $client->tax_id ?? '—',
                'address' => $client->address ?? '—',
            ],
            'invoices' => $invoices->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'reference' => $invoice->reference ?? '—',
                'issued_at_label' => format_date($invoice->issued_at),
                'due_at_label' => format_date($invoice->due_at),
                'total' => (int) $invoice->total,
                'remaining' => max(0, (int) $invoice->total - (int) $invoice->amount_paid),
                'status' => $this->invoiceStatusLabel($invoice->status),
                'status_value' => $invoice->status->value,
                'status_tone' => $this->invoiceStatusTone($invoice->status),
                'is_overdue' => $invoice->status === InvoiceStatus::Overdue,
                'reminders_count' => $invoice->reminders->count(),
            ])->all(),
            'quotes' => $quotes->map(fn ($quote) => [
                'id' => $quote->id,
                'reference' => $quote->reference ?? '—',
                'issued_at_label' => format_date($quote->issued_at),
                'total' => (int) $quote->total,
                'status' => $this->quoteStatusLabel($quote->status),
                'status_tone' => $this->quoteStatusTone($quote->status),
            ])->all(),
            'payments' => $payments,
            'reminders' => $reminders,
            'timeline' => $timeline,
            'exposure' => [
                'share' => $row['outstanding_share'] ?? 0,
                'total_outstanding' => $row['outstanding_amount'] ?? 0,
            ],
        ];
    }

    /** @return Collection<int, Client> */
    private function loadClients(Company $company): Collection
    {
        return Client::query()
            ->where('company_id', $company->id)
            ->with([
                'invoices.reminders',
                'quotes',
            ])
            ->orderBy('name')
            ->get();
    }

    /** @return array<string, mixed> */
    private function buildRow(Client $client, mixed $periodStart): array
    {
        $invoices = $client->invoices
            ->filter(fn (Invoice $invoice) => ! in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Cancelled], true))
            ->values();

        $quotes = $client->quotes->values();

        $periodInvoices = $periodStart
            ? $invoices->filter(fn (Invoice $invoice) => $invoice->issued_at && $invoice->issued_at->greaterThanOrEqualTo($periodStart))
            : $invoices;

        $periodQuotes = $periodStart
            ? $quotes->filter(fn ($quote) => $quote->issued_at && $quote->issued_at->greaterThanOrEqualTo($periodStart))
            : $quotes;

        $openInvoices = $invoices->filter(
            fn (Invoice $invoice) => in_array(
                $invoice->status,
                [InvoiceStatus::Sent, InvoiceStatus::Certified, InvoiceStatus::CertificationFailed, InvoiceStatus::PartiallyPaid, InvoiceStatus::Overdue],
                true
            )
        );

        $paidInvoices = $invoices->filter(fn (Invoice $invoice) => $invoice->status === InvoiceStatus::Paid && $invoice->paid_at);

        $latePaidInvoices = $paidInvoices->filter(
            fn (Invoice $invoice) => $invoice->due_at && $invoice->paid_at && $invoice->paid_at->greaterThan($invoice->due_at)
        );

        $openLateInvoices = $openInvoices->filter(
            fn (Invoice $invoice) => $invoice->due_at && $invoice->due_at->isPast()
        );

        $lateCount = $latePaidInvoices->count() + $openLateInvoices->count();
        $averageLateDays = $lateCount > 0
            ? (int) round(collect()
                ->merge($latePaidInvoices->map(fn (Invoice $invoice) => (int) $invoice->due_at->diffInDays($invoice->paid_at)))
                ->merge($openLateInvoices->map(fn (Invoice $invoice) => (int) $invoice->due_at->diffInDays(now())))
                ->avg() ?? 0)
            : 0;

        $averagePaymentDays = $paidInvoices->isNotEmpty()
            ? (int) round($paidInvoices->avg(
                fn (Invoice $invoice) => $invoice->issued_at
                    ? $invoice->issued_at->diffInDays($invoice->paid_at)
                    : 0
            ) ?? 0)
            : 0;

        $outstandingAmount = (int) $openInvoices->sum(
            fn (Invoice $invoice) => max(0, (int) $invoice->total - (int) $invoice->amount_paid)
        );

        $remindersCount = $invoices->sum(fn (Invoice $invoice) => $invoice->reminders->count());
        $totalRevenue = (int) $invoices->sum('total');
        $totalCollected = (int) $invoices->sum('amount_paid');
        $periodRevenue = (int) $periodInvoices->sum('total');
        $invoiceCountThisMonth = $invoices->filter(
            fn (Invoice $invoice) => $invoice->issued_at && $invoice->issued_at->isCurrentMonth()
        )->count();

        $score = $this->paymentScore(
            $invoices->count(),
            $outstandingAmount,
            $totalRevenue,
            $lateCount,
            $averageLateDays,
            $remindersCount
        );

        $lastInteraction = $this->lastInteraction($client, $invoices, $quotes);
        $isActive = $periodRevenue > 0 || $periodQuotes->isNotEmpty() || $outstandingAmount > 0;

        return [
            'id' => $client->id,
            'name' => $client->name,
            'initials' => $this->initials($client->name),
            'phone' => $client->phone,
            'email' => $client->email,
            'total_revenue' => $totalRevenue,
            'period_revenue' => $periodRevenue,
            'invoice_count_this_month' => $invoiceCountThisMonth,
            'invoice_count' => $invoices->count(),
            'quote_count' => $quotes->count(),
            'outstanding_amount' => $outstandingAmount,
            'average_payment_days' => $averagePaymentDays,
            'average_late_days' => $averageLateDays,
            'late_count' => $lateCount,
            'payment_score' => $score,
            'payment_label' => $score !== null ? $this->paymentLabel($score) : null,
            'payment_tone' => $score !== null ? $this->paymentTone($score) : null,
            'score_explanation' => $this->scoreExplanation($score, $outstandingAmount, $lateCount, $remindersCount),
            'total_collected' => $totalCollected,
            'reminders_count' => $remindersCount,
            'is_active' => $isActive,
            'is_inactive' => ! $isActive,
            'is_reliable' => $score !== null && $score >= 85 && $outstandingAmount === 0,
            'is_watch' => $outstandingAmount > 0 || ($score !== null && $score < 65),
            'has_frequent_delays' => $lateCount >= 2 || $averageLateDays >= 15,
            'last_interaction_label' => $lastInteraction['label'],
            'last_interaction_detail' => $lastInteraction['detail'],
        ];
    }

    private function resolvePeriodStart(string $period): mixed
    {
        return match ($period) {
            '30d' => now()->subDays(29)->startOfDay(),
            '90d' => now()->subDays(89)->startOfDay(),
            'year' => now()->startOfYear(),
            default => null,
        };
    }

    private function paymentScore(
        int $invoiceCount,
        int $outstandingAmount,
        int $totalRevenue,
        int $lateCount,
        int $averageLateDays,
        int $remindersCount
    ): ?int {
        if ($invoiceCount === 0) {
            return null;
        }

        $outstandingRatio = $totalRevenue > 0 ? $outstandingAmount / $totalRevenue : 0;

        $score = 100;
        $score -= min($lateCount * 10, 30);
        $score -= min($averageLateDays, 25);
        $score -= min((int) round($outstandingRatio * 35), 35);
        $score -= min($remindersCount * 4, 16);

        if ($outstandingAmount === 0 && $lateCount === 0) {
            $score += 5;
        }

        return max(5, min(100, $score));
    }

    /** @return array{label: string, detail: string} */
    private function lastInteraction(Client $client, Collection $invoices, Collection $quotes): array
    {
        $events = collect();

        $events = $events->merge($invoices->filter(fn (Invoice $invoice) => $invoice->issued_at)->map(fn (Invoice $invoice) => [
            'date' => $invoice->issued_at,
            'label' => $this->relativeDateLabel($invoice->issued_at, 'Facture envoyée'),
            'detail' => $invoice->reference ?? '—',
        ]));

        $events = $events->merge($invoices->filter(fn (Invoice $invoice) => $invoice->paid_at)->map(fn (Invoice $invoice) => [
            'date' => $invoice->paid_at,
            'label' => $this->relativeDateLabel($invoice->paid_at, 'Paiement reçu'),
            'detail' => $invoice->reference ?? '—',
        ]));

        $events = $events->merge($invoices->flatMap(fn (Invoice $invoice) => $invoice->reminders->filter(fn ($reminder) => $reminder->sent_at)->map(
            fn ($reminder) => [
                'date' => $reminder->sent_at,
                'label' => $this->relativeDateLabel($reminder->sent_at, 'Relancé'),
                'detail' => $this->channelLabel($reminder->channel),
            ]
        )));

        $events = $events->merge($quotes->filter(fn ($quote) => $quote->issued_at)->map(fn ($quote) => [
            'date' => $quote->issued_at,
            'label' => $this->relativeDateLabel($quote->issued_at, 'Devis envoyé'),
            'detail' => $quote->reference ?? '—',
        ]));

        $latest = $events->sortByDesc('date')->first();

        if ($latest) {
            return [
                'label' => $latest['label'],
                'detail' => $latest['detail'],
            ];
        }

        return [
            'label' => 'Aucune interaction récente',
            'detail' => $client->email ?: ($client->phone ?: 'Client sans activité'),
        ];
    }

    private function relativeDateLabel(mixed $date, string $prefix): string
    {
        if (! $date) {
            return $prefix;
        }

        $days = (int) $date->diffInDays(now());

        return match (true) {
            $days === 0 => $prefix.' aujourd’hui',
            $days === 1 => $prefix.' hier',
            default => $prefix.' il y a '.$days.' jours',
        };
    }

    private function channelLabel(ReminderChannel $channel): string
    {
        return match ($channel) {
            ReminderChannel::WhatsApp => 'WhatsApp',
            ReminderChannel::Sms => 'SMS',
            ReminderChannel::Email => 'Email',
        };
    }

    private function paymentLabel(int $score): string
    {
        return match (true) {
            $score >= 85 => 'Fiable',
            $score >= 65 => 'Correct',
            $score >= 45 => 'À surveiller',
            default => 'Risqué',
        };
    }

    private function paymentTone(int $score): string
    {
        return match (true) {
            $score >= 85 => 'emerald',
            $score >= 65 => 'teal',
            $score >= 45 => 'amber',
            default => 'rose',
        };
    }

    private function scoreExplanation(?int $score, int $outstandingAmount, int $lateCount, int $remindersCount): string
    {
        if ($score === null) {
            return 'Aucune facture enregistrée pour le moment.';
        }

        if ($outstandingAmount === 0 && $lateCount === 0) {
            return 'Aucun impayé ni retard récent.';
        }

        if ($score < 45) {
            return 'Risque élevé lié aux impayés, retards et relances.';
        }

        if ($remindersCount > 0) {
            return 'Score basé sur les délais, les retards et les relances.';
        }

        return 'Score basé sur les délais moyens et les montants ouverts.';
    }

    private function initials(string $name): string
    {
        return collect(explode(' ', $name))
            ->filter()
            ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->take(2)
            ->join('');
    }

    private function invoiceStatusLabel(InvoiceStatus $status): string
    {
        return match ($status) {
            InvoiceStatus::Draft => 'Brouillon',
            InvoiceStatus::Sent => 'Envoyée',
            InvoiceStatus::Certified => 'Certifiée',
            InvoiceStatus::CertificationFailed => 'Certification à revoir',
            InvoiceStatus::PartiallyPaid => 'Partiellement payée',
            InvoiceStatus::Paid => 'Payée',
            InvoiceStatus::Overdue => 'En retard',
            InvoiceStatus::Cancelled => 'Annulée',
        };
    }

    private function invoiceStatusTone(InvoiceStatus $status): string
    {
        return match ($status) {
            InvoiceStatus::Paid => 'emerald',
            InvoiceStatus::PartiallyPaid, InvoiceStatus::Overdue, InvoiceStatus::CertificationFailed => 'amber',
            InvoiceStatus::Cancelled => 'rose',
            InvoiceStatus::Draft => 'slate',
            default => 'sky',
        };
    }

    private function quoteStatusLabel(QuoteStatus $status): string
    {
        return match ($status) {
            QuoteStatus::Draft => 'Brouillon',
            QuoteStatus::Sent => 'Envoyé',
            QuoteStatus::Accepted => 'Accepté',
            QuoteStatus::Declined => 'Refusé',
            QuoteStatus::Expired => 'Expiré',
        };
    }

    private function quoteStatusTone(QuoteStatus $status): string
    {
        return match ($status) {
            QuoteStatus::Accepted => 'green',
            QuoteStatus::Sent => 'blue',
            QuoteStatus::Declined => 'red',
            QuoteStatus::Draft, QuoteStatus::Expired => 'gray',
        };
    }
}
