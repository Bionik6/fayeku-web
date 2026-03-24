<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Auth\Models\Company;
use Modules\PME\Invoicing\Enums\InvoiceStatus;
use Modules\PME\Invoicing\Enums\QuoteStatus;
use Modules\PME\Invoicing\Models\Invoice;
use Modules\PME\Invoicing\Models\Quote;

new #[Title('Factures & Devis')] #[Layout('layouts::pme')] class extends Component {
    #[Url(as: 'type')]
    public string $typeFilter = 'all';

    #[Url(as: 'statut')]
    public string $statusFilter = 'all';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'periode')]
    public string $period = '';

    public ?Company $company = null;

    public string $currentMonth = '';

    public int $invoiceCount = 0;

    public int $pendingQuoteCount = 0;

    public int $invoicedAmount = 0;

    public int $actionRequiredCount = 0;

    public function mount(): void
    {
        $this->currentMonth = ucfirst(now()->locale('fr_FR')->translatedFormat('F Y'));
        $this->company = auth()->user()->companies()->where('type', 'sme')->first();

        if (! $this->company) {
            return;
        }

        // KPI: factures émises ce mois (hors brouillon/annulée)
        $this->invoiceCount = Invoice::query()
            ->where('company_id', $this->company->id)
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Cancelled])
            ->whereMonth('issued_at', now()->month)
            ->whereYear('issued_at', now()->year)
            ->count();

        // KPI: devis en attente (sent)
        $this->pendingQuoteCount = Quote::query()
            ->where('company_id', $this->company->id)
            ->where('status', QuoteStatus::Sent)
            ->count();

        // KPI: montant HT facturé ce mois
        $this->invoicedAmount = Invoice::query()
            ->where('company_id', $this->company->id)
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Cancelled])
            ->whereMonth('issued_at', now()->month)
            ->whereYear('issued_at', now()->year)
            ->sum('subtotal');

        // KPI: documents nécessitant une action (overdue + sent invoices)
        $this->actionRequiredCount = Invoice::query()
            ->where('company_id', $this->company->id)
            ->whereIn('status', [InvoiceStatus::Overdue, InvoiceStatus::Sent])
            ->count();
    }

    /** @return array<int, array<string, mixed>> */
    #[Computed]
    public function rows(): array
    {
        $rows = $this->rowsBeforeAggregateFilters();
        $rows = $this->applyTypeFilter($rows);

        return $this->applyStatusFilter($rows);
    }

    /** @return array<string, int> */
    #[Computed]
    public function typeCounts(): array
    {
        if (! $this->company) {
            return ['all' => 0, 'invoice' => 0, 'quote' => 0];
        }

        $base = $this->applyStatusFilter($this->rowsBeforeAggregateFilters());

        return [
            'all'     => count($base),
            'invoice' => count(array_filter($base, fn ($r) => $r['type'] === 'invoice')),
            'quote'   => count(array_filter($base, fn ($r) => $r['type'] === 'quote')),
        ];
    }

    /** @return array<string, int> */
    #[Computed]
    public function statusCounts(): array
    {
        $base = $this->applyTypeFilter($this->rowsBeforeAggregateFilters());
        $counts = ['all' => count($base)];

        foreach ($base as $row) {
            $counts[$row['status_value']] = ($counts[$row['status_value']] ?? 0) + 1;
        }

        return $counts;
    }

    /** @return array<string, string> */
    #[Computed]
    public function availablePeriods(): array
    {
        $periods = [];
        for ($i = 0; $i < 6; $i++) {
            $date = now()->subMonths($i);
            $periods[$date->format('Y-m')] = ucfirst($date->locale('fr_FR')->translatedFormat('F Y'));
        }

        return $periods;
    }

    public function markAsPaid(string $invoiceId): void
    {
        $invoice = Invoice::query()
            ->where('company_id', $this->company?->id)
            ->findOrFail($invoiceId);

        $invoice->update([
            'status' => InvoiceStatus::Paid,
            'amount_paid' => $invoice->total,
            'paid_at' => now(),
        ]);

        unset($this->rows, $this->typeCounts, $this->statusCounts);

        // Refresh KPI
        $this->actionRequiredCount = Invoice::query()
            ->where('company_id', $this->company->id)
            ->whereIn('status', [InvoiceStatus::Overdue, InvoiceStatus::Sent])
            ->count();
    }

    public function setTypeFilter(string $type): void
    {
        $this->typeFilter = $type;
        $this->statusFilter = 'all';
        unset($this->rows, $this->typeCounts, $this->statusCounts);
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        unset($this->rows, $this->statusCounts);
    }

    public function updatedPeriod(string $value): void
    {
        unset($this->rows, $this->typeCounts, $this->statusCounts);
    }

    /** @return array<int, array<string, mixed>> */
    private function baseRows(): array
    {
        if (! $this->company) {
            return [];
        }

        $invoices = Invoice::query()
            ->where('company_id', $this->company->id)
            ->whereNotIn('status', [InvoiceStatus::Cancelled])
            ->get()
            ->map(fn ($inv) => ['type' => 'invoice', 'status_value' => $inv->status->value]);

        $quotes = Quote::query()
            ->where('company_id', $this->company->id)
            ->get()
            ->map(fn ($q) => ['type' => 'quote', 'status_value' => $q->status->value]);

        return collect([...$invoices, ...$quotes])->values()->toArray();
    }

    /** @return array<int, array<string, mixed>> */
    private function typeFilteredRows(): array
    {
        return $this->applyTypeFilter($this->baseRows());
    }

    /** @return array<int, array<string, mixed>> */
    private function rowsBeforeAggregateFilters(): array
    {
        return $this->applySearchFilter($this->applyPeriodFilter($this->allRows()));
    }

    /** @return array<int, array<string, mixed>> */
    private function allRows(): array
    {
        if (! $this->company) {
            return [];
        }

        $invoices = Invoice::query()
            ->where('company_id', $this->company->id)
            ->whereNotIn('status', [InvoiceStatus::Cancelled])
            ->with('client')
            ->get()
            ->map(function ($inv) {
                $delayDays = $inv->due_at ? (int) abs(now()->diffInDays($inv->due_at)) : 0;
                $isOverdue = $inv->status === InvoiceStatus::Overdue;

                return [
                    'id'           => $inv->id,
                    'type'         => 'invoice',
                    'reference'    => $inv->reference ?? '—',
                    'client_name'  => $inv->client?->name ?? '—',
                    'subtotal'     => $inv->subtotal,
                    'tax_amount'   => $inv->tax_amount,
                    'total'        => $inv->total,
                    'issued_at'    => $inv->issued_at,
                    'due_at'       => $inv->due_at,
                    'status_value' => $inv->status->value,
                    'is_overdue'   => $isOverdue,
                    'delay_days'   => $isOverdue ? $delayDays : 0,
                    'amount_paid'  => $inv->amount_paid,
                ];
            });

        $quotes = Quote::query()
            ->where('company_id', $this->company->id)
            ->with('client')
            ->get()
            ->map(function ($q) {
                $isExpired = $q->status === QuoteStatus::Expired ||
                    ($q->valid_until && $q->valid_until->isPast() && $q->status === QuoteStatus::Sent);

                return [
                    'id'           => $q->id,
                    'type'         => 'quote',
                    'reference'    => $q->reference ?? '—',
                    'client_name'  => $q->client?->name ?? '—',
                    'subtotal'     => $q->subtotal,
                    'tax_amount'   => $q->tax_amount,
                    'total'        => $q->total,
                    'issued_at'    => $q->issued_at,
                    'due_at'       => $q->valid_until,
                    'status_value' => $q->status->value,
                    'is_overdue'   => $isExpired,
                    'delay_days'   => 0,
                    'amount_paid'  => 0,
                ];
            });

        return collect([...$invoices, ...$quotes])->sortByDesc('issued_at')->values()->toArray();
    }

    /** @param array<int, array<string, mixed>> $rows
     *  @return array<int, array<string, mixed>>
     */
    private function applyPeriodFilter(array $rows): array
    {
        if ($this->period === '') {
            return $rows;
        }

        [$year, $month] = explode('-', $this->period);

        return array_values(array_filter(
            $rows,
            fn ($row) => $row['issued_at']
                && $row['issued_at']->year == $year
                && $row['issued_at']->month == $month
        ));
    }

    /** @param array<int, array<string, mixed>> $rows
     *  @return array<int, array<string, mixed>>
     */
    private function applySearchFilter(array $rows): array
    {
        if ($this->search === '') {
            return $rows;
        }

        $term = mb_strtolower($this->search);

        return array_values(array_filter(
            $rows,
            fn ($row) => str_contains(mb_strtolower($row['reference']), $term)
                || str_contains(mb_strtolower($row['client_name']), $term)
        ));
    }

    /** @param array<int, array<string, mixed>> $rows
     *  @return array<int, array<string, mixed>>
     */
    private function applyTypeFilter(array $rows, ?string $type = null): array
    {
        $type ??= $this->typeFilter;

        if ($type === 'all') {
            return $rows;
        }

        return array_values(array_filter($rows, fn ($row) => $row['type'] === $type));
    }

    /** @param array<int, array<string, mixed>> $rows
     *  @return array<int, array<string, mixed>>
     */
    private function applyStatusFilter(array $rows, ?string $status = null): array
    {
        $status ??= $this->statusFilter;

        if ($status === 'all') {
            return $rows;
        }

        return array_values(array_filter($rows, fn ($row) => $row['status_value'] === $status));
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- Bloc A — En-tête --}}
    <section class="app-shell-panel overflow-hidden">
        <div class="flex flex-col gap-4 p-6 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.24em] text-teal">
                    {{ __('Facturation') }} · {{ $currentMonth }}
                    @if (count($this->rows) > 0)
                        · {{ count($this->rows) }} {{ count($this->rows) > 1 ? __('documents') : __('document') }}
                    @endif
                </p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-ink">{{ __('Factures & Devis') }}</h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ __('Gérez vos factures clients et vos devis en attente de validation.') }}
                </p>
            </div>

            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <button type="button"
                    class="inline-flex items-center gap-2 rounded-2xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-strong">
                    <flux:icon name="plus" class="size-4" />
                    {{ __('Nouvelle facture') }}
                </button>
                <button type="button"
                    class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary">
                    {{ __('Nouveau devis') }}
                </button>
            </div>
        </div>
    </section>

    {{-- Bloc B — 4 KPI cards --}}
    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">

        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-teal-50">
                    <flux:icon name="document-text" class="size-5 text-primary" />
                </div>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-500">
                    {{ __('Ce mois') }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Factures émises') }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ $currentMonth }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-ink">{{ $invoiceCount }}</p>
        </article>

        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-amber-50">
                    <flux:icon name="document-duplicate" class="size-5 text-amber-500" />
                </div>
                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
                    {{ __('En attente') }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Devis en attente') }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ __('Réponse attendue') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-amber-500">{{ $pendingQuoteCount }}</p>
        </article>

        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-primary/8">
                    <flux:icon name="banknotes" class="size-5 text-primary" />
                </div>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-500">
                    HT
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Montant facturé') }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ __('HT ce mois') }}</p>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-primary">
                @if ($invoicedAmount > 0)
                    {{ number_format($invoicedAmount, 0, ',', ' ') }} FCFA
                @else
                    —
                @endif
            </p>
        </article>

        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-rose-50">
                    <flux:icon name="exclamation-triangle" class="size-5 text-rose-500" />
                </div>
                <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700">
                    {{ __('Action requise') }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('En retard ou en attente') }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ __('Factures à traiter') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-rose-500">{{ $actionRequiredCount }}</p>
        </article>

    </section>

    {{-- Bloc C — Filtres --}}
    <section class="app-shell-panel p-4 md:p-5">

        {{-- Filtre Type --}}
        <div class="flex flex-wrap gap-2">
            @foreach ([
                'all'     => ['label' => 'Tous',     'count' => $this->typeCounts['all']],
                'invoice' => ['label' => 'Factures', 'count' => $this->typeCounts['invoice']],
                'quote'   => ['label' => 'Devis',    'count' => $this->typeCounts['quote']],
            ] as $key => $tab)
                <button
                    wire:click="setTypeFilter('{{ $key }}')"
                    @class([
                        'inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold transition',
                        'bg-primary text-white shadow-sm'                                          => $typeFilter === $key,
                        'bg-white border border-slate-200 text-slate-600 hover:border-primary/30' => $typeFilter !== $key,
                    ])
                >
                    {{ __($tab['label']) }}
                    <span @class([
                        'inline-flex items-center justify-center rounded-full px-1.5 py-0.5 text-xs font-bold min-w-[1.25rem]',
                        'bg-white/20 text-white'    => $typeFilter === $key,
                        'bg-slate-100 text-slate-500' => $typeFilter !== $key,
                    ])>{{ $tab['count'] }}</span>
                </button>
            @endforeach
        </div>

        {{-- Filtre Statut --}}
        <div class="mt-3 flex flex-wrap gap-2">
            @php
                $statusTabs = [
                    'all'           => ['label' => 'Tous',       'dot' => null],
                    'draft'         => ['label' => 'Brouillon',  'dot' => 'bg-slate-400'],
                    'sent'          => ['label' => 'Envoyée',    'dot' => 'bg-blue-500'],
                    'paid'          => ['label' => 'Payée',      'dot' => 'bg-accent'],
                    'overdue'       => ['label' => 'En retard',  'dot' => 'bg-rose-500'],
                    'partially_paid' => ['label' => 'Part. payée', 'dot' => 'bg-amber-500'],
                    'accepted'      => ['label' => 'Accepté',   'dot' => 'bg-accent'],
                    'declined'      => ['label' => 'Refusé',    'dot' => 'bg-rose-500'],
                ];
            @endphp
            @foreach ($statusTabs as $key => $tab)
                @php $count = $key === 'all' ? ($this->statusCounts['all'] ?? 0) : ($this->statusCounts[$key] ?? 0); @endphp
                @if ($key === 'all' || $count > 0)
                    <button
                        wire:click="setStatusFilter('{{ $key }}')"
                        @class([
                            'inline-flex items-center gap-1.5 rounded-full px-3.5 py-1 text-xs font-semibold transition',
                            'bg-ink text-white shadow-sm'                                                  => $statusFilter === $key,
                            'bg-slate-100 text-slate-600 hover:bg-slate-200'                               => $statusFilter !== $key,
                        ])
                    >
                        @if ($tab['dot'])
                            <span class="size-1.5 rounded-full {{ $tab['dot'] }}"></span>
                        @endif
                        {{ __($tab['label']) }}
                        @if ($key !== 'all')
                            <span class="opacity-70">{{ $count }}</span>
                        @endif
                    </button>
                @endif
            @endforeach
        </div>

        {{-- Recherche + Période --}}
        <div class="mt-3 flex flex-wrap gap-3">
            <div class="relative flex-1 min-w-48">
                <flux:icon name="magnifying-glass" class="absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input
                    wire:model.live.debounce.300ms="search"
                    type="search"
                    placeholder="{{ __('Référence, client…') }}"
                    class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 py-2.5 pl-10 pr-4 text-sm focus:border-primary/50 focus:outline-none focus:ring-1 focus:ring-primary/20"
                />
            </div>
            <select
                wire:model.live="period"
                class="rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-2.5 text-sm text-slate-700 focus:border-primary/50 focus:outline-none"
            >
                <option value="">{{ __('Toutes les périodes') }}</option>
                @foreach ($this->availablePeriods as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

    </section>

    {{-- Bloc D — Table --}}
    <section class="app-shell-panel overflow-hidden">

        @php
            $rows = $this->rows;
            $hasAny = count($this->baseRows()) > 0;
        @endphp

        @if (count($rows) > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/80">
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500">{{ __('Type') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">{{ __('Référence') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">{{ __('Client') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500">{{ __('Montant TTC') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">{{ __('Date émission') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">{{ __('Échéance') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">{{ __('Statut') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($rows as $row)
                            @php
                                $statusConfig = match ($row['status_value']) {
                                    'paid'          => ['label' => 'Payée',       'class' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'],
                                    'sent'          => ['label' => 'Envoyée',     'class' => 'bg-blue-50 text-blue-700 ring-blue-600/20'],
                                    'certified'     => ['label' => 'Certifiée',   'class' => 'bg-blue-50 text-blue-700 ring-blue-600/20'],
                                    'overdue'       => ['label' => 'En retard',   'class' => 'bg-rose-50 text-rose-700 ring-rose-600/20'],
                                    'partially_paid' => ['label' => 'Part. payée', 'class' => 'bg-amber-50 text-amber-700 ring-amber-600/20'],
                                    'draft'         => ['label' => 'Brouillon',  'class' => 'bg-slate-100 text-slate-600 ring-slate-600/20'],
                                    'accepted'      => ['label' => 'Accepté',    'class' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'],
                                    'declined'      => ['label' => 'Refusé',     'class' => 'bg-rose-50 text-rose-700 ring-rose-600/20'],
                                    'expired'       => ['label' => 'Expiré',     'class' => 'bg-slate-100 text-slate-500 ring-slate-500/20'],
                                    default         => ['label' => ucfirst($row['status_value']), 'class' => 'bg-slate-100 text-slate-600 ring-slate-600/20'],
                                };
                            @endphp
                            <tr wire:key="doc-{{ $row['id'] }}" class="transition hover:bg-slate-50/60">

                                {{-- Type --}}
                                <td class="px-6 py-4">
                                    <span @class([
                                        'inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-semibold',
                                        'bg-teal-50 text-teal-700'     => $row['type'] === 'invoice',
                                        'bg-violet-50 text-violet-700' => $row['type'] === 'quote',
                                    ])>
                                        {{ $row['type'] === 'invoice' ? __('Facture') : __('Devis') }}
                                    </span>
                                </td>

                                {{-- Référence --}}
                                <td class="px-4 py-4 font-semibold text-ink">{{ $row['reference'] }}</td>

                                {{-- Client --}}
                                <td class="px-4 py-4">
                                    <span class="font-semibold text-ink">{{ $row['client_name'] }}</span>
                                </td>

                                {{-- Montant TTC --}}
                                <td class="px-4 py-4 text-right font-semibold text-ink">
                                    {{ number_format($row['total'], 0, ',', ' ') }} F
                                </td>

                                {{-- Date émission --}}
                                <td class="px-4 py-4 text-slate-500">
                                    @if ($row['issued_at'])
                                        {{ $row['issued_at']->locale('fr_FR')->translatedFormat('j M. Y') }}
                                    @else
                                        —
                                    @endif
                                </td>

                                {{-- Échéance --}}
                                <td class="px-4 py-4">
                                    @if ($row['due_at'])
                                        @if ($row['is_overdue'])
                                            <span class="font-bold text-rose-500">
                                                {{ $row['due_at']->locale('fr_FR')->translatedFormat('j M.') }} ! J+{{ $row['delay_days'] }}
                                            </span>
                                        @else
                                            <span class="text-slate-500">{{ $row['due_at']->locale('fr_FR')->translatedFormat('j M. Y') }}</span>
                                        @endif
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>

                                {{-- Statut --}}
                                <td class="px-4 py-4">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $statusConfig['class'] }}">
                                        {{ $statusConfig['label'] }}
                                    </span>
                                </td>

                                {{-- Actions --}}
                                <td class="px-4 py-4">
                                    <flux:dropdown position="bottom" align="end">
                                        <button type="button"
                                            class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-1.5 text-sm font-semibold text-slate-600 transition hover:border-primary/30 hover:text-primary">
                                            {{ __('Actions') }}
                                            <flux:icon name="chevron-down" class="size-3.5" />
                                        </button>
                                        <flux:menu>
                                            <flux:menu.item :href="route('pme.invoices.index')" wire:navigate>
                                                <flux:icon name="eye" class="size-4 text-slate-400" />
                                                {{ __('Voir le document') }}
                                            </flux:menu.item>

                                            @if ($row['type'] === 'invoice' && in_array($row['status_value'], ['sent', 'overdue', 'partially_paid']))
                                                <flux:menu.item :href="route('pme.collection.index')" wire:navigate>
                                                    <flux:icon name="bell" class="size-4 text-slate-400" />
                                                    {{ __('Relancer le client') }}
                                                </flux:menu.item>
                                            @endif

                                            @if ($row['type'] === 'invoice' && !in_array($row['status_value'], ['paid', 'cancelled', 'draft']))
                                                <flux:menu.separator />
                                                <flux:menu.item wire:click="markAsPaid('{{ $row['id'] }}')"
                                                    wire:confirm="{{ __('Marquer cette facture comme payée ?') }}">
                                                    <flux:icon name="check-circle" class="size-4 text-slate-400" />
                                                    {{ __('Marquer comme payée') }}
                                                </flux:menu.item>
                                            @endif

                                            @if ($row['type'] === 'quote' && $row['status_value'] === 'accepted')
                                                <flux:menu.separator />
                                                <flux:menu.item disabled>
                                                    <flux:icon name="document-arrow-up" class="size-4 text-slate-400" />
                                                    {{ __('Convertir en facture') }}
                                                </flux:menu.item>
                                            @endif
                                        </flux:menu>
                                    </flux:dropdown>
                                </td>

                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        @elseif (! $hasAny)
            {{-- État vide — aucun document --}}
            <div class="flex flex-col items-center justify-center p-16 text-center">
                <div class="flex size-14 items-center justify-center rounded-2xl bg-mist">
                    <x-app.icon name="invoice" class="size-6 text-primary" />
                </div>
                <h3 class="mt-4 text-lg font-semibold text-ink">{{ __('Aucun document pour le moment') }}</h3>
                <p class="mt-2 max-w-sm text-sm text-slate-500">
                    {{ __('Commencez par créer votre première facture ou votre premier devis.') }}
                </p>
                <button type="button"
                    class="mt-6 inline-flex items-center gap-2 rounded-2xl bg-primary px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-strong">
                    <flux:icon name="plus" class="size-4" />
                    {{ __('Créer une facture') }}
                </button>
            </div>

        @else
            {{-- État vide — filtre sans résultat --}}
            <div class="flex flex-col items-center justify-center p-12 text-center">
                <div class="flex size-12 items-center justify-center rounded-2xl bg-slate-100">
                    <flux:icon name="magnifying-glass" class="size-5 text-slate-400" />
                </div>
                <p class="mt-4 font-semibold text-ink">{{ __('Aucun document ne correspond') }}</p>
                <p class="mt-1 text-sm text-slate-400">{{ __('Essayez de modifier vos filtres ou votre recherche.') }}</p>
                <button
                    wire:click="$set('search', '')"
                    x-on:click="$wire.typeFilter = 'all'; $wire.statusFilter = 'all'; $wire.period = ''"
                    class="mt-4 text-sm font-semibold text-primary hover:underline"
                >
                    {{ __('Réinitialiser les filtres') }}
                </button>
            </div>
        @endif

    </section>

</div>
