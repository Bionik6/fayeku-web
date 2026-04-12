<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Auth\Models\Company;
use Modules\PME\Invoicing\Enums\QuoteStatus;
use Modules\PME\Invoicing\Models\Invoice;
use Modules\PME\Invoicing\Models\Quote;
use Modules\PME\Invoicing\Services\QuoteService;

new #[Title('Devis')] #[Layout('layouts::pme')] class extends Component {
    #[Url(as: 'statut')]
    public string $statusFilter = 'all';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'periode')]
    public string $period = '';

    public ?Company $company = null;

    public string $currentMonth = '';

    public int $totalQuotes = 0;

    public int $pendingQuotes = 0;

    public int $acceptedQuotes = 0;

    public int $expiredQuotes = 0;

    public ?string $selectedQuoteId = null;

    public ?string $selectedInvoiceId = null;

    public ?string $confirmConvertId = null;

    public ?string $confirmDeleteId = null;

    /** @var array<int, array<string, mixed>>|null */
    private ?array $allRowsCache = null;

    /** @var array<int, array<string, mixed>>|null */
    private ?array $rowsBeforeAggregateFiltersCache = null;

    public function mount(): void
    {
        $this->currentMonth = format_month(now());
        $this->company = auth()->user()->smeCompany();

        if (! $this->company) {
            return;
        }

        $this->refreshKpis();
    }

    #[Computed]
    public function selectedQuote(): ?Quote
    {
        if (! $this->selectedQuoteId || ! $this->company) {
            return null;
        }

        return Quote::query()
            ->with(['client', 'lines', 'invoice'])
            ->where('company_id', $this->company->id)
            ->whereKey($this->selectedQuoteId)
            ->first();
    }

    public function viewQuote(string $quoteId): void
    {
        abort_unless($this->company, 403);

        Quote::query()
            ->where('company_id', $this->company->id)
            ->findOrFail($quoteId);

        $this->selectedQuoteId = $quoteId;
    }

    public function closeQuote(): void
    {
        $this->selectedQuoteId = null;
    }

    #[Computed]
    public function selectedInvoice(): ?Invoice
    {
        if (! $this->selectedInvoiceId || ! $this->company) {
            return null;
        }

        return Invoice::query()
            ->with(['client', 'lines'])
            ->where('company_id', $this->company->id)
            ->whereKey($this->selectedInvoiceId)
            ->first();
    }

    public function viewInvoice(string $invoiceId): void
    {
        abort_unless($this->company, 403);

        Invoice::query()
            ->where('company_id', $this->company->id)
            ->findOrFail($invoiceId);

        $this->selectedInvoiceId = $invoiceId;
    }

    public function closeInvoice(): void
    {
        $this->selectedInvoiceId = null;
    }

    public function confirmDelete(string $id): void
    {
        $this->confirmDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmDeleteId = null;
    }

    public function deleteQuote(string $quoteId): void
    {
        $this->confirmDeleteId = null;
        abort_unless($this->company, 403);

        $quote = Quote::query()
            ->where('company_id', $this->company->id)
            ->findOrFail($quoteId);

        $quote->delete();

        if ($this->selectedQuoteId === $quoteId) {
            $this->selectedQuoteId = null;
        }

        $this->flushCaches();
        $this->refreshKpis();
        unset($this->rows, $this->statusCounts, $this->selectedQuote);

        $this->dispatch('toast', type: 'success', title: __('Le devis a été supprimé.'));
    }

    public function markAsAccepted(string $quoteId): void
    {
        abort_unless($this->company, 403);

        $quote = Quote::query()
            ->where('company_id', $this->company->id)
            ->findOrFail($quoteId);

        app(QuoteService::class)->markAsAccepted($quote);

        $this->flushCaches();
        $this->refreshKpis();
        unset($this->rows, $this->statusCounts, $this->selectedQuote);

        $this->dispatch('toast', type: 'success', title: __('Le devis a été marqué comme accepté.'));
    }

    public function markAsDeclined(string $quoteId): void
    {
        abort_unless($this->company, 403);

        $quote = Quote::query()
            ->where('company_id', $this->company->id)
            ->findOrFail($quoteId);

        app(QuoteService::class)->markAsDeclined($quote);

        $this->flushCaches();
        $this->refreshKpis();
        unset($this->rows, $this->statusCounts, $this->selectedQuote);

        $this->dispatch('toast', type: 'success', title: __('Le devis a été marqué comme refusé.'));
    }

    public function convertToInvoice(string $quoteId): void
    {
        abort_unless($this->company, 403);

        $quote = Quote::query()
            ->where('company_id', $this->company->id)
            ->with('lines')
            ->findOrFail($quoteId);

        try {
            $invoice = app(QuoteService::class)->convertToInvoice($quote, $this->company);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->dispatch('toast', type: 'error', title: $e->getMessage());

            return;
        }

        $this->redirect(route('pme.invoices.edit', $invoice), navigate: true);
    }

    public function confirmConvert(string $id): void
    {
        $this->confirmConvertId = $id;
    }

    public function cancelConvert(): void
    {
        $this->confirmConvertId = null;
    }

    private function refreshKpis(): void
    {
        if (! $this->company) {
            $this->totalQuotes = 0;
            $this->pendingQuotes = 0;
            $this->acceptedQuotes = 0;
            $this->expiredQuotes = 0;

            return;
        }

        $this->totalQuotes = Quote::query()
            ->where('company_id', $this->company->id)
            ->count();

        $this->pendingQuotes = Quote::query()
            ->where('company_id', $this->company->id)
            ->where('status', QuoteStatus::Sent)
            ->count();

        $this->acceptedQuotes = Quote::query()
            ->where('company_id', $this->company->id)
            ->where('status', QuoteStatus::Accepted)
            ->count();

        $this->expiredQuotes = Quote::query()
            ->where('company_id', $this->company->id)
            ->where(function ($q) {
                $q->where('status', QuoteStatus::Expired)
                    ->orWhere(function ($q2) {
                        $q2->where('status', QuoteStatus::Sent)
                            ->where('valid_until', '<', now());
                    });
            })
            ->count();
    }

    #[Computed]
    public function rows(): array
    {
        return $this->applyStatusFilter($this->rowsBeforeAggregateFilters());
    }

    #[Computed]
    public function statusCounts(): array
    {
        $base = $this->rowsBeforeAggregateFilters();
        $counts = ['all' => count($base)];

        foreach ($base as $row) {
            $counts[$row['status_value']] = ($counts[$row['status_value']] ?? 0) + 1;
        }

        return $counts;
    }

    #[Computed]
    public function availablePeriods(): array
    {
        $periods = [];
        for ($i = 0; $i < 6; $i++) {
            $date = now()->subMonths($i);
            $periods[$date->format('Y-m')] = format_month($date);
        }

        return $periods;
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        unset($this->rows, $this->statusCounts);
    }

    public function updatedPeriod(string $value): void
    {
        unset($this->rows, $this->statusCounts);
    }

    private function rowsBeforeAggregateFilters(): array
    {
        if ($this->rowsBeforeAggregateFiltersCache !== null) {
            return $this->rowsBeforeAggregateFiltersCache;
        }

        return $this->rowsBeforeAggregateFiltersCache = $this->applySearchFilter($this->applyPeriodFilter($this->allRows()));
    }

    private function allRows(): array
    {
        if ($this->allRowsCache !== null) {
            return $this->allRowsCache;
        }

        if (! $this->company) {
            return $this->allRowsCache = [];
        }

        return $this->allRowsCache = Quote::query()
            ->where('company_id', $this->company->id)
            ->with(['client', 'invoice'])
            ->orderByDesc('issued_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($q) {
                $isExpired = $q->status === QuoteStatus::Expired ||
                    ($q->valid_until && $q->valid_until->isPast() && $q->status === QuoteStatus::Sent);

                return [
                    'id'           => $q->id,
                    'reference'    => $q->reference ?? '—',
                    'client_name'  => $q->client?->name ?? '—',
                    'subtotal'     => $q->subtotal,
                    'tax_amount'   => $q->tax_amount,
                    'total'        => $q->total,
                    'currency'     => $q->currency,
                    'issued_at'    => $q->issued_at,
                    'valid_until'  => $q->valid_until,
                    'status_value' => $isExpired ? 'expired' : $q->status->value,
                    'has_invoice'  => $q->invoice !== null,
                    'invoice_id'   => $q->invoice?->id,
                ];
            })
            ->sortByDesc('issued_at')
            ->values()
            ->toArray();
    }

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

    private function applyStatusFilter(array $rows, ?string $status = null): array
    {
        $status ??= $this->statusFilter;

        if ($status === 'all') {
            return $rows;
        }

        return array_values(array_filter($rows, fn ($row) => $row['status_value'] === $status));
    }

    private function flushCaches(): void
    {
        $this->allRowsCache = null;
        $this->rowsBeforeAggregateFiltersCache = null;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    @if (session('success'))
        <div x-init="$dispatch('toast', { type: 'success', title: '{{ session('success') }}' })"></div>
    @endif

    {{-- Bloc A -- En-tete --}}
    <section class="app-shell-panel overflow-hidden">
        <div class="flex flex-col gap-4 p-6 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.24em] text-teal">
                    {{ __('Devis') }} · {{ $currentMonth }}
                    @if (count($this->rows) > 0)
                        · {{ count($this->rows) }} {{ count($this->rows) > 1 ? __('devis') : __('devis') }}
                    @endif
                </p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-ink">{{ __('Devis') }}</h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ __('Gérez vos devis clients et convertissez-les en factures.') }}
                </p>
            </div>

            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <a
                    href="{{ route('pme.quotes.create') }}"
                    wire:navigate
                    class="inline-flex items-center gap-2 rounded-2xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-strong"
                >
                    <flux:icon name="plus" class="size-4" />
                    {{ __('Nouveau devis') }}
                </a>
            </div>
        </div>
    </section>

    {{-- Bloc B -- 4 KPI cards --}}
    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">

        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-teal-50">
                    <flux:icon name="document-duplicate" class="size-5 text-primary" />
                </div>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Total devis') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-ink">{{ $totalQuotes }}</p>
        </article>

        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-amber-50">
                    <flux:icon name="clock" class="size-5 text-amber-500" />
                </div>
                <span class="inline-flex whitespace-nowrap items-center rounded-full bg-amber-50 px-2.5 py-1 text-sm font-semibold text-amber-700">
                    {{ __('En attente') }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Devis en attente') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-amber-500">{{ $pendingQuotes }}</p>
        </article>

        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-emerald-50">
                    <flux:icon name="check-circle" class="size-5 text-emerald-500" />
                </div>
                <span class="inline-flex whitespace-nowrap items-center rounded-full bg-emerald-50 px-2.5 py-1 text-sm font-semibold text-emerald-700">
                    {{ __('Acceptés') }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Devis acceptés') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-emerald-500">{{ $acceptedQuotes }}</p>
        </article>

        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-rose-50">
                    <flux:icon name="exclamation-triangle" class="size-5 text-rose-500" />
                </div>
                <span class="inline-flex whitespace-nowrap items-center rounded-full bg-rose-50 px-2.5 py-1 text-sm font-semibold text-rose-700">
                    {{ __('Expirés') }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Devis expirés') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-rose-500">{{ $expiredQuotes }}</p>
        </article>

    </section>

    {{-- Bloc C+D -- Filtres + Table --}}
    <x-ui.table-panel
        :title="__('Devis')"
        :description="__('Liste des devis envoyés et en cours.')"
        :filterLabel="__('Filtrer par statut')"
    >
        <x-slot:filters>
            @foreach ([
                'all'      => ['label' => 'Tous',      'dot' => null,           'activeClass' => 'bg-primary text-white',     'badgeInactive' => 'bg-slate-100 text-slate-500'],
                'draft'    => ['label' => 'Brouillon', 'dot' => 'bg-slate-400', 'activeClass' => 'bg-slate-500 text-white',   'badgeInactive' => 'bg-slate-100 text-slate-600'],
                'sent'     => ['label' => 'Envoyé',    'dot' => 'bg-blue-500',  'activeClass' => 'bg-blue-500 text-white',    'badgeInactive' => 'bg-blue-100 text-blue-700'],
                'accepted' => ['label' => 'Accepté',   'dot' => 'bg-accent',    'activeClass' => 'bg-emerald-600 text-white', 'badgeInactive' => 'bg-emerald-100 text-emerald-700'],
                'declined' => ['label' => 'Refusé',    'dot' => 'bg-rose-500',  'activeClass' => 'bg-rose-500 text-white',    'badgeInactive' => 'bg-rose-100 text-rose-700'],
                'expired'  => ['label' => 'Expiré',    'dot' => 'bg-slate-400', 'activeClass' => 'bg-slate-500 text-white',   'badgeInactive' => 'bg-slate-100 text-slate-600'],
            ] as $key => $tab)
                @php $count = $this->statusCounts[$key] ?? 0; @endphp
                @if ($key === 'all' || $count > 0)
                    <x-ui.filter-chip
                        wire:click="setStatusFilter('{{ $key }}')"
                        :label="__($tab['label'])"
                        :dot="$tab['dot']"
                        :active="$statusFilter === $key"
                        :activeClass="$tab['activeClass']"
                        :badgeInactive="$tab['badgeInactive']"
                        :count="$count"
                    />
                @endif
            @endforeach
        </x-slot:filters>

        <x-slot:search>
            <div class="flex flex-wrap gap-3">
                <div class="relative min-w-48 flex-1">
                    <svg class="absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                    <input
                        wire:model.live.debounce.300ms="search"
                        type="search"
                        placeholder="{{ __('Référence, client…') }}"
                        class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 py-3 pl-10 pr-4 text-sm text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                    />
                </div>
                <x-select-native>
                    <select
                        wire:model.live="period"
                        class="col-start-1 row-start-1 appearance-none rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 pr-8 text-sm text-slate-700 focus:border-primary/50 focus:outline-none"
                    >
                        <option value="">{{ __('Toutes les périodes') }}</option>
                        @foreach ($this->availablePeriods as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </x-select-native>
            </div>
        </x-slot:search>

        @php
            $rows = $this->rows;
            $hasAny = count($this->allRows()) > 0;
        @endphp

        @if (count($rows) > 0)
            <div class="overflow-x-auto border-t border-slate-100">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/80">
                            <th class="px-6 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Référence') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Client') }}</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-slate-500">{{ __('Montant HT') }}</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-slate-500">{{ __('Montant TTC') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Date émission') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Valide jusqu\'au') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Statut') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($rows as $row)
                            @php
                                $statusConfig = match ($row['status_value']) {
                                    'accepted' => ['label' => 'Accepté',   'class' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'],
                                    'sent'     => ['label' => 'Envoyé',    'class' => 'bg-blue-50 text-blue-700 ring-blue-600/20'],
                                    'draft'    => ['label' => 'Brouillon', 'class' => 'bg-slate-100 text-slate-600 ring-slate-600/20'],
                                    'declined' => ['label' => 'Refusé',    'class' => 'bg-rose-50 text-rose-700 ring-rose-600/20'],
                                    'expired'  => ['label' => 'Expiré',    'class' => 'bg-slate-100 text-slate-500 ring-slate-500/20'],
                                    default    => ['label' => ucfirst($row['status_value']), 'class' => 'bg-slate-100 text-slate-600 ring-slate-600/20'],
                                };
                            @endphp
                            <tr
                                wire:key="quote-{{ $row['id'] }}"
                                class="cursor-pointer transition hover:bg-slate-50/60"
                                wire:click="viewQuote('{{ $row['id'] }}')"
                            >

                                {{-- Référence --}}
                                <td class="px-6 py-4 font-semibold text-ink">
                                    {{ $row['reference'] }}
                                    @if ($row['has_invoice'])
                                        <span class="ml-1.5 inline-flex whitespace-nowrap items-center rounded-full bg-teal-50 px-2 py-0.5 text-xs font-semibold text-teal-700">{{ __('Facturé') }}</span>
                                    @endif
                                </td>

                                {{-- Client --}}
                                <td class="px-4 py-4">
                                    <span class="font-semibold text-ink">{{ $row['client_name'] }}</span>
                                </td>

                                {{-- Montant HT --}}
                                <td class="whitespace-nowrap px-4 py-4 text-right text-slate-500">
                                    {{ format_money($row['subtotal'], $row['currency'], compact: true) }}
                                </td>

                                {{-- Montant TTC --}}
                                <td class="whitespace-nowrap px-4 py-4 text-right font-semibold text-ink">
                                    {{ format_money($row['total'], $row['currency'], compact: true) }}
                                </td>

                                {{-- Date émission --}}
                                <td class="px-4 py-4 text-slate-500">
                                    {{ format_date($row['issued_at']) }}
                                </td>

                                {{-- Valide jusqu'au --}}
                                <td class="px-4 py-4">
                                    @if ($row['valid_until'])
                                        @if ($row['status_value'] === 'expired')
                                            <span class="font-bold text-rose-500">
                                                {{ format_date($row['valid_until']) }}
                                            </span>
                                        @else
                                            <span class="text-slate-500">{{ format_date($row['valid_until']) }}</span>
                                        @endif
                                    @else
                                        <span class="text-slate-500">—</span>
                                    @endif
                                </td>

                                {{-- Statut --}}
                                <td class="px-4 py-4">
                                    <span class="inline-flex whitespace-nowrap items-center gap-1 rounded-full px-2.5 py-0.5 text-sm font-semibold ring-1 ring-inset {{ $statusConfig['class'] }}">
                                        {{ $statusConfig['label'] }}
                                    </span>
                                </td>

                                {{-- Actions --}}
                                <td class="px-4 py-4" x-on:click.stop>
                                    <x-ui.dropdown>
                                        <x-ui.dropdown-item wire:click="viewQuote('{{ $row['id'] }}')">
                                            <x-slot:icon>
                                                <svg class="size-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                </svg>
                                            </x-slot:icon>
                                            {{ __('Voir le devis') }}
                                        </x-ui.dropdown-item>
                                        @if ($row['has_invoice'])
                                            <x-ui.dropdown-separator />
                                            <x-ui.dropdown-item wire:click="viewInvoice('{{ $row['invoice_id'] }}')">
                                                <x-slot:icon>
                                                    <svg class="size-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                                    </svg>
                                                </x-slot:icon>
                                                {{ __('Voir la facture') }}
                                            </x-ui.dropdown-item>
                                            <x-ui.dropdown-item :href="route('pme.invoices.pdf', $row['invoice_id'])" target="_blank">
                                                <x-slot:icon>
                                                    <svg class="size-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                                    </svg>
                                                </x-slot:icon>
                                                {{ __('Afficher en PDF') }}
                                            </x-ui.dropdown-item>
                                        @endif
                                        @if (in_array($row['status_value'], ['draft', 'sent']))
                                            <x-ui.dropdown-item :href="route('pme.quotes.edit', $row['id'])" wire:navigate>
                                                <x-slot:icon>
                                                    <svg class="size-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                                    </svg>
                                                </x-slot:icon>
                                                {{ __('Modifier le devis') }}
                                            </x-ui.dropdown-item>
                                        @endif
                                        @if (in_array($row['status_value'], ['sent', 'accepted']) && ! $row['has_invoice'])
                                            <x-ui.dropdown-separator />
                                            <x-ui.dropdown-item
                                                wire:click="confirmConvert('{{ $row['id'] }}')"
                                            >
                                                <x-slot:icon>
                                                    <svg class="size-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m6.75 12-3-3m0 0-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                                    </svg>
                                                </x-slot:icon>
                                                {{ __('Convertir en facture') }}
                                            </x-ui.dropdown-item>
                                        @endif
                                        @if ($row['status_value'] === 'sent')
                                            <x-ui.dropdown-separator />
                                            <x-ui.dropdown-item wire:click="markAsAccepted('{{ $row['id'] }}')">
                                                <x-slot:icon>
                                                    <svg class="size-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                    </svg>
                                                </x-slot:icon>
                                                {{ __('Marquer comme accepté') }}
                                            </x-ui.dropdown-item>
                                            <x-ui.dropdown-item wire:click="markAsDeclined('{{ $row['id'] }}')">
                                                <x-slot:icon>
                                                    <svg class="size-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                    </svg>
                                                </x-slot:icon>
                                                {{ __('Marquer comme refusé') }}
                                            </x-ui.dropdown-item>
                                        @endif
                                        @if ($row['status_value'] === 'draft')
                                            <x-ui.dropdown-separator />
                                            <x-ui.dropdown-item
                                                wire:click="confirmDelete('{{ $row['id'] }}')"
                                                :destructive="true"
                                            >
                                                <x-slot:icon>
                                                    <svg class="size-4 shrink-0 text-rose-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                    </svg>
                                                </x-slot:icon>
                                                {{ __('Supprimer le devis') }}
                                            </x-ui.dropdown-item>
                                        @endif
                                    </x-ui.dropdown>
                                </td>

                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        @elseif (! $hasAny)
            {{-- empty state --}}
            <div class="flex flex-col items-center justify-center p-16 text-center">
                <div class="flex size-14 items-center justify-center rounded-2xl bg-mist">
                    <x-app.icon name="invoice" class="size-6 text-primary" />
                </div>
                <h3 class="mt-4 text-lg font-semibold text-ink">{{ __('Aucun devis pour le moment') }}</h3>
                <p class="mt-2 max-w-sm text-sm text-slate-500">
                    {{ __('Commencez par créer votre premier devis.') }}
                </p>
                <a href="{{ route('pme.quotes.create') }}" wire:navigate
                    class="mt-6 inline-flex items-center gap-2 rounded-2xl bg-primary px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-strong">
                    <flux:icon name="plus" class="size-4" />
                    {{ __('Créer un devis') }}
                </a>
            </div>

        @else
            {{-- no results --}}
            <div class="flex flex-col items-center justify-center p-12 text-center">
                <div class="flex size-12 items-center justify-center rounded-2xl bg-slate-100">
                    <flux:icon name="magnifying-glass" class="size-5 text-slate-500" />
                </div>
                <p class="mt-4 font-semibold text-ink">{{ __('Aucun devis ne correspond') }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ __('Essayez de modifier vos filtres ou votre recherche.') }}</p>
                <button
                    wire:click="$set('search', '')"
                    x-on:click="$wire.statusFilter = 'all'; $wire.period = ''"
                    class="mt-4 text-sm font-semibold text-primary hover:underline"
                >
                    {{ __('Réinitialiser les filtres') }}
                </button>
            </div>
        @endif

    </x-ui.table-panel>


    {{-- Invoice detail modal --}}
    @if ($this->selectedInvoice)
        <x-invoices.detail-modal :invoice="$this->selectedInvoice" close-action="closeInvoice" />
    @endif

    <x-ui.confirm-modal
        :confirm-id="$confirmDeleteId"
        :title="__('Supprimer le devis')"
        :description="__('Cette action est irréversible. Le devis sera définitivement supprimé.')"
        confirm-action="deleteQuote"
        cancel-action="cancelDelete"
        :confirm-label="__('Supprimer')"
    />

    <x-ui.confirm-modal
        :confirm-id="$confirmConvertId"
        :title="__('Convertir en facture')"
        :description="__('Ce devis sera converti en facture brouillon. Vous pourrez la modifier avant de l\'envoyer.')"
        confirm-action="convertToInvoice"
        cancel-action="cancelConvert"
        :confirm-label="__('Convertir')"
        variant="primary"
    />

    {{-- Quote detail modal --}}
    @if ($this->selectedQuote)
        @php
            $q = $this->selectedQuote;
            $client = $q->client;
            $statusConfig = match ($q->status) {
                \Modules\PME\Invoicing\Enums\QuoteStatus::Accepted => ['label' => 'Accepté', 'class' => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20'],
                \Modules\PME\Invoicing\Enums\QuoteStatus::Sent => ['label' => 'Envoyé', 'class' => 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20'],
                \Modules\PME\Invoicing\Enums\QuoteStatus::Draft => ['label' => 'Brouillon', 'class' => 'bg-slate-100 text-slate-600'],
                \Modules\PME\Invoicing\Enums\QuoteStatus::Declined => ['label' => 'Refusé', 'class' => 'bg-rose-50 text-rose-700'],
                \Modules\PME\Invoicing\Enums\QuoteStatus::Expired => ['label' => 'Expiré', 'class' => 'bg-slate-100 text-slate-500'],
                default => ['label' => ucfirst($q->status->value), 'class' => 'bg-slate-100 text-slate-600'],
            };
        @endphp
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            wire:click.self="closeQuote"
        >
            <div class="relative w-full max-w-[1200px] overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-slate-100 px-10 py-7">
                    <div>
                        <p class="text-sm font-semibold tracking-[0.24em] text-slate-400">{{ __('Devis') }}</p>
                        <h2 class="mt-1 text-xl font-bold text-ink">{{ $q->reference }}</h2>
                        <div class="mt-1 flex items-center gap-3">
                            <p class="text-sm text-slate-500">
                                {{ __('Émis le') }} {{ format_date($q->issued_at) }}
                                @if ($q->valid_until)
                                    &nbsp;·&nbsp;
                                    {{ __('Valide jusqu\'au') }} {{ format_date($q->valid_until) }}
                                @endif
                            </p>
                            <span class="inline-flex whitespace-nowrap items-center rounded-full px-3 py-1 text-sm font-semibold {{ $statusConfig['class'] }}">
                                {{ $statusConfig['label'] }}
                            </span>
                        </div>
                    </div>
                    <button
                        wire:click="closeQuote"
                        class="rounded-full border border-slate-200 p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                    >
                        <flux:icon name="x-mark" class="size-5" />
                    </button>
                </div>

                <div class="max-h-[80vh] overflow-y-auto">
                    <div class="grid grid-cols-1 gap-0 lg:grid-cols-3">
                        <div class="col-span-2 px-10 py-8">
                            @if ($client)
                                <div class="mb-6">
                                    <p class="mb-3 text-sm font-semibold text-slate-500">{{ __('Destinataire') }}</p>
                                    <div class="rounded-xl border border-slate-100 bg-slate-50/60 px-5 py-4">
                                        <p class="font-semibold text-ink">{{ $client->name }}</p>
                                        @if ($client->phone)
                                            <p class="mt-1 flex items-center gap-1.5 text-sm text-slate-500">
                                                <flux:icon name="phone" class="size-3.5 shrink-0" />
                                                {{ format_phone($client->phone) }}
                                            </p>
                                        @endif
                                        @if ($client->email)
                                            <p class="mt-0.5 flex items-center gap-1.5 text-sm text-slate-500">
                                                <flux:icon name="envelope" class="size-3.5 shrink-0" />
                                                {{ $client->email }}
                                            </p>
                                        @endif
                                        @if ($client->address)
                                            <p class="mt-0.5 flex items-center gap-1.5 text-sm text-slate-500">
                                                <flux:icon name="map-pin" class="size-3.5 shrink-0" />
                                                {{ $client->address }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            <div>
                                <p class="mb-3 text-sm font-semibold text-slate-500">{{ __('Détail des prestations') }}</p>
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-slate-100 text-left">
                                            <th class="pb-2 pr-4 text-sm font-semibold text-slate-500">{{ __('Description') }}</th>
                                            <th class="pb-2 px-4 text-right text-sm font-semibold text-slate-500 whitespace-nowrap">{{ __('Qté') }}</th>
                                            <th class="pb-2 px-4 text-right text-sm font-semibold text-slate-500 whitespace-nowrap">{{ __('PU HT') }}</th>
                                            <th class="pb-2 px-4 text-right text-sm font-semibold text-slate-500 whitespace-nowrap">{{ __('TVA') }}</th>
                                            <th class="pb-2 pl-4 text-right text-sm font-semibold text-slate-500 whitespace-nowrap">{{ __('Total HT') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50">
                                        @forelse ($q->lines as $line)
                                            <tr>
                                                <td class="py-3 pr-4 text-ink">{{ $line->description }}</td>
                                                <td class="py-3 px-4 text-right tabular-nums text-slate-600 whitespace-nowrap">{{ $line->quantity }}</td>
                                                <td class="py-3 px-4 text-right tabular-nums text-slate-600 whitespace-nowrap">
                                                    {{ format_money($line->unit_price, $q->currency) }}
                                                </td>
                                                <td class="py-3 px-4 text-right tabular-nums text-slate-500 whitespace-nowrap">{{ $line->tax_rate }} %</td>
                                                <td class="py-3 pl-4 text-right tabular-nums font-medium text-ink whitespace-nowrap">
                                                    {{ format_money($line->total, $q->currency) }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="py-4 text-center text-slate-400">{{ __('Aucune ligne.') }}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    <tfoot class="border-t border-slate-200">
                                        <tr>
                                            <td colspan="4" class="pt-4 pr-4 text-right text-sm text-slate-500">{{ __('Sous-total HT') }}</td>
                                            <td class="pt-4 pl-4 text-right tabular-nums text-sm text-ink whitespace-nowrap">
                                                {{ format_money($q->subtotal, $q->currency) }}
                                            </td>
                                        </tr>
                                        @if ($q->discount > 0)
                                            @php $discountAmount = (int) round($q->subtotal * $q->discount / 100); @endphp
                                            <tr>
                                                <td colspan="4" class="pt-1 pr-4 text-right text-sm text-emerald-600">{{ __('Réduction') }} ({{ $q->discount }} %)</td>
                                                <td class="pt-1 pl-4 text-right tabular-nums text-sm text-emerald-600 whitespace-nowrap">
                                                    − {{ format_money($discountAmount, $q->currency) }}
                                                </td>
                                            </tr>
                                        @endif
                                        <tr>
                                            <td colspan="4" class="pt-1 pr-4 text-right text-sm text-slate-500">{{ __('TVA') }}</td>
                                            <td class="pt-1 pl-4 text-right tabular-nums text-sm text-ink whitespace-nowrap">
                                                {{ format_money($q->tax_amount, $q->currency) }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="4" class="pt-2 pr-4 text-right text-base font-semibold text-ink">{{ __('Total TTC') }}</td>
                                            <td class="pt-2 pl-4 text-right tabular-nums text-base font-bold text-ink whitespace-nowrap">
                                                {{ format_money($q->total, $q->currency) }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <div class="border-t border-slate-100 bg-slate-50/60 px-8 py-8 lg:border-t-0 lg:border-l">
                            <p class="mb-4 text-sm font-semibold text-slate-500">{{ __('Récapitulatif') }}</p>
                            <dl class="space-y-3 text-sm">
                                <div class="flex justify-between">
                                    <dt class="text-slate-500">{{ __('Montant HT') }}</dt>
                                    <dd class="tabular-nums font-medium text-ink">{{ format_money($q->subtotal, $q->currency) }}</dd>
                                </div>
                                @if ($q->discount > 0)
                                    @php $discountAmount = (int) round($q->subtotal * $q->discount / 100); @endphp
                                    <div class="flex justify-between text-emerald-600">
                                        <dt>{{ __('Réduction') }} ({{ $q->discount }} %)</dt>
                                        <dd class="tabular-nums font-medium">− {{ format_money($discountAmount, $q->currency) }}</dd>
                                    </div>
                                @endif
                                <div class="flex justify-between">
                                    <dt class="text-slate-500">{{ __('TVA') }}</dt>
                                    <dd class="tabular-nums font-medium text-ink">{{ format_money($q->tax_amount, $q->currency) }}</dd>
                                </div>
                                <div class="flex justify-between border-t border-slate-200 pt-3">
                                    <dt class="font-semibold text-ink">{{ __('Total TTC') }}</dt>
                                    <dd class="tabular-nums text-lg font-bold text-ink">{{ format_money($q->total, $q->currency) }}</dd>
                                </div>
                            </dl>

                            @if (in_array($q->status, [\Modules\PME\Invoicing\Enums\QuoteStatus::Sent, \Modules\PME\Invoicing\Enums\QuoteStatus::Accepted]) && ! $q->invoice)
                                <div class="mt-6">
                                    <button
                                        wire:click="confirmConvert('{{ $q->id }}')"
                                        class="flex w-full items-center justify-center rounded-2xl bg-primary px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-strong"
                                    >
                                        <flux:icon name="document-arrow-up" class="mr-2 size-4" />
                                        {{ __('Convertir en facture') }}
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-slate-100 px-10 py-5">
                    <flux:button variant="ghost" wire:click="closeQuote">
                        {{ __('Fermer') }}
                    </flux:button>
                </div>
            </div>
        </div>
    @endif


</div>
