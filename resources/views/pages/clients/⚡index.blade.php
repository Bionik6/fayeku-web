<?php

use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Auth\Models\Company;
use Modules\Compta\Partnership\Models\PartnerInvitation;
use Modules\Compta\Portfolio\Services\PortfolioService;
use Modules\PME\Invoicing\Enums\InvoiceStatus;
use Modules\PME\Invoicing\Models\Invoice;

new #[Title('Clients')] class extends Component {
    public ?Company $firm = null;

    #[Url] public string $search = '';

    #[Url] public string $filterStatus = 'all';

    #[Url] public string $filterPlan = '';

    #[Url] public string $sortBy = 'status';

    #[Url] public string $sortDirection = 'asc';

    public string $currentMonth = '';

    // ─── Invite modal state ─────────────────────────────────────────────
    public string $inviteCompanyName = '';

    public string $inviteContactName = '';

    public string $invitePhone = '';

    public string $invitePlan = 'essentiel';

    /** @var array<int, array<string, mixed>>|null */
    private ?array $portfolioCache = null;

    public function mount(): void
    {
        $this->currentMonth = ucfirst(now()->locale('fr_FR')->translatedFormat('F Y'));

        $this->firm = auth()->user()->companies()
            ->where('type', 'accountant_firm')
            ->first();
    }

    /** @return array<int, array<string, mixed>> */
    #[Computed]
    public function rows(): array
    {
        $raw = $this->buildRawPortfolio();

        if ($this->search !== '') {
            $term = mb_strtolower($this->search);
            $raw = array_values(array_filter(
                $raw,
                fn (array $row) => str_contains(mb_strtolower($row['name']), $term)
            ));
        }

        if ($this->filterPlan !== '') {
            $raw = array_values(array_filter(
                $raw,
                fn (array $row) => $row['plan_slug'] === $this->filterPlan
            ));
        }

        if ($this->filterStatus !== 'all') {
            $raw = array_values(array_filter(
                $raw,
                fn (array $row) => $row['status'] === $this->filterStatus
            ));
        }

        $statusOrder = ['critique' => 0, 'attente' => 1, 'a_jour' => 2];

        usort($raw, function (array $a, array $b) use ($statusOrder): int {
            $cmp = match ($this->sortBy) {
                'name'           => strcmp($a['name'], $b['name']),
                'plan'           => strcmp($a['plan_slug'], $b['plan_slug']),
                'last_invoice'   => $a['last_invoice_days'] <=> $b['last_invoice_days'],
                'pending_amount' => $a['pending_amount'] <=> $b['pending_amount'],
                'recovery_rate'  => $a['recovery_rate'] <=> $b['recovery_rate'],
                default          => ($statusOrder[$a['status']] ?? 9) <=> ($statusOrder[$b['status']] ?? 9),
            };

            return $this->sortDirection === 'desc' ? -$cmp : $cmp;
        });

        return $raw;
    }

    /** @return array{all: int, a_jour: int, attente: int, critique: int} */
    #[Computed]
    public function statusCounts(): array
    {
        $raw = $this->buildRawPortfolio();

        return [
            'all'      => count($raw),
            'a_jour'   => count(array_filter($raw, fn (array $r) => $r['status'] === 'a_jour')),
            'attente'  => count(array_filter($raw, fn (array $r) => $r['status'] === 'attente')),
            'critique' => count(array_filter($raw, fn (array $r) => $r['status'] === 'critique')),
        ];
    }

    /** @return array<string, string> */
    #[Computed]
    public function availablePlans(): array
    {
        $plans = [];
        foreach ($this->buildRawPortfolio() as $row) {
            if ($row['plan_slug'] !== '') {
                $plans[$row['plan_slug']] = $row['plan'];
            }
        }
        asort($plans);

        return $plans;
    }

    /** @return array{pending_amount_total: int, critical_clients: int, average_recovery_rate: int} */
    #[Computed]
    public function summaryStats(): array
    {
        $raw = $this->buildRawPortfolio();

        if ($raw === []) {
            return [
                'pending_amount_total' => 0,
                'critical_clients' => 0,
                'average_recovery_rate' => 0,
            ];
        }

        $totalInvoiced = array_sum(array_column($raw, 'total_invoiced'));
        $totalCollected = array_sum(array_column($raw, 'total_collected'));

        return [
            'pending_amount_total' => array_sum(array_column($raw, 'pending_amount')),
            'critical_clients' => count(array_filter($raw, fn (array $row) => $row['status'] === 'critique')),
            'average_recovery_rate' => $totalInvoiced > 0
                ? (int) round($totalCollected / $totalInvoiced * 100)
                : 100,
        ];
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function setFilterStatus(string $status): void
    {
        $this->filterStatus = $status;
    }

    // ─── Invite modal actions ────────────────────────────────────────────

    public function openInviteModal(): void
    {
        $this->resetInviteForm();
        $this->modal('invite-pme')->show();
    }

    public function sendInvitation(): void
    {
        $this->validate([
            'inviteCompanyName' => 'required|string|max:255',
            'inviteContactName' => 'required|string|max:255',
            'invitePhone' => 'required|string|max:30',
            'invitePlan' => 'required|in:basique,essentiel',
        ], [
            'inviteCompanyName.required' => __('Le nom de l\'entreprise est requis.'),
            'inviteContactName.required' => __('Le nom du contact est requis.'),
            'invitePhone.required' => __('Le numéro WhatsApp est requis.'),
        ]);

        if (! $this->firm) {
            return;
        }

        $existing = PartnerInvitation::query()
            ->where('accountant_firm_id', $this->firm->id)
            ->where('invitee_phone', $this->invitePhone)
            ->where('status', '!=', 'expired')
            ->first();

        if ($existing) {
            $this->addError('invitePhone', __('Cette PME a déjà été invitée récemment.'));

            return;
        }

        PartnerInvitation::create([
            'accountant_firm_id' => $this->firm->id,
            'token' => Str::random(32),
            'invitee_company_name' => $this->inviteCompanyName,
            'invitee_name' => $this->inviteContactName,
            'invitee_phone' => $this->invitePhone,
            'recommended_plan' => $this->invitePlan,
            'channel' => 'whatsapp',
            'status' => 'pending',
            'expires_at' => now()->addDays(30),
        ]);

        $this->resetInviteForm();
        $this->modal('invite-pme')->close();

        session()->flash('message', __('Invitation envoyée avec succès.'));
    }

    public function resetInviteForm(): void
    {
        $this->inviteCompanyName = '';
        $this->inviteContactName = '';
        $this->invitePhone = '';
        $this->invitePlan = 'essentiel';
        $this->resetErrorBag();
    }

    /** @return array<int, array<string, mixed>> */
    private function buildRawPortfolio(): array
    {
        if ($this->portfolioCache !== null) {
            return $this->portfolioCache;
        }

        if (! $this->firm) {
            return $this->portfolioCache = [];
        }

        $smeIds = app(PortfolioService::class)->activeSmeIds($this->firm);

        if ($smeIds->isEmpty()) {
            return [];
        }

        $allInvoices = Invoice::query()
            ->whereIn('company_id', $smeIds)
            ->get()
            ->groupBy('company_id');

        $companies = Company::query()
            ->whereIn('id', $smeIds)
            ->with('subscription')
            ->get()
            ->keyBy('id');

        $portfolio = [];

        foreach ($smeIds as $smeId) {
            $company = $companies->get($smeId);

            if (! $company) {
                continue;
            }

            $invoices = $allInvoices->get($smeId, collect());

            $unpaidInvoices = $invoices->filter(
                fn ($inv) => in_array($inv->status, [InvoiceStatus::Overdue, InvoiceStatus::PartiallyPaid])
            );

            $totalInvoiced = $invoices->sum('total');
            $totalCollected = $invoices->sum(fn ($inv) => (int) ($inv->amount_paid ?? 0));
            $pendingAmount = $unpaidInvoices->sum(fn ($inv) => $inv->total - (int) ($inv->amount_paid ?? 0));
            $recoveryRate = $totalInvoiced > 0 ? (int) round($totalCollected / $totalInvoiced * 100) : 100;

            $lastInvoice = $invoices->sortByDesc('issued_at')->first();

            if ($lastInvoice) {
                $daysDiff = (int) now()->diffInDays($lastInvoice->issued_at);
                $lastInvoiceLabel = match (true) {
                    $daysDiff === 0 => "Aujourd'hui",
                    $daysDiff === 1 => 'Hier',
                    default => 'Il y a '.$daysDiff.' j',
                };
            } else {
                $daysDiff = PHP_INT_MAX;
                $lastInvoiceLabel = '—';
            }

            $hasCritical = $invoices->filter(
                fn ($inv) => $inv->status === InvoiceStatus::Overdue
                    && $inv->due_at
                    && $inv->due_at->lt(now()->subDays(60))
            )->isNotEmpty();

            $status = match (true) {
                $hasCritical => 'critique',
                $unpaidInvoices->isNotEmpty() => 'attente',
                default => 'a_jour',
            };

            $planSlug = strtolower($company->subscription?->plan_slug ?? $company->plan ?? '');
            $planLabel = $planSlug !== '' ? ucfirst($planSlug) : '—';

            $nameParts = collect(explode(' ', $company->name));
            $initials = $nameParts->map(fn ($w) => strtoupper($w[0] ?? ''))->take(2)->join('');

            $portfolio[] = [
                'id'                 => $company->id,
                'name'               => $company->name,
                'initials'           => $initials,
                'plan'               => $planLabel,
                'plan_slug'          => $planSlug,
                'last_invoice_label' => $lastInvoiceLabel,
                'last_invoice_days'  => $daysDiff,
                'total_invoiced'     => $totalInvoiced,
                'total_collected'    => $totalCollected,
                'unpaid_count'       => $unpaidInvoices->count(),
                'pending_amount'     => $pendingAmount,
                'recovery_rate'      => $recoveryRate,
                'status'             => $status,
            ];
        }

        return $this->portfolioCache = $portfolio;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- En-tête --}}
    <section class="app-shell-panel overflow-hidden">
        <div class="flex flex-col gap-4 p-6 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.24em] text-teal">{{ __('Portefeuille') }}</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-ink">{{ __('Clients') }}</h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $this->statusCounts['all'] }} {{ $this->statusCounts['all'] > 1 ? 'clients suivis' : 'client suivi' }}
                    · {{ $this->statusCounts['critique'] }} {{ $this->statusCounts['critique'] > 1 ? 'clients critiques' : 'client critique' }}
                    · {{ $this->statusCounts['attente'] }} {{ __('à surveiller') }}
                </p>
            </div>

            <div class="flex shrink-0 items-center gap-3">
                <button
                    type="button"
                    wire:click="openInviteModal"
                    class="inline-flex items-center gap-2 rounded-xl border border-primary/20 bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-[0_8px_24px_rgba(2,77,77,0.18)] transition hover:bg-primary/90"
                >
                    <flux:icon name="plus" variant="micro" />
                    {{ __('Inviter une PME') }}
                </button>
            </div>
        </div>
    </section>

    {{-- Synthèse portefeuille --}}
    <section class="grid gap-4 md:grid-cols-3">
        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-rose-50">
                    <flux:icon name="banknotes" class="size-5 text-rose-500" />
                </div>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-sm font-medium text-slate-500">
                    {{ __('Portefeuille') }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Montant total en attente') }}</p>
            <p class="mt-1 text-3xl font-semibold tracking-tight text-ink">{{ number_format($this->summaryStats['pending_amount_total'], 0, ',', ' ') }} F</p>
        </article>

        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-amber-50">
                    <flux:icon name="exclamation-triangle" class="size-5 text-amber-500" />
                </div>
                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-sm font-semibold text-amber-700">
                    {{ __('Critiques') }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Clients critiques') }}</p>
            <p class="mt-1 text-3xl font-semibold tracking-tight text-amber-600">{{ $this->summaryStats['critical_clients'] }}</p>
        </article>

        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-emerald-50">
                    <flux:icon name="chart-bar" class="size-5 text-accent" />
                </div>
                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-sm font-semibold text-emerald-700">
                    {{ $currentMonth }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Taux moyen de recouvrement') }}</p>
            <p class="mt-1 text-3xl font-semibold tracking-tight text-accent">{{ $this->summaryStats['average_recovery_rate'] }}%</p>
        </article>
    </section>

    {{-- Onglets statut + filtres --}}
    <section class="app-shell-panel p-5">
        {{-- Onglets de filtre --}}
        <p class="mb-3 text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ __('Filtrer les clients') }}</p>
        <div class="flex flex-wrap items-center gap-2">
            @foreach ([
                'all'      => ['label' => 'Tous',       'dot' => null,        'activeClass' => 'bg-primary text-white',     'badgeInactive' => 'bg-slate-100 text-slate-500'],
                'a_jour'   => ['label' => 'À jour',     'dot' => 'bg-accent', 'activeClass' => 'bg-emerald-600 text-white', 'badgeInactive' => 'bg-emerald-100 text-emerald-700'],
                'attente'  => ['label' => 'À surveiller',  'dot' => 'bg-amber-400', 'activeClass' => 'bg-amber-500 text-white',  'badgeInactive' => 'bg-amber-100 text-amber-700'],
                'critique' => ['label' => 'Critiques',  'dot' => 'bg-rose-500',  'activeClass' => 'bg-rose-500 text-white',   'badgeInactive' => 'bg-rose-100 text-rose-700'],
            ] as $key => $tab)
                <button
                    wire:click="setFilterStatus('{{ $key }}')"
                    @class([
                        'inline-flex items-center gap-1.5 rounded-full px-4 py-1.5 text-sm font-semibold transition',
                        $tab['activeClass']                                                                              => $filterStatus === $key,
                        'bg-white border border-slate-200 text-slate-600 hover:border-primary/30 hover:text-primary'   => $filterStatus !== $key,
                    ])
                >
                    @if ($tab['dot'])
                        <span @class(['size-2 rounded-full', 'bg-white' => $filterStatus === $key, $tab['dot'] => $filterStatus !== $key])></span>
                    @endif
                    {{ $tab['label'] }}
                    <span @class([
                        'rounded-full px-1.5 py-px text-sm font-bold',
                        'bg-white/20 text-white'       => $filterStatus === $key,
                        $tab['badgeInactive']           => $filterStatus !== $key,
                    ])>{{ $this->statusCounts[$key] }}</span>
                </button>
            @endforeach
        </div>

        {{-- Recherche + filtre plan --}}
        <div class="mt-4 flex flex-col gap-3 sm:flex-row">
            <div class="relative flex-1">
                <svg class="absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <input
                    wire:model.live.debounce.300ms="search"
                    type="text"
                    placeholder="{{ __('Rechercher une entreprise...') }}"
                    class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 py-3 pl-10 pr-4 text-sm text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                />
            </div>

            <x-select-native>
                <select
                    wire:model.live="filterPlan"
                    class="col-start-1 row-start-1 appearance-none rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 pr-8 text-sm text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10 sm:w-48"
                >
                    <option value="">{{ __('Toutes les offres') }}</option>
                    @foreach ($this->availablePlans as $slug => $label)
                        <option value="{{ $slug }}">{{ $label }}</option>
                    @endforeach
                </select>
            </x-select-native>
        </div>
    </section>

    {{-- Tableau --}}
    <section class="app-shell-panel overflow-hidden">
        @if (count($this->rows) > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/80">
                            {{-- Client --}}
                            <th class="px-6 py-3 text-left">
                                <button wire:click="sort('name')" class="inline-flex items-center gap-1 text-sm font-semibold text-slate-500 hover:text-primary">
                                    {{ __('Client') }}
                                    @if ($sortBy === 'name')
                                        @if ($sortDirection === 'asc') <flux:icon.chevron-up class="size-3.5 text-primary" />
                                        @else <flux:icon.chevron-down class="size-3.5 text-primary" /> @endif
                                    @else
                                        <flux:icon.chevrons-up-down class="size-3.5 text-slate-300" />
                                    @endif
                                </button>
                            </th>
                            {{-- Offre --}}
                            <th class="px-4 py-3 text-left">
                                <button wire:click="sort('plan')" class="inline-flex items-center gap-1 text-sm font-semibold text-slate-500 hover:text-primary">
                                    {{ __('Offre') }}
                                    @if ($sortBy === 'plan')
                                        @if ($sortDirection === 'asc') <flux:icon.chevron-up class="size-3.5 text-primary" />
                                        @else <flux:icon.chevron-down class="size-3.5 text-primary" /> @endif
                                    @else
                                        <flux:icon.chevrons-up-down class="size-3.5 text-slate-300" />
                                    @endif
                                </button>
                            </th>
                            {{-- Dernière facture --}}
                            <th class="px-4 py-3 text-left">
                                <button wire:click="sort('last_invoice')" class="inline-flex items-center gap-1 text-sm font-semibold text-slate-500 hover:text-primary">
                                    {{ __('Dernière facture') }}
                                    @if ($sortBy === 'last_invoice')
                                        @if ($sortDirection === 'asc') <flux:icon.chevron-up class="size-3.5 text-primary" />
                                        @else <flux:icon.chevron-down class="size-3.5 text-primary" /> @endif
                                    @else
                                        <flux:icon.chevrons-up-down class="size-3.5 text-slate-300" />
                                    @endif
                                </button>
                            </th>
                            {{-- Impayés --}}
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">
                                {{ __('Impayés') }}
                            </th>
                            {{-- Montant en attente --}}
                            <th class="px-4 py-3 text-left">
                                <button wire:click="sort('pending_amount')" class="inline-flex items-center gap-1 text-sm font-semibold text-slate-500 hover:text-primary">
                                    {{ __('Montant en attente') }}
                                    @if ($sortBy === 'pending_amount')
                                        @if ($sortDirection === 'asc') <flux:icon.chevron-up class="size-3.5 text-primary" />
                                        @else <flux:icon.chevron-down class="size-3.5 text-primary" /> @endif
                                    @else
                                        <flux:icon.chevrons-up-down class="size-3.5 text-slate-300" />
                                    @endif
                                </button>
                            </th>
                            {{-- Taux de recouvrement --}}
                            <th class="px-4 py-3 text-left">
                                <button wire:click="sort('recovery_rate')" class="inline-flex items-center gap-1 text-sm font-semibold text-slate-500 hover:text-primary">
                                    {{ __('Taux de recouvrement') }}
                                    @if ($sortBy === 'recovery_rate')
                                        @if ($sortDirection === 'asc') <flux:icon.chevron-up class="size-3.5 text-primary" />
                                        @else <flux:icon.chevron-down class="size-3.5 text-primary" /> @endif
                                    @else
                                        <flux:icon.chevrons-up-down class="size-3.5 text-slate-300" />
                                    @endif
                                </button>
                            </th>
                            {{-- Statut --}}
                            <th class="px-4 py-3 text-left">
                                <button wire:click="sort('status')" class="inline-flex items-center gap-1 text-sm font-semibold text-slate-500 hover:text-primary">
                                    {{ __('Statut') }}
                                    @if ($sortBy === 'status')
                                        @if ($sortDirection === 'asc') <flux:icon.chevron-up class="size-3.5 text-primary" />
                                        @else <flux:icon.chevron-down class="size-3.5 text-primary" /> @endif
                                    @else
                                        <flux:icon.chevrons-up-down class="size-3.5 text-slate-300" />
                                    @endif
                                </button>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($this->rows as $row)
                            <tr
                                wire:key="clients-row-{{ $row['id'] }}"
                                @click="Livewire.navigate('{{ route('clients.show', $row['id']) }}')"
                                @class([
                                    'cursor-pointer transition hover:bg-slate-50/90',
                                    'bg-rose-50/30 hover:bg-rose-50/60' => $row['status'] === 'critique',
                                ])
                            >
                                {{-- Client --}}
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-2xl bg-mist text-sm font-bold text-primary">
                                            {{ $row['initials'] }}
                                        </span>
                                        <span class="font-semibold text-ink">{{ $row['name'] }}</span>
                                    </div>
                                </td>
                                {{-- Offre --}}
                                <td class="px-4 py-4">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2.5 py-0.5 text-sm font-semibold',
                                        'bg-teal-100 text-teal-700' => $row['plan_slug'] === 'essentiel',
                                        'bg-violet-100 text-violet-700' => $row['plan_slug'] === 'basique',
                                        'bg-amber-100 text-amber-700' => $row['plan_slug'] === 'premium',
                                        'bg-slate-100 text-slate-600' => ! in_array($row['plan_slug'], ['essentiel', 'basique', 'premium']),
                                    ])>
                                        {{ $row['plan'] }}
                                    </span>
                                </td>
                                {{-- Dernière facture --}}
                                <td class="px-4 py-4 text-slate-600">{{ $row['last_invoice_label'] }}</td>
                                {{-- Factures impayées --}}
                                <td class="px-4 py-4">
                                    @if ($row['unpaid_count'] > 0)
                                        <span @class([
                                            'font-semibold',
                                            'text-rose-500' => $row['status'] === 'critique',
                                            'text-amber-500' => $row['status'] === 'attente',
                                        ])>
                                            {{ $row['unpaid_count'] }} {{ $row['unpaid_count'] > 1 ? 'factures' : 'facture' }}
                                        </span>
                                    @else
                                        <span class="text-slate-500">0</span>
                                    @endif
                                </td>
                                {{-- Montant en attente --}}
                                <td class="px-4 py-4 font-semibold text-ink">
                                    @if ($row['pending_amount'] > 0)
                                        {{ number_format($row['pending_amount'], 0, ',', ' ') }} F
                                    @else
                                        <span class="text-slate-500">—</span>
                                    @endif
                                </td>
                                {{-- Taux recouvrement --}}
                                <td class="px-4 py-4">
                                    <span @class([
                                        'font-semibold',
                                        'text-rose-500' => $row['recovery_rate'] < 75,
                                        'text-amber-500' => $row['recovery_rate'] >= 75 && $row['recovery_rate'] < 95,
                                        'text-accent' => $row['recovery_rate'] >= 95,
                                    ])>{{ $row['recovery_rate'] }}%</span>
                                </td>
                                {{-- Statut --}}
                                <td class="px-4 py-4">
                                    <span @class([
                                        'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-sm font-semibold ring-1 ring-inset',
                                        'bg-rose-50 text-rose-700 ring-rose-600/20'   => $row['status'] === 'critique',
                                        'bg-amber-50 text-amber-700 ring-amber-600/20' => $row['status'] === 'attente',
                                        'bg-green-50 text-green-700 ring-green-600/20' => $row['status'] === 'a_jour',
                                    ])>
                                        <span class="size-1.5 rounded-full
                                            @if ($row['status'] === 'critique') bg-rose-500
                                            @elseif ($row['status'] === 'attente') bg-amber-500
                                            @else bg-green-500 @endif
                                        "></span>
                                        @if ($row['status'] === 'critique') Critique
                                        @elseif ($row['status'] === 'attente') À surveiller
                                        @else À jour @endif
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-10 text-center text-sm text-slate-500">
                                    {{ __('Aucun client ne correspond à ces filtres.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>


@elseif ($this->statusCounts['all'] > 0)
            <div class="px-6 py-10 text-center">
                <p class="text-sm text-slate-500">{{ __('Aucun client ne correspond à ces filtres.') }}</p>
            </div>
        @else
            <div class="px-6 py-10 text-center">
                <p class="text-sm text-slate-500">
                    @if ($firm)
                        {{ __('Aucun client dans votre portefeuille pour le moment.') }}
                    @else
                        {{ __('Aucun cabinet trouvé pour votre compte.') }}
                    @endif
                </p>
            </div>
        @endif
    </section>

    {{-- ─── Modale : Inviter une PME ─────────────────────────────────────── --}}
    <flux:modal name="invite-pme" variant="bare" closable class="!bg-transparent !p-0 !shadow-none !ring-0">
        <div class="w-[540px] max-w-[540px] rounded-[2rem] bg-white p-8">
            <h3 class="text-xl font-bold text-ink">{{ __('Inviter une PME') }}</h3>
            <p class="mt-1 text-sm text-slate-500">{{ __('Envoyez une invitation personnalisée à une PME pour l\'aider à rejoindre Fayeku.') }}</p>

            {{-- Nom entreprise --}}
            <div class="mt-6">
                <label for="invite-company" class="text-sm font-medium text-slate-700">{{ __('Nom de l\'entreprise') }}</label>
                <input
                    id="invite-company"
                    type="text"
                    wire:model="inviteCompanyName"
                    placeholder="{{ __('Ex. Transport Ngor SARL') }}"
                    class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-ink shadow-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                />
                @error('inviteCompanyName')
                    <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Nom contact --}}
            <div class="mt-4">
                <label for="invite-contact" class="text-sm font-medium text-slate-700">{{ __('Nom du contact') }}</label>
                <input
                    id="invite-contact"
                    type="text"
                    wire:model="inviteContactName"
                    placeholder="{{ __('Ex. Moussa Diallo') }}"
                    class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-ink shadow-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                />
                @error('inviteContactName')
                    <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Numéro WhatsApp --}}
            <div class="mt-4">
                <label for="invite-phone" class="text-sm font-medium text-slate-700">{{ __('Numéro WhatsApp') }}</label>
                <input
                    id="invite-phone"
                    type="tel"
                    wire:model="invitePhone"
                    placeholder="+221 77 000 00 00"
                    class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-ink shadow-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                />
                @error('invitePhone')
                    <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Plan recommandé --}}
            <div class="mt-4">
                <label class="text-sm font-medium text-slate-700">{{ __('Plan recommandé') }}</label>
                <div class="mt-2 grid grid-cols-2 gap-3">
                    <label @class([
                        'relative flex cursor-pointer items-center gap-3 rounded-xl border p-4 transition',
                        'border-primary bg-primary/5 ring-2 ring-primary' => $invitePlan === 'basique',
                        'border-slate-200 bg-white hover:bg-slate-50' => $invitePlan !== 'basique',
                    ])>
                        <input type="radio" wire:model.live="invitePlan" value="basique" class="sr-only" />
                        <div>
                            <p class="text-sm font-semibold text-ink">{{ __('Basique') }}</p>
                            <p class="text-sm text-slate-500">10 000 FCFA / mois</p>
                        </div>
                    </label>
                    <label @class([
                        'relative flex cursor-pointer items-center gap-3 rounded-xl border p-4 transition',
                        'border-primary bg-primary/5 ring-2 ring-primary' => $invitePlan === 'essentiel',
                        'border-slate-200 bg-white hover:bg-slate-50' => $invitePlan !== 'essentiel',
                    ])>
                        <input type="radio" wire:model.live="invitePlan" value="essentiel" class="sr-only" />
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-semibold text-ink">{{ __('Essentiel') }}</p>
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">{{ __('Recommandé') }}</span>
                            </div>
                            <p class="text-sm text-slate-500">20 000 FCFA / mois · 2 mois offerts</p>
                        </div>
                    </label>
                </div>
            </div>

            {{-- Aperçu message --}}
            <div class="mt-6 rounded-xl border border-slate-100 bg-slate-50 p-4">
                <p class="text-sm font-semibold uppercase tracking-wider text-slate-500">{{ __('Aperçu message WhatsApp') }}</p>
                <p class="mt-2 text-sm text-slate-600 italic">
                    "{{ __('Bonjour') }} {{ $inviteContactName ?: '[Contact]' }}, {{ $firm?->name ?? __('votre cabinet') }} {{ __('vous invite à rejoindre Fayeku pour simplifier votre facturation.') }}
                    @if ($invitePlan === 'essentiel')
                        {{ __('Profitez de 2 mois offerts pour démarrer.') }}
                    @endif
                    "</p>
            </div>

            {{-- Actions --}}
            <div class="mt-6">
                <button
                    type="button"
                    wire:click="sendInvitation"
                    class="w-full rounded-2xl bg-primary py-3.5 text-base font-semibold text-white shadow-sm transition hover:bg-primary/90"
                >
                    {{ __('Envoyer l\'invitation WhatsApp') }}
                </button>
            </div>

            @if ($firm)
                <p class="mt-3 text-center text-sm text-slate-500">
                    {{ __('Votre lien') }} : <span class="font-medium text-primary">fayeku.sn/invite/{{ Str::slug($firm->name) }}</span>
                </p>
            @endif
        </div>
    </flux:modal>

</div>
