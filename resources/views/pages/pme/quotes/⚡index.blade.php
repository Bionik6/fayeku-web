<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Models\Auth\Company;
use App\Enums\PME\ProposalDocumentStatus;
use App\Enums\PME\ProposalDocumentType;
use App\Models\PME\Invoice;
use App\Models\PME\ProposalDocument;
use App\Services\PME\ProposalDocumentService;

new #[Title('Devis & Proformas')] #[Layout('layouts::pme')] class extends Component {
    #[Url(as: 'statut')]
    public string $statusFilter = 'all';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'periode')]
    public string $period = '';

    public ?Company $company = null;

    public string $currentMonth = '';

    public int $totalDocuments = 0;

    public int $totalQuotes = 0;

    public int $totalProformas = 0;

    public int $pendingDocuments = 0;

    public int $validatedDocuments = 0;

    public int $expiredDocuments = 0;

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

    public function viewDocument(string $documentId): void
    {
        abort_unless($this->company, 403);

        $document = $this->findDocumentOrFail($documentId);
        $route = $document->isProforma() ? 'pme.proformas.show' : 'pme.quotes.show';

        $this->redirect(route($route, $documentId), navigate: true);
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

    public function deleteDocument(string $documentId): void
    {
        $this->confirmDeleteId = null;
        abort_unless($this->company, 403);

        $document = $this->findDocumentOrFail($documentId);
        $message = $document->isProforma()
            ? __('La proforma a été supprimée.')
            : __('Le devis a été supprimé.');

        $document->delete();

        $this->flushCaches();
        $this->refreshKpis();
        unset($this->rows, $this->statusCounts);

        $this->dispatch('toast', type: 'success', title: $message);
    }

    public function markAsAccepted(string $documentId): void
    {
        abort_unless($this->company, 403);

        $document = $this->findDocumentOrFail($documentId);
        app(ProposalDocumentService::class)->markAsAccepted($document);

        $this->flushCaches();
        $this->refreshKpis();
        unset($this->rows, $this->statusCounts);

        $this->dispatch('toast', type: 'success', title: __('Le devis a été marqué comme accepté.'));
    }

    public function markAsDeclined(string $documentId): void
    {
        abort_unless($this->company, 403);

        $document = $this->findDocumentOrFail($documentId);
        app(ProposalDocumentService::class)->markAsDeclined($document);

        $this->flushCaches();
        $this->refreshKpis();
        unset($this->rows, $this->statusCounts);

        $message = $document->isProforma()
            ? __('La proforma a été marquée comme refusée.')
            : __('Le devis a été marqué comme refusé.');
        $this->dispatch('toast', type: 'success', title: $message);
    }

    public function markAsPoReceived(string $documentId): void
    {
        abort_unless($this->company, 403);

        $document = $this->findDocumentOrFail($documentId);
        app(ProposalDocumentService::class)->markAsPoReceived($document);

        $this->flushCaches();
        $this->refreshKpis();
        unset($this->rows, $this->statusCounts);

        $this->dispatch('toast', type: 'success', title: __('Bon de commande reçu : la proforma peut être convertie en facture.'));
    }

    public function convertToInvoice(string $documentId): void
    {
        abort_unless($this->company, 403);

        $document = $this->findDocumentOrFail($documentId);
        $document->load('lines');

        try {
            $invoice = app(ProposalDocumentService::class)->convertToInvoice($document, $this->company);
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

    private function findDocumentOrFail(string $id): ProposalDocument
    {
        abort_unless($this->company, 403);

        return ProposalDocument::query()
            ->where('company_id', $this->company->id)
            ->findOrFail($id);
    }

    private function refreshKpis(): void
    {
        if (! $this->company) {
            $this->totalDocuments = 0;
            $this->totalQuotes = 0;
            $this->totalProformas = 0;
            $this->pendingDocuments = 0;
            $this->validatedDocuments = 0;
            $this->expiredDocuments = 0;

            return;
        }

        $base = ProposalDocument::query()->where('company_id', $this->company->id);

        $this->totalQuotes = (clone $base)->ofType(ProposalDocumentType::Quote)->count();
        $this->totalProformas = (clone $base)->ofType(ProposalDocumentType::Proforma)->count();
        $this->totalDocuments = $this->totalQuotes + $this->totalProformas;

        $this->pendingDocuments = (clone $base)
            ->where('status', ProposalDocumentStatus::Sent)
            ->count();

        $this->validatedDocuments = (clone $base)
            ->whereIn('status', [ProposalDocumentStatus::Accepted, ProposalDocumentStatus::PoReceived])
            ->count();

        $this->expiredDocuments = (clone $base)
            ->where(function ($q) {
                $q->where('status', ProposalDocumentStatus::Expired)
                    ->orWhere(function ($q2) {
                        $q2->where('status', ProposalDocumentStatus::Sent)
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

        return $this->rowsBeforeAggregateFiltersCache = $this->applySearchFilter(
            $this->applyPeriodFilter($this->allRows())
        );
    }

    private function allRows(): array
    {
        if ($this->allRowsCache !== null) {
            return $this->allRowsCache;
        }

        if (! $this->company) {
            return $this->allRowsCache = [];
        }

        return $this->allRowsCache = ProposalDocument::query()
            ->where('company_id', $this->company->id)
            ->with(['client', 'invoice'])
            ->orderByDesc('issued_at')
            ->get()
            ->map(function (ProposalDocument $d) {
                $isExpired = $d->status === ProposalDocumentStatus::Expired
                    || ($d->valid_until && $d->valid_until->isPast() && $d->status === ProposalDocumentStatus::Sent);

                return [
                    'id'           => $d->id,
                    'type'         => $d->type->value,
                    'reference'    => $d->reference ?? '—',
                    'client_id'    => $d->client_id,
                    'client_name'  => $d->client?->name ?? '—',
                    'subtotal'     => $d->subtotal,
                    'tax_amount'   => $d->tax_amount,
                    'total'        => $d->total,
                    'currency'     => $d->currency,
                    'issued_at'    => $d->issued_at,
                    'valid_until'  => $d->valid_until,
                    'status_value' => $isExpired ? 'expired' : $d->status->value,
                    'has_invoice'  => $d->invoice !== null,
                    'invoice_id'   => $d->invoice?->id,
                    'invoice_public_code' => $d->invoice?->public_code,
                    'public_code'  => $d->public_code,
                    'dossier_reference' => $d->dossier_reference,
                ];
            })
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
        <div
            x-data
            x-init="$nextTick(() => window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'success', title: @js(session('success')) } })))"
        ></div>
    @endif

    {{-- Bloc A -- En-tete --}}
    <section class="app-shell-panel overflow-hidden">
        <div class="flex flex-col gap-4 p-6 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.24em] text-teal">
                    {{ __('Documents commerciaux') }} · {{ $currentMonth }}
                    @if (count($this->rows) > 0)
                        · {{ count($this->rows) }}
                    @endif
                </p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-ink">{{ __('Devis & Proformas') }}</h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ __('Gérez vos propositions commerciales pré-facturation et convertissez-les en factures.') }}
                </p>
            </div>

            <div class="flex shrink-0 flex-wrap items-center gap-2">
                {{-- Bouton dropdown : un seul point d'entrée pour la création.
                     Pattern teleport+fixed pour échapper à l'overflow-hidden de la section parente. --}}
                <div
                    x-data="{ open: false, top: 0, right: 0, width: 0 }"
                    class="inline-block"
                    @click.window="open = false"
                    @keydown.escape.window="open = false"
                >
                    <button
                        type="button"
                        x-ref="trigger"
                        @click.stop="
                            const wasOpen = open;
                            if (wasOpen) { open = false; return; }
                            const rect = $refs.trigger.getBoundingClientRect();
                            top = rect.bottom + 8;
                            right = window.innerWidth - rect.right;
                            width = Math.max(rect.width, 288);
                            open = true;
                        "
                        class="inline-flex items-center gap-2 rounded-2xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-strong"
                        :aria-expanded="open"
                    >
                        <flux:icon name="plus" class="size-4" />
                        {{ __('Nouveau document') }}
                        <svg class="size-3.5 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                        </svg>
                    </button>

                    <template x-teleport="body">
                        <div
                            x-show="open"
                            x-cloak
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            @click.stop
                            :style="`position: fixed; z-index: 9999; top: ${top}px; right: ${right}px; min-width: ${width}px`"
                            class="w-72 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-lg"
                        >
                            <a href="{{ route('pme.quotes.create') }}" wire:navigate
                               class="block border-b border-slate-100 px-5 py-4 transition hover:bg-amber-50/40">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center rounded-md bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">{{ __('Devis') }}</span>
                                    <span class="text-sm font-semibold text-ink">{{ __('Nouveau devis') }}</span>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">{{ __('Proposition commerciale signable par le client.') }}</p>
                            </a>
                            <a href="{{ route('pme.proformas.create') }}" wire:navigate
                               class="block px-5 py-4 transition hover:bg-blue-50/40">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center rounded-md bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700">{{ __('Proforma') }}</span>
                                    <span class="text-sm font-semibold text-ink">{{ __('Nouvelle proforma') }}</span>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">{{ __('Document pré-facture pour déclencher un bon de commande.') }}</p>
                            </a>
                        </div>
                    </template>
                </div>
            </div>
        </div>

    </section>

    {{-- Bloc B -- 4 KPI cards (combinés Devis + Proformas) --}}
    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">

        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-teal-50">
                    <flux:icon name="document-duplicate" class="size-5 text-primary" />
                </div>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Total documents') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-ink">{{ $totalDocuments }}</p>
            <div class="mt-3 flex flex-wrap items-center gap-1.5 text-xs">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-0.5 font-semibold text-amber-700">
                    <span>{{ __('Devis') }}</span>
                    <span class="text-amber-900">{{ $totalQuotes }}</span>
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-2.5 py-0.5 font-semibold text-blue-700">
                    <span>{{ __('Proforma') }}</span>
                    <span class="text-blue-900">{{ $totalProformas }}</span>
                </span>
            </div>
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
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Documents envoyés') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-amber-500">{{ $pendingDocuments }}</p>
        </article>

        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-emerald-50">
                    <flux:icon name="check-circle" class="size-5 text-emerald-500" />
                </div>
                <span class="inline-flex whitespace-nowrap items-center rounded-full bg-emerald-50 px-2.5 py-1 text-sm font-semibold text-emerald-700">
                    {{ __('Validés') }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Acceptés / BC reçus') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-emerald-500">{{ $validatedDocuments }}</p>
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
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Documents expirés') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-rose-500">{{ $expiredDocuments }}</p>
        </article>

    </section>

    {{-- Bloc C+D -- Filtres + Table --}}
    <x-ui.table-panel
        :title="__('Devis & Proformas')"
        :description="__('Liste de vos propositions commerciales pré-facturation.')"
        :filterLabel="__('Filtrer par statut')"
    >
        <x-slot:filters>
            @php
                // Statuts disponibles : union des statuts devis ET proforma.
                // Les statuts spécifiques (accepted devis-only, po_received/converted proforma-only)
                // s'affichent automatiquement seulement s'ils ont au moins une ligne.
                $statusTabs = [
                    'all'         => ['label' => 'Tous',      'dot' => null,             'activeClass' => 'bg-primary text-white',     'badgeInactive' => 'bg-slate-100 text-slate-500'],
                    'draft'       => ['label' => 'Brouillon', 'dot' => 'bg-slate-400',   'activeClass' => 'bg-slate-500 text-white',   'badgeInactive' => 'bg-slate-100 text-slate-600'],
                    'sent'        => ['label' => 'Envoyé',    'dot' => 'bg-blue-500',    'activeClass' => 'bg-blue-500 text-white',    'badgeInactive' => 'bg-blue-100 text-blue-700'],
                    'accepted'    => ['label' => 'Accepté',   'dot' => 'bg-accent',      'activeClass' => 'bg-emerald-600 text-white', 'badgeInactive' => 'bg-emerald-100 text-emerald-700'],
                    'po_received' => ['label' => 'BC reçu',   'dot' => 'bg-emerald-500', 'activeClass' => 'bg-emerald-600 text-white', 'badgeInactive' => 'bg-emerald-100 text-emerald-700'],
                    'converted'   => ['label' => 'Facturée',  'dot' => 'bg-teal-500',    'activeClass' => 'bg-teal-600 text-white',    'badgeInactive' => 'bg-teal-100 text-teal-700'],
                    'declined'    => ['label' => 'Refusé',    'dot' => 'bg-rose-500',    'activeClass' => 'bg-rose-500 text-white',    'badgeInactive' => 'bg-rose-100 text-rose-700'],
                    'expired'     => ['label' => 'Expiré',    'dot' => 'bg-slate-400',   'activeClass' => 'bg-slate-500 text-white',   'badgeInactive' => 'bg-slate-100 text-slate-600'],
                ];
            @endphp

            @foreach ($statusTabs as $key => $tab)
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
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Type') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Référence') }}</th>
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
                                $isProforma = $row['type'] === 'proforma';
                                $typeBadge = $isProforma
                                    ? ['code' => __('Proforma'), 'class' => 'bg-blue-50 text-blue-700 ring-blue-600/20']
                                    : ['code' => __('Devis'),    'class' => 'bg-amber-50 text-amber-700 ring-amber-600/20'];
                                $borderClass = $isProforma ? 'border-l-4 border-blue-400' : 'border-l-4 border-amber-300';
                                $statusConfig = match ($row['status_value']) {
                                    'accepted'    => ['label' => 'Accepté',   'class' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'],
                                    'po_received' => ['label' => 'BC reçu',   'class' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'],
                                    'converted'   => ['label' => 'Facturée',  'class' => 'bg-teal-50 text-teal-700 ring-teal-600/20'],
                                    'sent'        => ['label' => $isProforma ? 'Envoyée' : 'Envoyé', 'class' => 'bg-blue-50 text-blue-700 ring-blue-600/20'],
                                    'draft'       => ['label' => 'Brouillon', 'class' => 'bg-slate-100 text-slate-600 ring-slate-600/20'],
                                    'declined'    => ['label' => $isProforma ? 'Refusée' : 'Refusé', 'class' => 'bg-rose-50 text-rose-700 ring-rose-600/20'],
                                    'expired'     => ['label' => $isProforma ? 'Expirée' : 'Expiré', 'class' => 'bg-slate-100 text-slate-500 ring-slate-500/20'],
                                    default       => ['label' => ucfirst($row['status_value']), 'class' => 'bg-slate-100 text-slate-600 ring-slate-600/20'],
                                };
                                $showRoute = $isProforma
                                    ? route('pme.proformas.show', $row['id'])
                                    : route('pme.quotes.show', $row['id']);
                            @endphp
                            <tr
                                wire:key="{{ $row['type'] }}-{{ $row['id'] }}"
                                class="cursor-pointer transition hover:bg-slate-50/60 {{ $borderClass }}"
                                onclick="if(!event.target.closest('[x-on\\:click\\.stop]')) Livewire.navigate('{{ $showRoute }}')"
                            >

                                {{-- Type pill --}}
                                <td class="px-4 py-4">
                                    <span class="inline-flex items-center rounded-md px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $typeBadge['class'] }}">
                                        {{ $typeBadge['code'] }}
                                    </span>
                                </td>

                                {{-- Référence --}}
                                <td class="px-4 py-4 font-semibold text-ink">
                                    {{ $row['reference'] }}
                                    @if ($row['has_invoice'])
                                        <span class="ml-1.5 inline-flex whitespace-nowrap items-center rounded-full bg-teal-50 px-2 py-0.5 text-xs font-semibold text-teal-700">{{ $isProforma ? __('Facturée') : __('Facturé') }}</span>
                                    @endif
                                    @if ($isProforma && $row['dossier_reference'])
                                        <p class="mt-0.5 text-xs font-normal text-slate-500">{{ __('Dossier') }} : {{ $row['dossier_reference'] }}</p>
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
                                        @php
                                            $iconEye = '<svg class="size-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>';
                                            $iconUser = '<svg class="size-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>';
                                            $iconInvoice = '<svg class="size-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>';
                                            $iconPdf = '<svg class="size-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>';
                                            $iconEdit = '<svg class="size-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/></svg>';
                                            $iconConvert = '<svg class="size-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m6.75 12-3-3m0 0-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>';
                                            $iconCheck = '<svg class="size-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>';
                                            $iconX = '<svg class="size-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>';
                                            $iconTrash = '<svg class="size-4 shrink-0 text-rose-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>';
                                        @endphp

                                        {{-- Voir --}}
                                        <x-ui.dropdown-item :href="$showRoute" wire:navigate>
                                            <x-slot:icon>{!! $iconEye !!}</x-slot:icon>
                                            {{ $isProforma ? __('Voir la proforma') : __('Voir le devis') }}
                                        </x-ui.dropdown-item>

                                        {{-- Voir le client --}}
                                        @if ($row['client_id'])
                                            <x-ui.dropdown-item :href="route('pme.clients.show', $row['client_id'])" wire:navigate>
                                                <x-slot:icon>{!! $iconUser !!}</x-slot:icon>
                                                {{ __('Voir le client') }}
                                            </x-ui.dropdown-item>
                                        @endif

                                        {{-- Voir la facture liée --}}
                                        @if ($row['has_invoice'])
                                            <x-ui.dropdown-separator />
                                            <x-ui.dropdown-item wire:click="viewInvoice('{{ $row['invoice_id'] }}')">
                                                <x-slot:icon>{!! $iconInvoice !!}</x-slot:icon>
                                                {{ __('Voir la facture') }}
                                            </x-ui.dropdown-item>
                                            <x-ui.dropdown-item :href="route('pme.invoices.pdf', $row['invoice_public_code'])" target="_blank">
                                                <x-slot:icon>{!! $iconPdf !!}</x-slot:icon>
                                                {{ __('Afficher en PDF') }}
                                            </x-ui.dropdown-item>
                                        @endif

                                        {{-- PDF du document lui-même (toujours dispo pour les proformas) --}}
                                        @if ($isProforma)
                                            <x-ui.dropdown-item :href="route('pme.proformas.pdf', $row['public_code'])" target="_blank">
                                                <x-slot:icon>{!! $iconPdf !!}</x-slot:icon>
                                                {{ __('Afficher la proforma en PDF') }}
                                            </x-ui.dropdown-item>
                                        @endif

                                        {{-- Modifier --}}
                                        @if (in_array($row['status_value'], ['draft', 'sent']))
                                            <x-ui.dropdown-item :href="$isProforma ? route('pme.proformas.edit', $row['id']) : route('pme.quotes.edit', $row['id'])" wire:navigate>
                                                <x-slot:icon>{!! $iconEdit !!}</x-slot:icon>
                                                {{ $isProforma ? __('Modifier la proforma') : __('Modifier le devis') }}
                                            </x-ui.dropdown-item>
                                        @endif

                                        {{-- Convertir en facture (logique différente quote vs proforma) --}}
                                        @if (! $isProforma && in_array($row['status_value'], ['sent', 'accepted']) && ! $row['has_invoice'])
                                            <x-ui.dropdown-separator />
                                            <x-ui.dropdown-item wire:click="confirmConvert('{{ $row['id'] }}')">
                                                <x-slot:icon>{!! $iconConvert !!}</x-slot:icon>
                                                {{ __('Convertir en facture') }}
                                            </x-ui.dropdown-item>
                                        @endif

                                        @if ($isProforma && $row['status_value'] === 'po_received' && ! $row['has_invoice'])
                                            <x-ui.dropdown-separator />
                                            <x-ui.dropdown-item wire:click="confirmConvert('{{ $row['id'] }}')">
                                                <x-slot:icon>{!! $iconConvert !!}</x-slot:icon>
                                                {{ __('Convertir en facture') }}
                                            </x-ui.dropdown-item>
                                        @endif

                                        {{-- Transitions de statut sur Sent --}}
                                        @if ($row['status_value'] === 'sent')
                                            <x-ui.dropdown-separator />
                                            @if ($isProforma)
                                                <x-ui.dropdown-item wire:click="markAsPoReceived('{{ $row['id'] }}')">
                                                    <x-slot:icon>{!! $iconCheck !!}</x-slot:icon>
                                                    {{ __('Marquer BC reçu') }}
                                                </x-ui.dropdown-item>
                                            @else
                                                <x-ui.dropdown-item wire:click="markAsAccepted('{{ $row['id'] }}')">
                                                    <x-slot:icon>{!! $iconCheck !!}</x-slot:icon>
                                                    {{ __('Marquer comme accepté') }}
                                                </x-ui.dropdown-item>
                                            @endif
                                            <x-ui.dropdown-item wire:click="markAsDeclined('{{ $row['id'] }}')">
                                                <x-slot:icon>{!! $iconX !!}</x-slot:icon>
                                                {{ $isProforma ? __('Marquer comme refusée') : __('Marquer comme refusé') }}
                                            </x-ui.dropdown-item>
                                        @endif

                                        {{-- Suppression brouillon --}}
                                        @if ($row['status_value'] === 'draft')
                                            <x-ui.dropdown-separator />
                                            <x-ui.dropdown-item
                                                wire:click="confirmDelete('{{ $row['id'] }}')"
                                                :destructive="true"
                                            >
                                                <x-slot:icon>{!! $iconTrash !!}</x-slot:icon>
                                                {{ $isProforma ? __('Supprimer la proforma') : __('Supprimer le devis') }}
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
        :title="__('Supprimer le document')"
        :description="__('Cette action est irréversible. Le document sera définitivement supprimé.')"
        confirm-action="deleteDocument"
        cancel-action="cancelDelete"
        :confirm-label="__('Supprimer')"
    />

    <x-ui.confirm-modal
        :confirm-id="$confirmConvertId"
        :title="__('Convertir en facture')"
        :description="__('Ce document sera converti en facture brouillon. Vous pourrez la modifier avant de l\'envoyer.')"
        confirm-action="convertToInvoice"
        cancel-action="cancelConvert"
        :confirm-label="__('Convertir')"
        variant="primary"
    />


</div>
