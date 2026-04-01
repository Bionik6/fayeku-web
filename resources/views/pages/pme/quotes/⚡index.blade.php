<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Auth\Models\Company;
use Modules\PME\Clients\Models\Client;
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

    public bool $showEditQuoteModal = false;

    public string $editingQuoteId = '';

    public string $quoteReference = '';

    public string $quoteClientId = '';

    public string $quoteIssuedAt = '';

    public string $quoteValidUntil = '';

    public string $quoteNotes = '';

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
    public function clients(): array
    {
        if (! $this->company) {
            return [];
        }

        return Client::query()
            ->where('company_id', $this->company->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Client $client) => [
                'id' => $client->id,
                'name' => $client->name,
            ])
            ->all();
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

    public function openEditQuoteModal(string $quoteId): void
    {
        abort_unless($this->company, 403);

        $quote = Quote::query()
            ->where('company_id', $this->company->id)
            ->with('client')
            ->findOrFail($quoteId);

        $this->editingQuoteId = $quote->id;
        $this->quoteReference = $quote->reference ?? '';
        $this->quoteClientId = $quote->client_id ?? '';
        $this->quoteIssuedAt = $quote->issued_at?->format('Y-m-d') ?? '';
        $this->quoteValidUntil = $quote->valid_until?->format('Y-m-d') ?? '';
        $this->quoteNotes = $quote->notes ?? '';
        $this->resetValidation();
        $this->showEditQuoteModal = true;
    }

    public function saveQuoteUpdates(): void
    {
        abort_unless($this->company, 403);

        $quote = Quote::query()
            ->where('company_id', $this->company->id)
            ->findOrFail($this->editingQuoteId);

        $validated = $this->validate([
            'quoteReference' => ['required', 'string', 'max:255'],
            'quoteClientId' => ['required', 'string', 'exists:clients,id'],
            'quoteIssuedAt' => ['required', 'date'],
            'quoteValidUntil' => ['required', 'date', 'after_or_equal:quoteIssuedAt'],
            'quoteNotes' => ['nullable', 'string', 'max:1000'],
        ], [
            'quoteReference.required' => __('La référence du devis est requise.'),
            'quoteClientId.required' => __('Le client est requis.'),
            'quoteValidUntil.after_or_equal' => __('La date de validité doit être postérieure ou égale à la date d\'émission.'),
        ]);

        abort_unless(
            Client::query()
                ->where('company_id', $this->company->id)
                ->whereKey($validated['quoteClientId'])
                ->exists(),
            403
        );

        $quote->update([
            'reference' => trim($validated['quoteReference']),
            'client_id' => $validated['quoteClientId'],
            'issued_at' => $validated['quoteIssuedAt'],
            'valid_until' => $validated['quoteValidUntil'],
            'notes' => trim((string) ($validated['quoteNotes'] ?? '')) ?: null,
        ]);

        $this->showEditQuoteModal = false;
        $this->editingQuoteId = '';
        $this->selectedQuoteId = $quote->id;
        $this->flushCaches();
        $this->refreshKpis();
        unset($this->rows, $this->statusCounts, $this->selectedQuote, $this->clients);

        $this->dispatch('toast', type: 'success', title: __('Le devis a été mis à jour.'));
    }

    public function deleteQuote(string $quoteId): void
    {
        abort_unless($this->company, 403);

        $quote = Quote::query()
            ->where('company_id', $this->company->id)
            ->findOrFail($quoteId);

        $quote->delete();

        if ($this->selectedQuoteId === $quoteId) {
            $this->selectedQuoteId = null;
        }

        if ($this->editingQuoteId === $quoteId) {
            $this->showEditQuoteModal = false;
            $this->editingQuoteId = '';
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

        $invoice = app(QuoteService::class)->convertToInvoice($quote, $this->company);

        $this->redirect(route('pme.invoices.edit', $invoice), navigate: true);
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
                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-sm font-semibold text-amber-700">
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
                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-sm font-semibold text-emerald-700">
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
                <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-1 text-sm font-semibold text-rose-700">
                    {{ __('Expirés') }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Devis expirés') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-rose-500">{{ $expiredQuotes }}</p>
        </article>

    </section>

    {{-- Bloc C -- Filtres --}}
    <section class="app-shell-panel p-4 md:p-5">

        {{-- Filtre Statut --}}
        <div class="flex flex-wrap gap-2">
            @php
                $statusTabs = [
                    'all'      => ['label' => 'Tous',      'dot' => null],
                    'draft'    => ['label' => 'Brouillon', 'dot' => 'bg-slate-400'],
                    'sent'     => ['label' => 'Envoyé',    'dot' => 'bg-blue-500'],
                    'accepted' => ['label' => 'Accepté',   'dot' => 'bg-emerald-500'],
                    'declined' => ['label' => 'Refusé',    'dot' => 'bg-rose-500'],
                    'expired'  => ['label' => 'Expiré',    'dot' => 'bg-slate-400'],
                ];
            @endphp
            @foreach ($statusTabs as $key => $tab)
                @php $count = $this->statusCounts[$key] ?? 0; @endphp
                @if ($key === 'all' || $count > 0)
                    <button
                        wire:click="setStatusFilter('{{ $key }}')"
                        @class([
                            'inline-flex items-center gap-1.5 rounded-full px-3.5 py-1 text-sm font-semibold transition',
                            'bg-ink text-white shadow-sm'                                    => $statusFilter === $key,
                            'bg-slate-100 text-slate-600 hover:bg-slate-200'                 => $statusFilter !== $key,
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

        {{-- Recherche + Periode --}}
        <div class="mt-3 flex flex-wrap gap-3">
            <div class="relative flex-1 min-w-48">
                <flux:icon name="magnifying-glass" class="absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-500" />
                <input
                    wire:model.live.debounce.300ms="search"
                    type="search"
                    placeholder="{{ __('Référence, client…') }}"
                    class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 py-2.5 pl-10 pr-4 text-sm focus:border-primary/50 focus:outline-none focus:ring-1 focus:ring-primary/20"
                />
            </div>
            <x-select-native>
                <select
                    wire:model.live="period"
                    class="col-start-1 row-start-1 appearance-none rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-2.5 pr-8 text-sm text-slate-700 focus:border-primary/50 focus:outline-none"
                >
                    <option value="">{{ __('Toutes les périodes') }}</option>
                    @foreach ($this->availablePeriods as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </x-select-native>
        </div>

    </section>

    {{-- Bloc D -- Table --}}
    <section class="app-shell-panel overflow-hidden">

        @php
            $rows = $this->rows;
            $hasAny = count($this->allRows()) > 0;
        @endphp

        @if (count($rows) > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/80">
                            <th class="px-6 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Référence') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Client') }}</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-slate-500">{{ __('Montant TTC') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Date émission') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Valide jusqu\'au') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Statut') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Facture') }}</th>
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
                                        <span class="ml-1.5 inline-flex items-center rounded-full bg-teal-50 px-2 py-0.5 text-xs font-semibold text-teal-700">{{ __('Facturé') }}</span>
                                    @endif
                                </td>

                                {{-- Client --}}
                                <td class="px-4 py-4">
                                    <span class="font-semibold text-ink">{{ $row['client_name'] }}</span>
                                </td>

                                {{-- Montant TTC --}}
                                <td class="px-4 py-4 text-right font-semibold text-ink">
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
                                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-sm font-semibold ring-1 ring-inset {{ $statusConfig['class'] }}">
                                        {{ $statusConfig['label'] }}
                                    </span>
                                </td>

                                {{-- Facture --}}
                                <td class="px-4 py-4" x-on:click.stop>
                                    @if ($row['has_invoice'])
                                        <a
                                            href="{{ route('pme.invoices.index') }}"
                                            wire:navigate
                                            class="text-sm font-semibold text-primary hover:underline"
                                        >{{ __('Ouvrir') }}</a>
                                    @else
                                        <span class="text-sm text-slate-400">{{ __('Aucune') }}</span>
                                    @endif
                                </td>

                                {{-- Actions --}}
                                <td class="px-4 py-4" x-on:click.stop>
                                    <flux:dropdown position="bottom" align="end">
                                        <button type="button"
                                            class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-1.5 text-sm font-semibold text-slate-600 transition hover:border-primary/30 hover:text-primary">
                                            {{ __('Actions') }}
                                            <flux:icon name="chevron-down" class="size-3.5" />
                                        </button>
                                        <flux:menu>
                                            <flux:menu.item wire:click="viewQuote('{{ $row['id'] }}')">
                                                <flux:icon name="eye" class="size-4 text-slate-500" />
                                                {{ __('Voir le devis') }}
                                            </flux:menu.item>

                                            @if (in_array($row['status_value'], ['draft', 'sent']))
                                                <flux:menu.item :href="route('pme.quotes.edit', $row['id'])" wire:navigate>
                                                    <flux:icon name="pencil-square" class="size-4 text-slate-500" />
                                                    {{ __('Modifier le devis') }}
                                                </flux:menu.item>
                                            @endif

                                            @if (in_array($row['status_value'], ['sent', 'accepted']) && ! $row['has_invoice'])
                                                <flux:menu.separator />
                                                <flux:menu.item wire:click="convertToInvoice('{{ $row['id'] }}')"
                                                    wire:confirm="{{ __('Convertir ce devis en facture ?') }}">
                                                    <flux:icon name="document-arrow-up" class="size-4 text-primary" />
                                                    {{ __('Convertir en facture') }}
                                                </flux:menu.item>
                                            @endif

                                            @if ($row['status_value'] === 'sent')
                                                <flux:menu.separator />
                                                <flux:menu.item wire:click="markAsAccepted('{{ $row['id'] }}')">
                                                    <flux:icon name="check-circle" class="size-4 text-emerald-500" />
                                                    {{ __('Marquer comme accepté') }}
                                                </flux:menu.item>
                                                <flux:menu.item wire:click="markAsDeclined('{{ $row['id'] }}')">
                                                    <flux:icon name="x-circle" class="size-4 text-rose-500" />
                                                    {{ __('Marquer comme refusé') }}
                                                </flux:menu.item>
                                            @endif

                                            @if ($row['status_value'] === 'draft')
                                                <flux:menu.separator />
                                                <flux:menu.item
                                                    variant="danger"
                                                    wire:click="deleteQuote('{{ $row['id'] }}')"
                                                    wire:confirm="{{ __('Supprimer définitivement ce devis ?') }}"
                                                >
                                                    <flux:icon name="trash" class="size-4 text-rose-400" />
                                                    {{ __('Supprimer le devis') }}
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

    </section>

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
                            <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold {{ $statusConfig['class'] }}">
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
                                        wire:click="convertToInvoice('{{ $q->id }}')"
                                        wire:confirm="{{ __('Convertir ce devis en facture ?') }}"
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

    {{-- Edit quote modal --}}
    @if ($showEditQuoteModal)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            wire:click.self="$set('showEditQuoteModal', false)"
            x-data
            @keydown.escape.window="$wire.set('showEditQuoteModal', false)"
        >
            <div class="relative w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                <form wire:submit="saveQuoteUpdates">
                    <div class="flex items-start justify-between border-b border-slate-100 px-7 py-6">
                        <div>
                            <h2 class="text-lg font-semibold text-ink">{{ __('Éditer le devis') }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ __('Modifiez les informations principales du devis.') }}</p>
                        </div>
                        <button
                            type="button"
                            wire:click="$set('showEditQuoteModal', false)"
                            class="ml-4 shrink-0 rounded-full border border-slate-200 p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                        >
                            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="max-h-[70vh] overflow-y-auto px-7 py-6">
                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ __('Référence') }}</label>
                                <input
                                    wire:model="quoteReference"
                                    type="text"
                                    class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                                />
                                @error('quoteReference') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ __('Client') }}</label>
                                <x-select-native>
                                    <select
                                        wire:model="quoteClientId"
                                        class="col-start-1 row-start-1 w-full appearance-none rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 pr-8 text-sm text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                                    >
                                        <option value="">{{ __('Choisir un client…') }}</option>
                                        @foreach ($this->clients as $client)
                                            <option value="{{ $client['id'] }}">{{ $client['name'] }}</option>
                                        @endforeach
                                    </select>
                                </x-select-native>
                                @error('quoteClientId') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ __('Date d\'émission') }}</label>
                                <input
                                    wire:model="quoteIssuedAt"
                                    type="date"
                                    class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                                />
                                @error('quoteIssuedAt') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ __('Valide jusqu\'au') }}</label>
                                <input
                                    wire:model="quoteValidUntil"
                                    type="date"
                                    class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                                />
                                @error('quoteValidUntil') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            <div class="md:col-span-2">
                                <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ __('Notes') }}</label>
                                <textarea
                                    wire:model="quoteNotes"
                                    rows="4"
                                    class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                                ></textarea>
                                @error('quoteNotes') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50/50 px-7 py-4">
                        <button
                            type="button"
                            wire:click="$set('showEditQuoteModal', false)"
                            class="inline-flex items-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary"
                        >
                            {{ __('Annuler') }}
                        </button>
                        <button
                            type="submit"
                            class="inline-flex items-center rounded-2xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-strong"
                        >
                            {{ __('Enregistrer') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</div>
