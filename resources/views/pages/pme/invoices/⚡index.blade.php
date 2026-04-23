<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Models\Auth\Company;
use App\Enums\PME\ReminderChannel;
use App\Enums\PME\InvoiceStatus;
use App\Models\PME\Invoice;

new #[Title('Factures')] #[Layout('layouts::pme')] class extends Component {
    #[Url(as: 'statut')]
    public string $statusFilter = 'all';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'periode')]
    public string $period = '';

    public ?Company $company = null;

    public string $currentMonth = '';

    public int $invoiceCount = 0;

    public int $unpaidCount = 0;

    public int $invoicedAmount = 0;

    public int $actionRequiredCount = 0;

    public ?string $timelineInvoiceId = null;

    public ?string $confirmDeleteId = null;

    public ?string $confirmMarkPaidId = null;

    public ?string $previewInvoiceId = null;

    public string $previewTone = 'cordial';

    public bool $previewAttachPdf = true;

    public string $previewChannel = 'whatsapp';

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
    public function timelineInvoice(): ?Invoice
    {
        if (! $this->timelineInvoiceId || ! $this->company) {
            return null;
        }

        $invoice = Invoice::query()
            ->with(['client', 'reminders'])
            ->where('company_id', $this->company->id)
            ->whereKey($this->timelineInvoiceId)
            ->first();

        $invoice?->setRelation(
            'reminders',
            $invoice->reminders->sortBy('created_at')->values()
        );

        return $invoice;
    }

    #[Computed]
    public function previewInvoice(): ?Invoice
    {
        if (! $this->previewInvoiceId || ! $this->company) {
            return null;
        }

        return Invoice::query()
            ->with(['client', 'lines', 'reminders'])
            ->where('company_id', $this->company->id)
            ->whereKey($this->previewInvoiceId)
            ->first();
    }

    public function openPreview(string $invoiceId): void
    {
        abort_unless($this->company, 403);

        $invoice = Invoice::query()
            ->with('client')
            ->where('company_id', $this->company->id)
            ->findOrFail($invoiceId);

        $this->previewInvoiceId = $invoiceId;
        $this->previewTone = 'cordial';
        $this->previewAttachPdf = true;
        $this->previewChannel = filled($invoice->client?->phone)
            ? \App\Enums\PME\ReminderChannel::WhatsApp->value
            : \App\Enums\PME\ReminderChannel::Email->value;
        $this->timelineInvoiceId = null;
        unset($this->previewInvoice);
    }

    public function closePreview(): void
    {
        $this->previewInvoiceId = null;
        unset($this->previewInvoice);
    }

    public function openTimeline(string $invoiceId): void
    {
        abort_unless($this->company, 403);

        Invoice::query()
            ->where('company_id', $this->company->id)
            ->findOrFail($invoiceId);

        $this->timelineInvoiceId = $invoiceId;
    }

    public function closeTimeline(): void
    {
        $this->timelineInvoiceId = null;
    }

    public function confirmDelete(string $id): void
    {
        $this->confirmDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmDeleteId = null;
    }

    public function deleteInvoice(string $invoiceId): void
    {
        $this->confirmDeleteId = null;
        abort_unless($this->company, 403);

        $invoice = Invoice::query()
            ->where('company_id', $this->company->id)
            ->findOrFail($invoiceId);

        $invoice->delete();

        $this->flushDocumentCaches();
        $this->refreshKpis();
        unset($this->rows, $this->statusCounts);

        $this->dispatch('toast', type: 'success', title: __('La facture a été supprimée.'));
    }

    private function refreshKpis(): void
    {
        if (! $this->company) {
            $this->invoiceCount = 0;
            $this->unpaidCount = 0;
            $this->invoicedAmount = 0;
            $this->actionRequiredCount = 0;

            return;
        }

        // KPI: factures émises ce mois (hors brouillon/annulée)
        $this->invoiceCount = Invoice::query()
            ->where('company_id', $this->company->id)
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Cancelled])
            ->whereMonth('issued_at', now()->month)
            ->whereYear('issued_at', now()->year)
            ->count();

        // KPI: factures impayées
        $this->unpaidCount = Invoice::query()
            ->where('company_id', $this->company->id)
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue, InvoiceStatus::PartiallyPaid])
            ->count();

        // KPI: montant HT facturé ce mois
        $this->invoicedAmount = Invoice::query()
            ->where('company_id', $this->company->id)
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Cancelled])
            ->whereMonth('issued_at', now()->month)
            ->whereYear('issued_at', now()->year)
            ->sum('subtotal');

        // KPI: factures nécessitant une action (overdue + sent)
        $this->actionRequiredCount = Invoice::query()
            ->where('company_id', $this->company->id)
            ->whereIn('status', [InvoiceStatus::Overdue, InvoiceStatus::Sent])
            ->count();
    }

    /** @return array<int, array<string, mixed>> */
    #[Computed]
    public function rows(): array
    {
        return $this->applyStatusFilter($this->rowsBeforeAggregateFilters());
    }

    /** @return array<string, int> */
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

    /** @return array<string, string> */
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

    public function confirmMarkPaid(string $id): void
    {
        $this->confirmMarkPaidId = $id;
    }

    public function cancelMarkPaid(): void
    {
        $this->confirmMarkPaidId = null;
    }

    public function markAsPaid(string $invoiceId): void
    {
        $this->confirmMarkPaidId = null;
        $invoice = Invoice::query()
            ->where('company_id', $this->company?->id)
            ->findOrFail($invoiceId);

        $invoice->update([
            'status' => InvoiceStatus::Paid,
            'amount_paid' => $invoice->total,
            'paid_at' => now(),
        ]);

        $this->flushDocumentCaches();
        $this->refreshKpis();
        unset($this->rows, $this->statusCounts);
    }

    public function sendReminder(string $invoiceId): void
    {
        abort_unless($this->company, 403);

        $invoice = Invoice::query()
            ->where('company_id', $this->company->id)
            ->findOrFail($invoiceId);

        if (! $invoice->canReceiveReminder()) {
            $this->dispatch('toast', type: 'warning', title: __('Cette facture ne peut plus être relancée.'));

            return;
        }

        if (now()->isWeekend()) {
            $this->dispatch('toast', type: 'warning', title: __('Les relances ne peuvent être envoyées qu\'en jour ouvré (lundi au vendredi).'));

            return;
        }

        try {
            $channel = ReminderChannel::from($this->previewChannel);
            $msg = $this->buildPreviewMessage();
            $messageBody = implode("\n\n", array_filter([
                $msg['greeting'],
                $msg['body'],
                $msg['closing'],
                $this->company->name,
            ])) ?: null;

            app(\App\Services\PME\ReminderService::class)
                ->send($invoice, $this->company, $channel, $messageBody, mode: \App\Enums\PME\ReminderMode::Manual);

            $this->dispatch('toast', type: 'success', title: __('Relance envoyée avec succès.'));
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'warning', title: __('Service d\'envoi bientôt disponible. Votre relance sera envoyée prochainement.'));
        }

        $this->previewInvoiceId = null;
        unset($this->previewInvoice);
        $this->flushDocumentCaches();
        $this->refreshKpis();
        unset($this->rows, $this->statusCounts);
    }

    /**
     * @return array{greeting: string, body: string, closing: string}
     */
    public function buildPreviewMessage(): array
    {
        $inv = $this->previewInvoice;
        if (! $inv) {
            return ['greeting' => '', 'body' => '', 'closing' => ''];
        }

        $clientName = $inv->client?->name ?? '—';
        $reference = $inv->reference ?? '—';
        $remaining = format_money($inv->total - $inv->amount_paid);
        $dueDate = format_date($inv->due_at);

        $toneGreetings = [
            'cordial' => "Bonjour {$clientName},",
            'ferme' => "Bonjour {$clientName},",
            'urgent' => "{$clientName},",
        ];

        $toneBody = [
            'cordial' => "Nous souhaitons vous rappeler que la facture {$reference} d'un montant de {$remaining}, échue le {$dueDate}, reste en attente de règlement.\n\nNous vous serions reconnaissants de bien vouloir procéder au paiement dans les meilleurs délais.",
            'ferme' => "La facture {$reference} d'un montant de {$remaining} est en retard de paiement depuis le {$dueDate}.\n\nNous vous demandons de procéder au règlement dans les plus brefs délais.",
            'urgent' => "URGENT : La facture {$reference} ({$remaining}) est impayée depuis le {$dueDate}. Malgré nos précédentes relances, aucun règlement n'a été effectué.\n\nNous vous prions de régulariser cette situation immédiatement.",
        ];

        $toneClosing = [
            'cordial' => 'Cordialement,',
            'ferme' => 'Dans l\'attente de votre règlement,',
            'urgent' => 'En espérant une action immédiate de votre part,',
        ];

        $tone = $this->previewTone;

        return [
            'greeting' => $toneGreetings[$tone] ?? $toneGreetings['cordial'],
            'body' => $toneBody[$tone] ?? $toneBody['cordial'],
            'closing' => $toneClosing[$tone] ?? $toneClosing['cordial'],
        ];
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

    /** @return array<int, array<string, mixed>> */
    private function rowsBeforeAggregateFilters(): array
    {
        if ($this->rowsBeforeAggregateFiltersCache !== null) {
            return $this->rowsBeforeAggregateFiltersCache;
        }

        return $this->rowsBeforeAggregateFiltersCache = $this->applySearchFilter($this->applyPeriodFilter($this->allRows()));
    }

    /** @return array<int, array<string, mixed>> */
    private function allRows(): array
    {
        if ($this->allRowsCache !== null) {
            return $this->allRowsCache;
        }

        if (! $this->company) {
            return $this->allRowsCache = [];
        }

        return $this->allRowsCache = Invoice::query()
            ->where('company_id', $this->company->id)
            ->whereNotIn('status', [InvoiceStatus::Cancelled])
            ->with(['client', 'reminders'])
            ->orderByDesc('issued_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($inv) {
                $delayDays = $inv->due_at ? (int) abs(now()->diffInDays($inv->due_at)) : 0;
                $isOverdue = $inv->status === InvoiceStatus::Overdue;

                return [
                    'id'              => $inv->id,
                    'public_code'     => $inv->public_code,
                    'reference'       => $inv->reference ?? '—',
                    'client_id'       => $inv->client_id,
                    'client_name'     => $inv->client?->name ?? '—',
                    'subtotal'        => $inv->subtotal,
                    'tax_amount'      => $inv->tax_amount,
                    'total'           => $inv->total,
                    'currency'        => $inv->currency,
                    'issued_at'       => $inv->issued_at,
                    'due_at'          => $inv->due_at,
                    'status_value'    => $inv->status->value,
                    'is_overdue'      => $isOverdue,
                    'delay_days'      => $isOverdue ? $delayDays : 0,
                    'amount_paid'     => $inv->amount_paid,
                    'reminders_count' => $inv->reminders->count(),
                ];
            })
            ->toArray();
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
    private function applyStatusFilter(array $rows, ?string $status = null): array
    {
        $status ??= $this->statusFilter;

        if ($status === 'all') {
            return $rows;
        }

        return array_values(array_filter($rows, fn ($row) => $row['status_value'] === $status));
    }

    private function flushDocumentCaches(): void
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


    {{-- Bloc A — En-tête --}}
    <section class="app-shell-panel overflow-hidden">
        <div class="flex flex-col gap-4 p-6 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.24em] text-teal">
                    {{ __('Facturation') }} · {{ $currentMonth }}
                    @if (count($this->rows) > 0)
                        · {{ count($this->rows) }} {{ count($this->rows) > 1 ? __('factures') : __('facture') }}
                    @endif
                </p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-ink">{{ __('Factures') }}</h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ __('Gérez vos factures clients.') }}
                </p>
            </div>

            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <a
                    href="{{ route('pme.invoices.create') }}"
                    wire:navigate
                    class="inline-flex items-center gap-2 rounded-2xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-strong"
                >
                    <flux:icon name="plus" class="size-4" />
                    {{ __('Nouvelle facture') }}
                </a>
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
                <span class="inline-flex whitespace-nowrap items-center rounded-full bg-slate-100 px-2.5 py-1 text-sm font-medium text-slate-500">
                    {{ __('Ce mois') }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Factures émises') }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ $currentMonth }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-ink">{{ $invoiceCount }}</p>
        </article>

        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-amber-50">
                    <flux:icon name="clock" class="size-5 text-amber-500" />
                </div>
                <span class="inline-flex whitespace-nowrap items-center rounded-full bg-amber-50 px-2.5 py-1 text-sm font-semibold text-amber-700">
                    {{ __('Impayées') }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Factures impayées') }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ __('En attente de paiement') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-amber-500">{{ $unpaidCount }}</p>
        </article>

        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-primary/8">
                    <flux:icon name="banknotes" class="size-5 text-primary" />
                </div>
                <span class="inline-flex whitespace-nowrap items-center rounded-full bg-slate-100 px-2.5 py-1 text-sm font-medium text-slate-500">
                    HT
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Montant facturé') }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ __('HT ce mois') }}</p>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-primary">
                @if ($invoicedAmount > 0)
                    {{ format_money($invoicedAmount) }}
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
                <span class="inline-flex whitespace-nowrap items-center rounded-full bg-rose-50 px-2.5 py-1 text-sm font-semibold text-rose-700">
                    {{ __('Action requise') }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('En retard ou en attente') }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ __('Factures à traiter') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-rose-500">{{ $actionRequiredCount }}</p>
        </article>

    </section>

    {{-- Bloc C+D — Filtres + Table --}}
    <x-ui.table-panel
        :title="__('Factures')"
        :description="__('Liste des factures émises.')"
        :filterLabel="__('Filtrer par statut')"
    >
        <x-slot:filters>
            @foreach ([
                'all'            => ['label' => 'Tous',        'dot' => null,           'activeClass' => 'bg-primary text-white',     'badgeInactive' => 'bg-slate-100 text-slate-500'],
                'draft'          => ['label' => 'Brouillon',   'dot' => 'bg-slate-400', 'activeClass' => 'bg-slate-500 text-white',   'badgeInactive' => 'bg-slate-100 text-slate-600'],
                'sent'           => ['label' => 'Envoyée',     'dot' => 'bg-blue-500',  'activeClass' => 'bg-blue-500 text-white',    'badgeInactive' => 'bg-blue-100 text-blue-700'],
                'paid'           => ['label' => 'Payée',       'dot' => 'bg-accent',    'activeClass' => 'bg-emerald-600 text-white', 'badgeInactive' => 'bg-emerald-100 text-emerald-700'],
                'overdue'        => ['label' => 'En retard',   'dot' => 'bg-rose-500',  'activeClass' => 'bg-rose-500 text-white',    'badgeInactive' => 'bg-rose-100 text-rose-700'],
                'partially_paid' => ['label' => 'Part. payée', 'dot' => 'bg-amber-400', 'activeClass' => 'bg-amber-500 text-white',   'badgeInactive' => 'bg-amber-100 text-amber-700'],
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
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Échéance') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Statut') }}</th>
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
                                    default         => ['label' => ucfirst($row['status_value']), 'class' => 'bg-slate-100 text-slate-600 ring-slate-600/20'],
                                };
                            @endphp
                            <tr
                                wire:key="inv-{{ $row['id'] }}"
                                class="cursor-pointer transition hover:bg-slate-50/60"
                                x-on:click="window.Livewire.navigate('{{ route('pme.invoices.show', $row['id']) }}')"
                            >

                                {{-- Référence --}}
                                <td class="px-6 py-4 font-semibold text-ink">{{ $row['reference'] }}</td>

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
                                <td class="px-4 py-4 text-slate-500 whitespace-nowrap">
                                    {{ format_date($row['issued_at']) }}
                                </td>

                                {{-- Échéance --}}
                                <td class="px-4 py-4 whitespace-nowrap">
                                    @if ($row['due_at'])
                                        @if ($row['is_overdue'])
                                            <span class="font-bold text-rose-500">
                                                {{ format_date($row['due_at'], withYear: false) }} ! J+{{ $row['delay_days'] }}
                                            </span>
                                        @else
                                            <span class="text-slate-500">{{ format_date($row['due_at']) }}</span>
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
                                        <x-ui.dropdown-item :href="route('pme.invoices.show', $row['id'])" wire:navigate>
                                            <x-slot:icon>
                                                <svg class="size-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                </svg>
                                            </x-slot:icon>
                                            {{ __('Voir la facture') }}
                                        </x-ui.dropdown-item>
                                        @if ($row['client_id'])
                                            <x-ui.dropdown-item :href="route('pme.clients.show', $row['client_id'])" wire:navigate>
                                                <x-slot:icon>
                                                    <svg class="size-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                                    </svg>
                                                </x-slot:icon>
                                                {{ __('Voir le client') }}
                                            </x-ui.dropdown-item>
                                        @endif
                                        <x-ui.dropdown-item :href="route('pme.invoices.pdf', $row['public_code'])" target="_blank">
                                            <x-slot:icon>
                                                <svg class="size-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                                </svg>
                                            </x-slot:icon>
                                            {{ __('Afficher en PDF') }}
                                        </x-ui.dropdown-item>
                                        <x-ui.dropdown-item wire:click="openTimeline('{{ $row['id'] }}')" :count="$row['reminders_count']">
                                            <x-slot:icon>
                                                <svg class="size-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                </svg>
                                            </x-slot:icon>
                                            {{ __('Voir les relances') }}
                                        </x-ui.dropdown-item>
                                        @if (in_array($row['status_value'], ['draft', 'sent']))
                                            <x-ui.dropdown-item :href="route('pme.invoices.edit', $row['id'])" wire:navigate>
                                                <x-slot:icon>
                                                    <svg class="size-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                                    </svg>
                                                </x-slot:icon>
                                                {{ __('Éditer la facture') }}
                                            </x-ui.dropdown-item>
                                        @endif
                                        @if (in_array($row['status_value'], ['sent', 'overdue', 'partially_paid']))
                                            <x-ui.dropdown-separator />
                                            <x-ui.dropdown-item wire:click="openPreview('{{ $row['id'] }}')">
                                                <x-slot:icon>
                                                    <svg class="size-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0Z" />
                                                    </svg>
                                                </x-slot:icon>
                                                {{ __('Relancer le client') }}
                                            </x-ui.dropdown-item>
                                        @endif
                                        @if (! in_array($row['status_value'], ['paid', 'cancelled', 'draft']))
                                            <x-ui.dropdown-separator />
                                            <x-ui.dropdown-item
                                                wire:click="confirmMarkPaid('{{ $row['id'] }}')"
                                            >
                                                <x-slot:icon>
                                                    <svg class="size-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                    </svg>
                                                </x-slot:icon>
                                                {{ __('Marquer comme payée') }}
                                            </x-ui.dropdown-item>
                                        @endif
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
                                            {{ __('Supprimer la facture') }}
                                        </x-ui.dropdown-item>
                                    </x-ui.dropdown>
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
                <h3 class="mt-4 text-lg font-semibold text-ink">{{ __('Aucune facture pour le moment') }}</h3>
                <p class="mt-2 max-w-sm text-sm text-slate-500">
                    {{ __('Commencez par créer votre première facture.') }}
                </p>
                <a href="{{ route('pme.invoices.create') }}" wire:navigate
                    class="mt-6 inline-flex items-center gap-2 rounded-2xl bg-primary px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-strong">
                    <flux:icon name="plus" class="size-4" />
                    {{ __('Créer une facture') }}
                </a>
            </div>

        @else
            {{-- État vide — filtre sans résultat --}}
            <div class="flex flex-col items-center justify-center p-12 text-center">
                <div class="flex size-12 items-center justify-center rounded-2xl bg-slate-100">
                    <flux:icon name="magnifying-glass" class="size-5 text-slate-500" />
                </div>
                <p class="mt-4 font-semibold text-ink">{{ __('Aucune facture ne correspond') }}</p>
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

    {{-- Slide-over historique des relances --}}
    @if ($timelineInvoiceId && $this->timelineInvoice)
        <x-ui.drawer
            :title="__('Historique des relances')"
            :subtitle="$this->timelineInvoice->reference . ' · ' . ($this->timelineInvoice->client?->name ?? '')"
            close-action="closeTimeline"
        >
            <x-collection.reminder-feed :invoice="$this->timelineInvoice" />
        </x-ui.drawer>
    @endif

    @if ($previewInvoiceId && $this->previewInvoice)
        <x-collection.reminder-preview-slideover
            :invoice="$this->previewInvoice"
            :message="$this->buildPreviewMessage()"
            :company="$company"
            :previewInvoiceId="$previewInvoiceId"
            :previewAttachPdf="$previewAttachPdf"
            :previewChannel="$previewChannel"
        />
    @endif

    <x-ui.confirm-modal
        :confirm-id="$confirmMarkPaidId"
        :title="__('Marquer comme payée')"
        :description="__('Cette facture sera marquée comme entièrement payée. Cette action est irréversible.')"
        confirm-action="markAsPaid"
        cancel-action="cancelMarkPaid"
        :confirm-label="__('Confirmer le paiement')"
        variant="primary"
    />

    <x-ui.confirm-modal
        :confirm-id="$confirmDeleteId"
        :title="__('Supprimer la facture')"
        :description="__('Cette action est irréversible. La facture sera définitivement supprimée.')"
        confirm-action="deleteInvoice"
        cancel-action="cancelDelete"
        :confirm-label="__('Supprimer')"
    />

</div>
