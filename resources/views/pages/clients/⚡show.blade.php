<?php

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Auth\Models\AccountantCompany;
use Modules\Auth\Models\Company;
use Modules\Compta\Export\Enums\ExportFormat;
use Modules\Compta\Partnership\Enums\PartnerTier;
use Modules\PME\Invoicing\Enums\InvoiceStatus;
use Modules\PME\Invoicing\Models\Invoice;

new #[Title('Clients')] class extends Component {
    public Company $company;

    public ?Company $firm = null;

    public ?AccountantCompany $relation = null;

    public string $initials = '';

    public string $companyRef = '';

    public string $clientSince = '';

    public string $tierLabel = '';

    public string $tierValue = '';

    #[Url] public string $selectedPeriod = '';

    #[Url] public int $perPage = 20;

    // Invoice detail modal
    public ?string $selectedInvoiceId = null;

    public function mount(Company $company): void
    {
        $this->firm = auth()->user()->accountantFirm();

        $this->relation = $this->firm
            ? AccountantCompany::query()
                ->where('accountant_firm_id', $this->firm->id)
                ->where('sme_company_id', $company->id)
                ->whereNull('ended_at')
                ->first()
            : null;

        if (! $this->relation) {
            abort(403);
        }

        $this->company = $company;

        if (empty($this->selectedPeriod)) {
            $this->selectedPeriod = now()->format('Y-m');
        }

        // Tier du cabinet
        $activeCount = AccountantCompany::query()
            ->where('accountant_firm_id', $this->firm->id)
            ->whereNull('ended_at')
            ->count();

        $tier = PartnerTier::fromActiveClients($activeCount);
        $this->tierValue = $tier->value;
        $this->tierLabel = match ($tier) {
            PartnerTier::Partner  => 'Partner',
            PartnerTier::Gold     => 'Gold',
            PartnerTier::Platinum => 'Platinum',
        };

        // Infos d'affichage
        $nameParts = collect(explode(' ', $company->name));
        $this->initials = $nameParts->map(fn ($w) => strtoupper($w[0] ?? ''))->take(2)->join('');
        $this->companyRef = $this->initials.'-'.strtoupper(substr($company->id, -4));
        $this->clientSince = format_month($this->relation->started_at);
    }

    private function selectedYear(): int
    {
        return (int) explode('-', $this->selectedPeriod)[0];
    }

    private function selectedMonth(): int
    {
        return (int) explode('-', $this->selectedPeriod)[1];
    }

    /**
     * Toutes les factures de la PME (stats all-time, sans eager load client).
     *
     * @return Collection<int, Invoice>
     */
    #[Computed]
    public function allInvoices(): Collection
    {
        return Invoice::query()
            ->where('company_id', $this->company->id)
            ->get(['id', 'status', 'total', 'amount_paid', 'issued_at', 'due_at', 'paid_at']);
    }

    /**
     * Factures du mois sélectionné, avec client, lignes et compteur de relances.
     *
     * @return Collection<int, Invoice>
     */
    #[Computed]
    public function invoices(): Collection
    {
        return Invoice::query()
            ->where('company_id', $this->company->id)
            ->whereYear('issued_at', $this->selectedYear())
            ->whereMonth('issued_at', $this->selectedMonth())
            ->with(['client', 'lines'])
            ->withCount('reminders')
            ->orderByDesc('issued_at')
            ->get();
    }

    /**
     * Mois distincts ayant des factures, pour le sélecteur.
     *
     * @return array<int, array{value: string, label: string}>
     */
    #[Computed]
    public function availableMonths(): array
    {
        $months = Invoice::query()
            ->where('company_id', $this->company->id)
            ->orderByDesc('issued_at')
            ->select('issued_at')
            ->get()
            ->map(fn ($inv) => [
                'value' => $inv->issued_at->format('Y-m'),
                'label' => format_month($inv->issued_at),
            ])
            ->unique('value')
            ->values()
            ->toArray();

        $current = now()->format('Y-m');
        if (collect($months)->where('value', $current)->isEmpty()) {
            array_unshift($months, [
                'value' => $current,
                'label' => format_month(now()),
            ]);
        }

        return $months;
    }

    /**
     * Indicateurs financiers (stat cards).
     *
     * @return array{billed_month: int, collected: int, pending_amount: int, pending_count: int, avg_days: int, recovery_rate: int}
     */
    #[Computed]
    public function stats(): array
    {
        $all   = collect($this->allInvoices);
        $year  = $this->selectedYear();
        $month = $this->selectedMonth();

        $monthInvoices = $all->filter(
            fn ($inv) => $inv->issued_at->year === $year && $inv->issued_at->month === $month
        );

        $unpaidStatuses = [
            InvoiceStatus::Sent,
            InvoiceStatus::Certified,
            InvoiceStatus::Overdue,
            InvoiceStatus::PartiallyPaid,
        ];

        $pending = $all->filter(fn ($inv) => in_array($inv->status, $unpaidStatuses));
        $paid    = $all->filter(fn ($inv) => $inv->status === InvoiceStatus::Paid && $inv->paid_at);

        $avgDays = $paid->isNotEmpty()
            ? (int) round($paid->avg(fn ($inv) => $inv->issued_at->diffInDays($inv->paid_at)))
            : 0;

        $totalBilled = (int) $all->sum('total');
        $totalPaid   = (int) $all->sum('amount_paid');
        $recoveryRate = $totalBilled > 0 ? (int) round($totalPaid / $totalBilled * 100) : 0;

        return [
            'billed_month'   => (int) $monthInvoices->sum('total'),
            'collected'      => $totalPaid,
            'pending_amount' => (int) $pending->sum(fn ($inv) => $inv->total - $inv->amount_paid),
            'pending_count'  => $pending->count(),
            'avg_days'       => $avgDays,
            'recovery_rate'  => $recoveryRate,
        ];
    }

    /** Statut global de la PME basé sur ses factures impayées. */
    #[Computed]
    public function statusValue(): string
    {
        $all = collect($this->allInvoices);

        $hasCritical = $all->some(
            fn ($inv) => $inv->status === InvoiceStatus::Overdue && $inv->due_at->diffInDays(now()) > 60
        );

        if ($hasCritical) {
            return 'critique';
        }

        $hasPending = $all->some(fn ($inv) => in_array($inv->status, [
            InvoiceStatus::Overdue,
            InvoiceStatus::Sent,
            InvoiceStatus::PartiallyPaid,
        ]));

        return $hasPending ? 'a_surveiller' : 'a_jour';
    }

    #[Computed]
    public function statusLabel(): string
    {
        return match ($this->statusValue) {
            'critique' => 'Critique',
            'a_surveiller' => 'À surveiller',
            default => 'À jour',
        };
    }

    #[Computed]
    public function heroSummary(): string
    {
        $pendingLabel = $this->stats['pending_count'] === 1 ? 'facture' : 'factures';

        return sprintf(
            '%s %s en attente · %s à recouvrer · Taux de recouvrement de %s%%',
            number_format($this->stats['pending_count'], 0, ',', ' '),
            $pendingLabel,
            format_money($this->stats['pending_amount']),
            $this->stats['recovery_rate']
        );
    }

    #[Computed]
    public function selectedPeriodLabel(): string
    {
        $selectedPeriod = collect($this->availableMonths)->firstWhere('value', $this->selectedPeriod);

        return $selectedPeriod['label']
            ?? format_month(now()->setYear($this->selectedYear())->setMonth($this->selectedMonth()));
    }

    /** Facture sélectionnée pour la modale de détail. */
    #[Computed]
    public function selectedInvoice(): ?Invoice
    {
        if (! $this->selectedInvoiceId) {
            return null;
        }

        return Invoice::query()
            ->where('id', $this->selectedInvoiceId)
            ->with(['client', 'lines'])
            ->first();
    }

    public function viewInvoice(string $id): void
    {
        $this->selectedInvoiceId = $id;
    }

    public function closeInvoice(): void
    {
        $this->selectedInvoiceId = null;
    }

    // ─── Export comptable ────────────────────────────────────────────────

    public string $exportPeriod = '';

    public string $exportFormat = 'sage100';

    /** @return array<int, array{value: string, label: string, type: string}> */
    #[Computed]
    public function exportPeriods(): array
    {
        $year = now()->year;
        $periods = [];

        // Mois individuels du plus récent au plus ancien
        for ($m = (int) now()->month; $m >= 1; $m--) {
            $date = now()->setMonth($m)->startOfMonth();
            $periods[] = [
                'value' => $date->format('Y-m'),
                'label' => format_month($date),
                'type' => 'Mois',
            ];
        }

        // Trimestres
        $quarterLabels = ['T1' => [1, 3], 'T2' => [4, 6], 'T3' => [7, 9], 'T4' => [10, 12]];
        foreach ($quarterLabels as $label => [$from, $to]) {
            if ($from <= now()->month) {
                $periods[] = [
                    'value' => $year.'-'.$label,
                    'label' => $label.' '.$year,
                    'type' => 'Trimestre',
                ];
            }
        }

        // Semestres
        $periods[] = ['value' => $year.'-S1', 'label' => 'S1 '.$year, 'type' => 'Semestre'];
        if (now()->month > 6) {
            $periods[] = ['value' => $year.'-S2', 'label' => 'S2 '.$year, 'type' => 'Semestre'];
        }

        // Année complète
        $periods[] = ['value' => (string) $year, 'label' => 'Année '.$year, 'type' => 'Année'];

        return $periods;
    }

    /** @return int */
    #[Computed]
    public function exportInvoiceCount(): int
    {
        if (empty($this->exportPeriod)) {
            return 0;
        }

        return $this->exportFilteredInvoices()->count();
    }

    public function exportPeriodLabel(): string
    {
        if (empty($this->exportPeriod)) {
            return '';
        }

        $period = collect($this->exportPeriods)->firstWhere('value', $this->exportPeriod);

        return $period['label'] ?? $this->exportPeriod;
    }

    private function exportFilteredInvoices(): Collection
    {
        $query = Invoice::query()->where('company_id', $this->company->id);

        $period = $this->exportPeriod;
        $year = (int) substr($period, 0, 4);

        return match (true) {
            str_contains($period, '-T1') => $query->whereYear('issued_at', $year)->whereMonth('issued_at', '>=', 1)->whereMonth('issued_at', '<=', 3)->get(),
            str_contains($period, '-T2') => $query->whereYear('issued_at', $year)->whereMonth('issued_at', '>=', 4)->whereMonth('issued_at', '<=', 6)->get(),
            str_contains($period, '-T3') => $query->whereYear('issued_at', $year)->whereMonth('issued_at', '>=', 7)->whereMonth('issued_at', '<=', 9)->get(),
            str_contains($period, '-T4') => $query->whereYear('issued_at', $year)->whereMonth('issued_at', '>=', 10)->whereMonth('issued_at', '<=', 12)->get(),
            str_contains($period, '-S1') => $query->whereYear('issued_at', $year)->whereMonth('issued_at', '>=', 1)->whereMonth('issued_at', '<=', 6)->get(),
            str_contains($period, '-S2') => $query->whereYear('issued_at', $year)->whereMonth('issued_at', '>=', 7)->whereMonth('issued_at', '<=', 12)->get(),
            strlen($period) === 4        => $query->whereYear('issued_at', $year)->get(),
            default                      => $query->whereYear('issued_at', $year)->whereMonth('issued_at', (int) substr($period, 5, 2))->get(),
        };
    }

    public function mountExportModal(): void
    {
        $this->exportPeriod = now()->format('Y-m');
        $this->exportFormat = 'sage100';
        unset($this->exportInvoiceCount);
    }

    public function updatedExportPeriod(): void
    {
        unset($this->exportInvoiceCount);
    }

    public function archive(): void
    {
        $this->relation->update(['ended_at' => now()]);
        $this->redirect(route('clients.index'), navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- ─── En-tête ──────────────────────────────────────────────────────── --}}
    <section class="app-shell-panel p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">

            {{-- Identité --}}
            <div class="flex items-start gap-4">
                <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-mist text-lg font-bold text-primary">
                    {{ $initials }}
                </span>
                <div>
                    <h2 class="text-2xl font-semibold tracking-tight text-ink">{{ $company->name }}</h2>
                    <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                        <span @class([
                            'inline-flex items-center rounded-full px-2 py-0.5 text-sm font-semibold',
                            'bg-teal-100 text-teal-700'    => strtolower($company->plan ?? '') === 'essentiel',
                            'bg-violet-100 text-violet-700' => strtolower($company->plan ?? '') === 'basique',
                            'bg-amber-100 text-amber-700'   => strtolower($company->plan ?? '') === 'premium',
                            'bg-slate-100 text-slate-600'   => ! in_array(strtolower($company->plan ?? ''), ['essentiel', 'basique', 'premium']),
                        ])>
                            {{ ucfirst($company->plan ?? '—') }}
                        </span>
                        <span>· {{ __('Client depuis') }} {{ $clientSince }}</span>
                        <span>· {{ __('Réf.') }} {{ $companyRef }}</span>
                    </p>
                    <p class="mt-2 text-sm text-slate-500">{{ $this->heroSummary }}</p>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <flux:modal.trigger name="export-comptable">
                    <button
                        type="button"
                        wire:click="mountExportModal"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-primary/30 hover:text-primary"
                    >
                        <x-app.icon name="export" class="size-4" />
                        {{ __('Exporter') }}
                    </button>
                </flux:modal.trigger>

                <flux:dropdown position="bottom" align="end">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-primary/30 hover:text-primary"
                    >
                        {{ __('Actions') }}
                        <x-app.icon name="chevron-down" class="size-3.5" />
                    </button>

                    <flux:menu>
                        <flux:menu.item href="#factures-client">
                            <x-app.icon name="invoice" class="size-4 text-slate-500" />
                            {{ __('Voir l’historique') }}
                        </flux:menu.item>

                        <flux:menu.separator />

                        <flux:menu.item
                            wire:click="archive"
                            wire:confirm="{{ __('Archiver ce client ? Cette action retirera la PME de votre portefeuille actif.') }}"
                        >
                            <flux:icon name="archive-box-x-mark" class="size-4 text-rose-500" />
                            {{ __('Archiver') }}
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>

                <span @class([
                    'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm font-semibold',
                    'border-rose-200 bg-rose-50 text-rose-700'      => $this->statusValue === 'critique',
                    'border-amber-200 bg-amber-50 text-amber-700'   => $this->statusValue === 'a_surveiller',
                    'border-green-200 bg-green-50 text-green-700' => $this->statusValue === 'a_jour',
                ])>
                    <span @class([
                        'size-2 rounded-full',
                        'bg-rose-500'  => $this->statusValue === 'critique',
                        'bg-amber-400' => $this->statusValue === 'a_surveiller',
                        'bg-green-500' => $this->statusValue === 'a_jour',
                    ])></span>
                    {{ $this->statusLabel }}
                </span>
            </div>
        </div>
    </section>

    {{-- ─── Stat cards ───────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">

        {{-- CA facturé --}}
        <section class="app-shell-panel p-5">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-slate-100">
                    <flux:icon name="document-chart-bar" class="size-5 text-slate-600" />
                </div>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-sm font-semibold text-slate-600">
                    {{ format_month(now()->setYear($this->selectedYear())->setMonth($this->selectedMonth())) }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('CA facturé') }}</p>
            <p class="mt-1 text-2xl font-bold tracking-tight text-ink">
                {{ format_money($this->stats['billed_month']) }}
            </p>
        </section>

        {{-- Encaissé --}}
        <section class="app-shell-panel p-5">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-emerald-50">
                    <flux:icon name="banknotes" class="size-5 text-accent" />
                </div>
                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-sm font-semibold text-emerald-700">
                    {{ __('Cumul') }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Encaissé') }}</p>
            <p class="mt-1 text-2xl font-bold tracking-tight text-accent">
                {{ format_money($this->stats['collected']) }}
            </p>
        </section>

        {{-- En attente --}}
        <section class="app-shell-panel p-5">
            <div class="flex items-start justify-between">
                <div @class([
                    'flex size-10 items-center justify-center rounded-xl',
                    'bg-rose-50'   => $this->statusValue === 'critique',
                    'bg-amber-50'  => $this->statusValue !== 'critique' && $this->stats['pending_amount'] > 0,
                    'bg-slate-100' => $this->stats['pending_amount'] === 0,
                ])>
                    <flux:icon name="exclamation-circle" @class([
                        'size-5',
                        'text-rose-500'   => $this->statusValue === 'critique',
                        'text-amber-500'  => $this->statusValue !== 'critique' && $this->stats['pending_amount'] > 0,
                        'text-slate-500'  => $this->stats['pending_amount'] === 0,
                    ]) />
                </div>
                @if ($this->stats['pending_count'] > 0)
                    <span @class([
                        'inline-flex items-center rounded-full px-2.5 py-1 text-sm font-semibold',
                        'bg-rose-50 text-rose-700'   => $this->statusValue === 'critique',
                        'bg-amber-50 text-amber-700' => $this->statusValue !== 'critique',
                    ])>
                        {{ $this->stats['pending_count'] }} {{ $this->stats['pending_count'] > 1 ? __('factures') : __('facture') }}
                    </span>
                @else
                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-sm font-semibold text-emerald-700">
                        À jour
                    </span>
                @endif
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Montant en attente') }}</p>
            <p @class([
                'mt-1 text-2xl font-bold tracking-tight',
                'text-rose-500'  => $this->statusValue === 'critique',
                'text-amber-500' => $this->statusValue !== 'critique' && $this->stats['pending_amount'] > 0,
                'text-ink'       => $this->stats['pending_amount'] === 0,
            ])>
                {{ format_money($this->stats['pending_amount']) }}
            </p>
        </section>

        {{-- Délai moyen · Taux --}}
        <section class="app-shell-panel p-5">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-amber-50">
                    <flux:icon name="clock" class="size-5 text-amber-500" />
                </div>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Délai moyen de paiement · Taux de recouvrement') }}</p>
            <p class="mt-1 text-2xl font-bold tracking-tight text-amber-500">
                {{ $this->stats['avg_days'] }} j
                <span class="text-slate-500">·</span>
                {{ $this->stats['recovery_rate'] }}%
            </p>
        </section>

    </div>

    {{-- ─── Factures du mois ─────────────────────────────────────────────── --}}
    <section id="factures-client" class="app-shell-panel overflow-hidden">

        {{-- En-tête section --}}
        <div class="flex flex-col gap-3 p-6 pb-4 sm:flex-row sm:items-center sm:justify-between">
            <h3 class="text-xl font-semibold tracking-tight text-ink">{{ __('Factures du mois') }} · {{ ucfirst($this->selectedPeriodLabel) }}</h3>

            <div class="flex items-center gap-2">
                {{-- Par page --}}
                <x-select-native>
                    <select
                        wire:model.live="perPage"
                        class="col-start-1 row-start-1 appearance-none rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2 pr-8 text-sm text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                    >
                        <option value="20">20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </x-select-native>

                {{-- Sélecteur de mois --}}
                <x-select-native>
                    <select
                        wire:model.live="selectedPeriod"
                        class="col-start-1 row-start-1 appearance-none rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2 pr-8 text-sm text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                    >
                        @foreach ($this->availableMonths as $m)
                            <option value="{{ $m['value'] }}">{{ $m['label'] }}</option>
                        @endforeach
                    </select>
                </x-select-native>
            </div>
        </div>

        {{-- Tableau --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-y border-slate-100 bg-slate-50/80">
                        <th class="px-6 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Référence') }}</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Client final') }}</th>
                        <th class="px-4 py-3 text-right text-sm font-semibold text-slate-500">{{ __('Montant HT') }}</th>
                        <th class="px-4 py-3 text-right text-sm font-semibold text-slate-500">{{ __('TVA') }}</th>
                        <th class="px-4 py-3 text-right text-sm font-semibold text-slate-500">{{ __('Montant TTC') }}</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Émise le') }}</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Échéance') }}</th>
                        <th class="px-4 py-3 text-center text-sm font-semibold text-slate-500">{{ __('Retard') }}</th>
                        <th class="px-4 py-3 text-center text-sm font-semibold text-slate-500">{{ __('Relances') }}</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Statut') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($this->invoices->take($perPage) as $invoice)
                        @php
                            $isPaid    = $invoice->status === InvoiceStatus::Paid;
                            $isOverdue = $invoice->status === InvoiceStatus::Overdue;

                            if ($isPaid && $invoice->paid_at) {
                                $delayDays = $invoice->paid_at->gt($invoice->due_at)
                                    ? (int) $invoice->due_at->diffInDays($invoice->paid_at)
                                    : 0;
                            } else {
                                $delayDays = $isOverdue ? (int) $invoice->due_at->diffInDays(now()) : 0;
                            }

                            $delayClass = match (true) {
                                $delayDays >= 60 => 'text-rose-500',
                                $delayDays > 0 => 'text-amber-500',
                                default => 'text-slate-500',
                            };

                            $statusConfig = match ($invoice->status) {
                                InvoiceStatus::Paid               => ['label' => 'Payée',       'class' => 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20'],
                                InvoiceStatus::Overdue            => ['label' => 'Impayée',     'class' => 'bg-rose-100 text-rose-700'],
                                InvoiceStatus::PartiallyPaid      => ['label' => 'Partiel',     'class' => 'bg-orange-100 text-orange-700'],
                                InvoiceStatus::Sent,
                                InvoiceStatus::Certified          => ['label' => 'En attente',  'class' => 'bg-amber-50 text-amber-700'],
                                InvoiceStatus::Draft              => ['label' => 'Brouillon',   'class' => 'bg-slate-100 text-slate-600'],
                                InvoiceStatus::Cancelled          => ['label' => 'Annulée',     'class' => 'bg-slate-100 text-slate-500'],
                                default                           => ['label' => ucfirst($invoice->status->value), 'class' => 'bg-slate-100 text-slate-600'],
                            };
                        @endphp
                        <tr
                            wire:click="viewInvoice('{{ $invoice->id }}')"
                            class="cursor-pointer transition hover:bg-slate-50/80"
                        >
                            <td class="px-6 py-4 font-mono text-sm font-semibold text-ink">
                                {{ $invoice->reference }}
                            </td>
                            <td class="px-4 py-4 text-slate-700">
                                {{ $invoice->client?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-4 text-right text-slate-600">
                                {{ format_money($invoice->subtotal, compact: true) }}
                            </td>
                            <td class="px-4 py-4 text-right text-slate-500">
                                {{ format_money($invoice->tax_amount, compact: true) }}
                            </td>
                            <td class="px-4 py-4 text-right font-semibold text-ink">
                                {{ format_money($invoice->total, compact: true) }}
                            </td>
                            <td class="px-4 py-4 text-slate-600">
                                {{ format_date($invoice->issued_at) }}
                            </td>
                            <td class="px-4 py-4 text-slate-600">
                                {{ format_date($invoice->due_at) }}
                            </td>
                            <td class="px-4 py-4 text-center">
                                <span class="font-semibold {{ $delayClass }}">{{ $delayDays }} j</span>
                            </td>
                            <td class="px-4 py-4 text-center text-slate-600">
                                @if ($invoice->reminders_count === 0)
                                    <span class="text-slate-500">0</span>
                                @else
                                    {{ $invoice->reminders_count }}
                                    {{ $invoice->reminders_count === 1 ? __('envoyée') : __('envoyées') }}
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-sm font-semibold {{ $statusConfig['class'] }}">
                                    {{ $statusConfig['label'] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-6 py-10 text-center text-sm text-slate-500">
                                {{ __('Aucune facture ce mois.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Footer show-more --}}
        @if ($this->invoices->count() > $perPage)
            <div class="flex items-center justify-center border-t border-slate-100 px-6 py-4 text-sm text-slate-500">
                + {{ $this->invoices->count() - $perPage }} {{ __('autres factures ce mois') }}
                &nbsp;·&nbsp;
                <button
                    wire:click="$set('perPage', 9999)"
                    class="font-semibold text-primary hover:underline"
                >
                    {{ __('Afficher tout') }}
                </button>
            </div>
        @endif
    </section>

    {{-- ─── Modale détail facture ─────────────────────────────────────────── --}}
    @if ($this->selectedInvoice)
        <x-invoices.detail-modal :invoice="$this->selectedInvoice" close-action="closeInvoice" />
    @endif

    {{-- ─── Modale Export Comptable ──────────────────────────────────────── --}}
    <flux:modal name="export-comptable" variant="bare" closable class="!bg-transparent !p-0 !shadow-none !ring-0">
        <div class="w-[500px] max-w-[500px] rounded-[2rem] bg-white p-8">

            <h3 class="text-xl font-bold text-ink">{{ __('Export comptable') }}</h3>
            <p class="mt-1 text-sm text-slate-500">{{ $company->name }}</p>

            {{-- Période --}}
            <div class="mt-6">
                <label class="text-sm font-medium text-slate-700">{{ __('Période') }}</label>
                <x-select-native>
                    <select
                        wire:model.live="exportPeriod"
                        class="col-start-1 row-start-1 appearance-none mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 pr-8 text-sm font-medium text-ink shadow-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                    >
                        @foreach (['Mois', 'Trimestre', 'Semestre', 'Année'] as $groupType)
                            @php $grouped = collect($this->exportPeriods)->where('type', $groupType); @endphp
                            @if ($grouped->isNotEmpty())
                                <optgroup label="{{ $groupType }}">
                                    @foreach ($grouped as $period)
                                        <option value="{{ $period['value'] }}">{{ $period['label'] }}</option>
                                    @endforeach
                                </optgroup>
                            @endif
                        @endforeach
                    </select>
                </x-select-native>
            </div>

            {{-- Format --}}
            <div class="mt-5">
                <label class="text-sm/6 font-medium text-slate-700">{{ __('Format') }}</label>
                <div class="mt-3 flex items-center space-x-10">
                    @foreach ([
                        'sage100' => 'Sage 100',
                        'ebp'    => 'EBP',
                        'excel'  => 'Excel',
                    ] as $value => $label)
                        <div class="flex items-center">
                            <input
                                id="export-format-{{ $value }}"
                                type="radio"
                                wire:model="exportFormat"
                                value="{{ $value }}"
                                class="relative size-4 appearance-none rounded-full border border-slate-300 bg-white before:absolute before:inset-1 before:rounded-full before:bg-white checked:border-primary checked:bg-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary not-checked:before:hidden forced-colors:appearance-auto forced-colors:before:hidden"
                            />
                            <label for="export-format-{{ $value }}" class="ml-3 block cursor-pointer text-sm/6 font-medium text-ink">
                                {{ $label }}
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Résumé --}}
            @if ($exportPeriod)
                <div class="mt-6 rounded-xl bg-slate-50 px-5 py-3 text-sm font-medium text-slate-600">
                    {{ $this->exportInvoiceCount }} {{ $this->exportInvoiceCount > 1 ? 'factures' : 'facture' }}
                    · {{ $this->exportPeriodLabel() }}
                    · Format {{ ['sage100' => 'Sage 100', 'ebp' => 'EBP', 'excel' => 'Excel'][$exportFormat] ?? $exportFormat }}
                </div>
            @endif

            {{-- Action --}}
            <div class="mt-6">
                <button
                    type="button"
                    @class([
                        'w-full rounded-2xl py-3.5 text-base font-semibold transition',
                        'bg-primary text-white shadow-sm hover:bg-primary/90' => $this->exportInvoiceCount > 0,
                        'cursor-not-allowed bg-slate-100 text-slate-500' => $this->exportInvoiceCount === 0,
                    ])
                    @if ($this->exportInvoiceCount === 0) disabled @endif
                >
                    {{ __('Télécharger l\'export') }}
                </button>
            </div>
        </div>
    </flux:modal>

</div>
