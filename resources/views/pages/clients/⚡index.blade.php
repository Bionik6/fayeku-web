<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Auth\Models\AccountantCompany;
use Modules\Auth\Models\Company;
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

    /** @return array<int, array<string, mixed>> */
    private function buildRawPortfolio(): array
    {
        if ($this->portfolioCache !== null) {
            return $this->portfolioCache;
        }

        if (! $this->firm) {
            return $this->portfolioCache = [];
        }

        $smeIds = AccountantCompany::query()
            ->where('accountant_firm_id', $this->firm->id)
            ->whereNull('ended_at')
            ->pluck('sme_company_id');

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
                    default => 'Il y a '.$daysDiff.'j',
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
                    {{ $this->statusCounts['all'] }} {{ $this->statusCounts['all'] > 1 ? 'clients' : 'client' }}
                    · {{ $currentMonth }}
                </p>
            </div>

            <div class="flex shrink-0 items-center gap-3">
                <a
                    href="#"
                    class="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-[0_8px_24px_rgba(2,77,77,0.18)] transition hover:bg-primary/90"
                >
                    <flux:icon name="plus" variant="micro" />
                    {{ __('Inviter un client') }}
                </a>
            </div>
        </div>
    </section>

    {{-- Onglets statut + filtres --}}
    <section class="app-shell-panel p-5">
        {{-- Onglets de filtre --}}
        <div class="flex flex-wrap items-center gap-2">
            @foreach (['all' => 'Tous', 'a_jour' => 'À jour', 'attente' => 'Attention', 'critique' => 'Critiques'] as $key => $label)
                <button
                    wire:click="setFilterStatus('{{ $key }}')"
                    @class([
                        'inline-flex items-center gap-1.5 rounded-full px-4 py-1.5 text-sm font-semibold transition',
                        'bg-primary text-white shadow-sm' => $filterStatus === $key,
                        'bg-white border border-slate-200 text-slate-600 hover:border-primary/30 hover:text-primary' => $filterStatus !== $key,
                    ])
                >
                    @if ($key === 'a_jour')
                        <span @class(['size-2 rounded-full', 'bg-white' => $filterStatus === $key, 'bg-accent' => $filterStatus !== $key])></span>
                    @elseif ($key === 'attente')
                        <span @class(['size-2 rounded-full', 'bg-white' => $filterStatus === $key, 'bg-amber-400' => $filterStatus !== $key])></span>
                    @elseif ($key === 'critique')
                        <span @class(['size-2 rounded-full', 'bg-white' => $filterStatus === $key, 'bg-rose-500' => $filterStatus !== $key])></span>
                    @endif
                    {{ $label }}
                    <span @class([
                        'rounded-full px-1.5 py-px text-xs font-bold',
                        'bg-white/20 text-white' => $filterStatus === $key,
                        'bg-slate-100 text-slate-500' => $filterStatus !== $key,
                    ])>{{ $this->statusCounts[$key] }}</span>
                </button>
            @endforeach
        </div>

        {{-- Recherche + filtre plan --}}
        <div class="mt-4 flex flex-col gap-3 sm:flex-row">
            <div class="relative flex-1">
                <svg class="absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <input
                    wire:model.live.debounce.300ms="search"
                    type="text"
                    placeholder="{{ __('Rechercher un client...') }}"
                    class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 py-3 pl-10 pr-4 text-sm text-ink placeholder:text-slate-400 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                />
            </div>

            <select
                wire:model.live="filterPlan"
                class="rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10 sm:w-48"
            >
                <option value="">{{ __('Tous les plans') }}</option>
                @foreach ($this->availablePlans as $slug => $label)
                    <option value="{{ $slug }}">{{ $label }}</option>
                @endforeach
            </select>
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
                                <button wire:click="sort('name')" class="inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-slate-500 hover:text-primary">
                                    {{ __('Client') }}
                                    @if ($sortBy === 'name')
                                        @if ($sortDirection === 'asc') <flux:icon.chevron-up class="size-3.5 text-primary" />
                                        @else <flux:icon.chevron-down class="size-3.5 text-primary" /> @endif
                                    @else
                                        <flux:icon.chevrons-up-down class="size-3.5 text-slate-300" />
                                    @endif
                                </button>
                            </th>
                            {{-- Plan --}}
                            <th class="px-4 py-3 text-left">
                                <button wire:click="sort('plan')" class="inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-slate-500 hover:text-primary">
                                    {{ __('Plan') }}
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
                                <button wire:click="sort('last_invoice')" class="inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-slate-500 hover:text-primary">
                                    {{ __('Dernière facture') }}
                                    @if ($sortBy === 'last_invoice')
                                        @if ($sortDirection === 'asc') <flux:icon.chevron-up class="size-3.5 text-primary" />
                                        @else <flux:icon.chevron-down class="size-3.5 text-primary" /> @endif
                                    @else
                                        <flux:icon.chevrons-up-down class="size-3.5 text-slate-300" />
                                    @endif
                                </button>
                            </th>
                            {{-- Factures impayées --}}
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                {{ __('Factures impayées') }}
                            </th>
                            {{-- Montant en attente --}}
                            <th class="px-4 py-3 text-left">
                                <button wire:click="sort('pending_amount')" class="inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-slate-500 hover:text-primary">
                                    {{ __('Montant en attente') }}
                                    @if ($sortBy === 'pending_amount')
                                        @if ($sortDirection === 'asc') <flux:icon.chevron-up class="size-3.5 text-primary" />
                                        @else <flux:icon.chevron-down class="size-3.5 text-primary" /> @endif
                                    @else
                                        <flux:icon.chevrons-up-down class="size-3.5 text-slate-300" />
                                    @endif
                                </button>
                            </th>
                            {{-- Taux recouvrement --}}
                            <th class="px-4 py-3 text-left">
                                <button wire:click="sort('recovery_rate')" class="inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-slate-500 hover:text-primary">
                                    {{ __('Taux recouvrement') }}
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
                                <button wire:click="sort('status')" class="inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-slate-500 hover:text-primary">
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
                                @click="Livewire.navigate('{{ route('clients.show', $row['id']) }}')"
                                class="cursor-pointer transition hover:bg-slate-50/80"
                            >
                                {{-- Client --}}
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-2xl bg-mist text-xs font-bold text-primary">
                                            {{ $row['initials'] }}
                                        </span>
                                        <span class="font-semibold text-ink">{{ $row['name'] }}</span>
                                    </div>
                                </td>
                                {{-- Plan --}}
                                <td class="px-4 py-4">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
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
                                        <span class="text-slate-400">0</span>
                                    @endif
                                </td>
                                {{-- Montant en attente --}}
                                <td class="px-4 py-4 font-semibold text-ink">
                                    @if ($row['pending_amount'] > 0)
                                        {{ number_format($row['pending_amount'], 0, ',', ' ') }} F
                                    @else
                                        <span class="text-slate-400">—</span>
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
                                        'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                        'bg-rose-50 text-rose-700' => $row['status'] === 'critique',
                                        'bg-amber-50 text-amber-700' => $row['status'] === 'attente',
                                        'bg-emerald-50 text-emerald-700' => $row['status'] === 'a_jour',
                                    ])>
                                        <span class="size-1.5 rounded-full
                                            @if ($row['status'] === 'critique') bg-rose-500
                                            @elseif ($row['status'] === 'attente') bg-amber-500
                                            @else bg-emerald-500 @endif
                                        "></span>
                                        @if ($row['status'] === 'critique') Critique
                                        @elseif ($row['status'] === 'attente') Attente
                                        @else À jour @endif
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-10 text-center text-sm text-slate-400">
                                    {{ __('Aucun client ne correspond à ces filtres.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>


@elseif ($this->statusCounts['all'] > 0)
            <div class="px-6 py-10 text-center">
                <p class="text-sm text-slate-400">{{ __('Aucun client ne correspond à ces filtres.') }}</p>
            </div>
        @else
            <div class="px-6 py-10 text-center">
                <p class="text-sm text-slate-400">
                    @if ($firm)
                        {{ __('Aucun client dans votre portefeuille pour le moment.') }}
                    @else
                        {{ __('Aucun cabinet trouvé pour votre compte.') }}
                    @endif
                </p>
            </div>
        @endif
    </section>

</div>
