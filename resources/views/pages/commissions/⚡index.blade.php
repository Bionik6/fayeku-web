<?php

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Auth\Models\Company;
use Modules\Compta\Partnership\Enums\PartnerTier;
use Modules\Compta\Partnership\Models\Commission;
use Modules\Compta\Partnership\Models\CommissionPayment;
use Modules\Compta\Portfolio\Services\PortfolioService;

new #[Title('Commissions')] class extends Component
{
    public ?Company $firm = null;

    public int $activeClientsCount = 0;

    public string $tierValue = 'partner';

    public string $tierLabel = 'Partner';

    public int $tierProgress = 0;

    public int $nextThreshold = 5;

    public string $tierRangeLabel = '1-4 clients';

    public string $nextTierLabel = 'Gold';

    public bool $isPlatinum = false;

    public bool $showAllCommissions = false;

    public string $currentMonth = '';

    public int $pendingClientsCount = 0;

    public string $search = '';

    public string $filterPlan = '';

    public string $filterStatus = '';

    public function mount(): void
    {
        $this->currentMonth = format_month(now());
        $this->firm = auth()->user()->accountantFirm();

        if (! $this->firm) {
            return;
        }

        $this->activeClientsCount = app(PortfolioService::class)->activeSmeIds($this->firm)->count();

        $tier = PartnerTier::fromActiveClients($this->activeClientsCount);
        $this->tierValue = $tier->value;
        $this->tierLabel = match ($tier) {
            PartnerTier::Partner => 'Partner',
            PartnerTier::Gold => 'Gold',
            PartnerTier::Platinum => 'Platinum',
        };
        $this->isPlatinum = $tier === PartnerTier::Platinum;

        [$this->tierProgress, $this->nextThreshold, $this->tierRangeLabel, $this->nextTierLabel] = match ($tier) {
            PartnerTier::Partner => [
                min(100, (int) round($this->activeClientsCount / 5 * 100)),
                5, '1–4 clients', 'Gold',
            ],
            PartnerTier::Gold => [
                min(100, (int) round(($this->activeClientsCount - 5) / 10 * 100)),
                15, '5–14 clients', 'Platinum',
            ],
            PartnerTier::Platinum => [100, 15, '15+ clients', ''],
        };
    }

    // ─── Computed ─────────────────────────────────────────────────────────

    /** @return Collection<int, Commission> */
    #[Computed]
    public function monthCommissions(): Collection
    {
        if (! $this->firm) {
            return collect();
        }

        return Commission::query()
            ->where('accountant_firm_id', $this->firm->id)
            ->whereYear('period_month', now()->year)
            ->whereMonth('period_month', now()->month)
            ->with(['smeCompany.subscription'])
            ->get()
            ->when($this->search !== '', fn ($c) => $c->filter(
                fn ($commission) => str_contains(
                    mb_strtolower($commission->smeCompany?->name ?? ''),
                    mb_strtolower(trim($this->search))
                )
            ))
            ->when($this->filterPlan !== '', fn ($c) => $c->filter(
                fn ($commission) => ($commission->smeCompany?->subscription?->plan_slug ?? '') === $this->filterPlan
            ))
            ->when($this->filterStatus !== '', fn ($c) => $c->filter(
                fn ($commission) => $commission->status === $this->filterStatus
            ))
            ->values();
    }

    #[Computed]
    public function monthTotal(): int
    {
        if (! $this->firm) {
            return 0;
        }

        return (int) Commission::query()
            ->where('accountant_firm_id', $this->firm->id)
            ->whereYear('period_month', now()->year)
            ->whereMonth('period_month', now()->month)
            ->sum('amount');
    }

    #[Computed]
    public function filteredTotal(): int
    {
        return $this->monthCommissions->sum('amount');
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return $this->search !== '' || $this->filterPlan !== '' || $this->filterStatus !== '';
    }

    /** @return array{all: int, pending: int, paid: int} */
    #[Computed]
    public function statusCounts(): array
    {
        if (! $this->firm) {
            return ['all' => 0, 'pending' => 0, 'paid' => 0];
        }

        $commissions = Commission::query()
            ->where('accountant_firm_id', $this->firm->id)
            ->whereYear('period_month', now()->year)
            ->whereMonth('period_month', now()->month)
            ->get();

        return [
            'all' => $commissions->count(),
            'pending' => $commissions->where('status', 'pending')->count(),
            'paid' => $commissions->where('status', 'paid')->count(),
        ];
    }

    #[Computed]
    public function yearTotal(): int
    {
        if (! $this->firm) {
            return 0;
        }

        return (int) Commission::query()
            ->where('accountant_firm_id', $this->firm->id)
            ->whereYear('period_month', now()->year)
            ->sum('amount');
    }

    #[Computed]
    public function yearMonthsLabel(): string
    {
        $months = [];
        for ($m = 1; $m <= now()->month; $m++) {
            $months[] = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'][$m - 1];
        }

        if (count($months) <= 3) {
            return implode(' + ', $months);
        }

        return $months[0].' → '.$months[count($months) - 1];
    }

    #[Computed]
    public function nextPaymentDate(): string
    {
        return format_date(now()->addMonth()->startOfMonth()->addDays(4), withYear: false);
    }

    /** @return Collection<int, CommissionPayment> */
    #[Computed]
    public function payments(): Collection
    {
        if (! $this->firm) {
            return collect();
        }

        return CommissionPayment::query()
            ->where('accountant_firm_id', $this->firm->id)
            ->orderByDesc('period_month')
            ->limit(12)
            ->get();
    }

    // ─── Actions ──────────────────────────────────────────────────────────

    public function toggleShowAll(): void
    {
        $this->showAllCommissions = ! $this->showAllCommissions;
    }

    public function setFilterStatus(string $status): void
    {
        $this->filterStatus = $status === 'all' ? '' : $status;
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filterPlan = '';
        $this->filterStatus = '';
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- ─── En-tête ──────────────────────────────────────────────────────── --}}
    <section class="app-shell-panel overflow-hidden">
        <div class="flex flex-col gap-4 p-6 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.24em] text-teal">{{ __('Programme Partenaire Fayeku') }}</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-ink">{{ __('Commissions & parrainage') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('Invitez vos clients PME sur Fayeku et recevez une commission sur chaque abonnement actif éligible.') }}</p>
            </div>

            @if ($firm)
                <div class="flex shrink-0 flex-col items-end gap-1">
                    <span @class([
                        'inline-flex items-center gap-1.5 rounded-full px-4 py-1.5 text-sm font-semibold',
                        'bg-primary text-white' => $tierValue === 'partner',
                        'bg-amber-400 text-amber-950' => $tierValue === 'gold',
                        'bg-ink text-accent' => $tierValue === 'platinum',
                    ])>
                        {{ $tierLabel }}
                        @if ($tierValue !== 'partner') ★ @endif
                        · {{ $activeClientsCount }} {{ $activeClientsCount > 1 ? 'clients actifs' : 'client actif' }}
                    </span>
                </div>
            @endif
        </div>
    </section>

    {{-- ─── Statut partenaire ──────────────────────────────────────────── --}}
    <section class="app-shell-panel p-6">
        <x-section-header
            :title="__('Votre niveau partenaire')"
            :subtitle="__('Votre statut évolue selon le nombre de clients référés actifs sur Fayeku.')"
        />

        {{-- Tiers comparison --}}
        <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
            {{-- Partner --}}
            <div @class([
                'rounded-2xl border bg-slate-50 border-slate-200 p-5',
                'ring-2 ring-slate-300' => $tierValue === 'partner',
            ])>
                <p class="text-sm font-semibold text-slate-700">Partner</p>
                <p class="mt-1 text-lg font-bold text-ink">1–4 clients actifs</p>
                @if ($tierValue === 'partner')
                    <span class="mt-2 inline-flex items-center rounded-full bg-primary px-2.5 py-0.5 text-sm font-semibold text-white">{{ __('Niveau actuel') }}</span>
                @endif
                <ul class="mt-3 space-y-1 text-sm text-slate-600">
                    <li>Commission de 15 % sur les abonnements éligibles</li>
                    <li>Accès complet à Fayeku Compta</li>
                </ul>
            </div>

            {{-- Gold --}}
            <div @class([
                'rounded-2xl border bg-amber-50 border-amber-200 p-5',
                'ring-2 ring-amber-300' => $tierValue === 'gold',
            ])>
                <p class="text-sm font-semibold text-amber-700">Gold ★</p>
                <p class="mt-1 text-lg font-bold text-ink">5–14 clients actifs</p>
                @if ($tierValue === 'gold')
                    <span class="mt-2 inline-flex items-center rounded-full bg-amber-400 px-2.5 py-0.5 text-sm font-semibold text-amber-950">{{ __('Niveau actuel') }}</span>
                @endif
                <ul class="mt-3 space-y-1 text-sm text-slate-600">
                    <li class="font-medium text-amber-700">Commission récurrente de 15 %</li>
                    <li>Badge officiel partenaire</li>
                    <li>Leads PME entrants</li>
                    <li>Accès au groupe partenaires</li>
                </ul>
            </div>

            {{-- Platinum --}}
            <div @class([
                'rounded-2xl border bg-sky-50 border-sky-200 p-5',
                'ring-2 ring-sky-300' => $tierValue === 'platinum',
            ])>
                <p class="text-sm font-semibold text-sky-800">Platinum</p>
                <p class="mt-1 text-lg font-bold text-ink">15+ clients actifs</p>
                @if ($tierValue === 'platinum')
                    <span class="mt-2 inline-flex items-center rounded-full bg-ink px-2.5 py-0.5 text-sm font-semibold text-accent">{{ __('Niveau actuel') }}</span>
                @endif
                <ul class="mt-3 space-y-1 text-sm text-slate-600">
                    <li>Tous les avantages Gold</li>
                    <li>Account manager dédié</li>
                    <li>Co-marketing et événements</li>
                    <li>Bonus trimestriel pour les meilleurs prescripteurs</li>
                </ul>
            </div>
        </div>

        {{-- Progress bar --}}
        <div class="mt-5">
            <div class="flex items-center justify-between text-sm">
                <span class="font-medium text-slate-600">
                    {{ $activeClientsCount }} {{ $activeClientsCount > 1 ? 'clients actifs' : 'client actif' }}
                    @if ($isPlatinum)
                        · {{ __('Niveau Platinum atteint') }}
                    @else
                        · Seuil {{ $nextTierLabel }} : {{ $nextThreshold }}
                        @if ($tierProgress >= 100)
                            · {{ __('Déjà éligible') }} ✓
                        @endif
                    @endif
                </span>
            </div>
            <div class="mt-2 h-2.5 w-full overflow-hidden rounded-full bg-slate-100">
                <div
                    @class([
                        'h-full rounded-full transition-all duration-500',
                        'bg-primary' => $tierValue === 'partner',
                        'bg-amber-400' => $tierValue === 'gold',
                        'bg-gradient-to-r from-sky-300 via-teal-300 to-primary' => $tierValue === 'platinum',
                    ])
                    style="width: {{ $tierProgress }}%"
                ></div>
            </div>
        </div>
    </section>

    {{-- ─── KPI Cards ──────────────────────────────────────────────────── --}}
    <section class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-4">

        {{-- Commission du mois --}}
        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-emerald-50">
                    <flux:icon name="banknotes" class="size-5 text-accent" />
                </div>
                <span class="inline-flex whitespace-nowrap items-center rounded-full bg-emerald-50 px-2.5 py-1 text-sm font-semibold text-emerald-700">
                    {{ $currentMonth }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Commission du mois') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-accent">
                {{ format_money($this->monthTotal) }}
            </p>
            <p class="mt-1 text-sm text-slate-500">Versement prévu le {{ $this->nextPaymentDate }} via Wave</p>
        </article>

        {{-- Clients référés actifs --}}
        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-teal-50">
                    <flux:icon name="user-group" class="size-5 text-primary" />
                </div>
                <span class="inline-flex whitespace-nowrap items-center rounded-full bg-slate-100 px-2.5 py-1 text-sm font-medium text-slate-500">
                    {{ __('Portefeuille') }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Clients référés actifs') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-ink">{{ $activeClientsCount }}</p>
        </article>

        {{-- Commissions cumulées --}}
        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-amber-50">
                    <flux:icon name="chart-bar" class="size-5 text-amber-600" />
                </div>
                <span class="inline-flex whitespace-nowrap items-center rounded-full bg-amber-50 px-2.5 py-1 text-sm font-semibold text-amber-700">
                    {{ now()->year }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Commissions cumulées') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-ink">
                {{ format_money($this->yearTotal) }}
            </p>
            <p class="mt-1 text-sm text-slate-500">{{ __('Depuis janvier') }} {{ now()->year }}</p>
        </article>

        {{-- Estimation du mois prochain --}}
        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-sky-50">
                    <flux:icon name="arrow-trending-up" class="size-5 text-sky-600" />
                </div>
                <span class="inline-flex whitespace-nowrap items-center rounded-full bg-sky-50 px-2.5 py-1 text-sm font-medium text-sky-700">
                    {{ __('Prévision') }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Estimation du mois prochain') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-ink">
                ~{{ format_money($this->monthTotal) }}
            </p>
            <p class="mt-1 text-sm text-slate-500">{{ __('Projection basée sur les clients actifs actuels') }}</p>
        </article>
    </section>

    {{-- ─── Détail des commissions ─────────────────────────────────────── --}}
    <section class="app-shell-panel">
        <div class="flex items-center justify-between px-6 pt-6 pb-2">
            <x-section-header
                :title="__('Commissions du mois') . ' · ' . $currentMonth"
                :subtitle="__('Chaque ligne correspond à un client référé éligible à commission.')"
            />
            @if ($this->monthTotal > 0)
                <span class="text-sm font-bold text-accent">
                    @if ($this->hasActiveFilters)
                        {{ __('Total filtré') }} : {{ format_money($this->filteredTotal) }}
                    @else
                        {{ __('Total du mois') }} : {{ format_money($this->monthTotal) }}
                    @endif
                </span>
            @endif
        </div>

        {{-- ─── Filtres ──────────────────────────────────────────────────────── --}}
        <div class="border-t border-slate-100 px-6 py-5">
            <p class="mb-3 text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ __('Filtrer les commissions') }}</p>

            {{-- Onglets statut --}}
            <div class="flex flex-wrap items-center gap-2">
                @foreach ([
                    'all'     => ['label' => 'Tous',       'dot' => null,           'activeClass' => 'bg-primary text-white',     'badgeInactive' => 'bg-slate-100 text-slate-500'],
                    'pending' => ['label' => 'En attente', 'dot' => 'bg-amber-400', 'activeClass' => 'bg-amber-500 text-white',  'badgeInactive' => 'bg-amber-100 text-amber-700'],
                    'paid'    => ['label' => 'Versées',    'dot' => 'bg-accent',    'activeClass' => 'bg-emerald-600 text-white', 'badgeInactive' => 'bg-emerald-100 text-emerald-700'],
                ] as $key => $tab)
                    @php $isActive = ($key === 'all' && $filterStatus === '') || $filterStatus === $key; @endphp
                    <button
                        wire:click="setFilterStatus('{{ $key }}')"
                        @class([
                            'inline-flex items-center gap-1.5 rounded-full px-4 py-1.5 text-sm font-semibold transition',
                            $tab['activeClass']                                                                            => $isActive,
                            'bg-white border border-slate-200 text-slate-600 hover:border-primary/30 hover:text-primary' => ! $isActive,
                        ])
                    >
                        @if ($tab['dot'])
                            <span @class(['size-2 rounded-full', 'bg-white' => $isActive, $tab['dot'] => ! $isActive])></span>
                        @endif
                        {{ $tab['label'] }}
                        <span @class([
                            'rounded-full px-1.5 py-px text-sm font-bold',
                            'bg-white/20 text-white' => $isActive,
                            $tab['badgeInactive']    => ! $isActive,
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
                        placeholder="{{ __('Rechercher un client…') }}"
                        class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 py-3 pl-10 pr-4 text-sm text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                    />
                </div>

                <x-select-native>
                    <select
                        wire:model.live="filterPlan"
                        class="col-start-1 row-start-1 appearance-none rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 pr-8 text-sm text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10 sm:w-48"
                    >
                        <option value="">{{ __('Toutes les offres') }}</option>
                        <option value="essentiel">{{ __('Essentiel') }}</option>
                        <option value="basique">{{ __('Basique') }}</option>
                    </select>
                </x-select-native>
            </div>
        </div>

        @if ($this->monthCommissions->isEmpty())
            <div class="px-6 pb-6">
                <p class="text-sm text-slate-500">
                    @if ($this->hasActiveFilters)
                        {{ __('Aucun résultat pour ces filtres.') }}
                    @else
                        {{ __('Aucune commission ce mois-ci.') }}
                    @endif
                </p>
            </div>
        @else
            @php
                $showAll = $showAllCommissions || $this->hasActiveFilters;
                $visibleCommissions = $showAll
                    ? $this->monthCommissions
                    : $this->monthCommissions->take(3);
                $remainingCount = $this->monthCommissions->count() - 3;
            @endphp

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-t border-slate-100 text-sm font-semibold text-slate-500">
                            <th class="px-6 py-3">{{ __('Client PME') }}</th>
                            <th class="px-6 py-3">{{ __('Offre') }}</th>
                            <th class="px-6 py-3">{{ __('Abonnement') }}</th>
                            <th class="px-6 py-3">{{ __('Taux') }}</th>
                            <th class="px-6 py-3">{{ __('Commission') }}</th>
                            <th class="px-6 py-3">{{ __('Statut') }}</th>
                            <th class="px-6 py-3">{{ __('Source') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($visibleCommissions as $commission)
                            @php
                                $sub = $commission->smeCompany?->subscription;
                                $planSlug = $sub?->plan_slug ?? '—';
                                $price = $sub?->price_paid ?? 0;
                                $rate = 15;
                            @endphp
                            <tr class="transition hover:bg-slate-50/50">
                                <td class="whitespace-nowrap px-6 py-3.5 font-medium text-ink">
                                    {{ $commission->smeCompany?->name ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5">
                                    <span @class([
                                        'rounded-full px-2.5 py-1 text-sm font-semibold',
                                        'bg-emerald-50 text-emerald-700' => strtolower($planSlug) === 'essentiel',
                                        'bg-slate-100 text-slate-600' => strtolower($planSlug) !== 'essentiel',
                                    ])>
                                        {{ ucfirst($planSlug) }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-slate-600">
                                    {{ format_money($price, compact: true) }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-slate-600">{{ $rate }}%</td>
                                <td class="whitespace-nowrap px-6 py-3.5 font-semibold text-accent">
                                    {{ format_money($commission->amount, compact: true) }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5">
                                    @php
                                        $statusLabel = match ($commission->status) {
                                            'paid' => __('Versée'),
                                            'pending' => __('En attente'),
                                            default => ucfirst($commission->status),
                                        };
                                    @endphp
                                    <span @class([
                                        'inline-flex whitespace-nowrap items-center gap-1 rounded-full px-2.5 py-0.5 text-sm font-semibold ring-1 ring-inset',
                                        'bg-emerald-50 text-emerald-700 ring-emerald-600/20' => $commission->status === 'paid',
                                        'bg-amber-50 text-amber-700 ring-amber-600/20' => $commission->status === 'pending',
                                        'bg-slate-100 text-slate-600 ring-slate-500/20' => ! in_array($commission->status, ['paid', 'pending']),
                                    ])>
                                        <span @class([
                                            'size-1.5 rounded-full',
                                            'bg-emerald-500' => $commission->status === 'paid',
                                            'bg-amber-500' => $commission->status === 'pending',
                                            'bg-slate-400' => ! in_array($commission->status, ['paid', 'pending']),
                                        ])></span>
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-slate-500">
                                    {{ __('Lien partenaire') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if (! $this->hasActiveFilters)
                @if (! $showAllCommissions && $remainingCount > 0)
                    <div class="border-t border-slate-100 px-6 py-4 text-center">
                        <button
                            type="button"
                            wire:click="toggleShowAll"
                            class="text-sm font-medium text-slate-500 hover:text-primary"
                        >
                            {{ __('Afficher les') }} {{ $remainingCount }} {{ __('autres clients') }}
                        </button>
                    </div>
                @elseif ($showAllCommissions && $this->monthCommissions->count() > 3)
                    <div class="border-t border-slate-100 px-6 py-4 text-center">
                        <button
                            type="button"
                            wire:click="toggleShowAll"
                            class="text-sm font-medium text-primary underline"
                        >
                            {{ __('Réduire') }}
                        </button>
                    </div>
                @endif
            @endif
        @endif
    </section>

    {{-- ─── Historique des versements ──────────────────────────────────── --}}
    <section class="app-shell-panel">
        <div class="px-6 pt-6 pb-4">
            <x-section-header
                :title="__('Historique des versements')"
                :subtitle="__('Retrouvez ici tous les paiements de commissions effectués sur votre compte.')"
            />
        </div>

        @if ($this->payments->isEmpty())
            <div class="px-6 pb-6">
                <p class="text-sm text-slate-500">{{ __('Aucun versement enregistré pour le moment.') }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ __('Vos prochaines commissions validées apparaîtront ici après paiement.') }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-t border-slate-100 text-sm font-semibold text-slate-500">
                            <th class="px-6 py-3">{{ __('Mois') }}</th>
                            <th class="px-6 py-3">{{ __('Clients actifs') }}</th>
                            <th class="px-6 py-3">{{ __('Montant versé') }}</th>
                            <th class="px-6 py-3">{{ __('Versé le') }}</th>
                            <th class="px-6 py-3">{{ __('Moyen') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($this->payments as $payment)
                            <tr class="transition hover:bg-slate-50/50">
                                <td class="whitespace-nowrap px-6 py-3.5 font-medium text-ink">
                                    {{ format_month($payment->period_month) }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-slate-600">
                                    {{ $payment->active_clients_count }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5 font-semibold text-accent">
                                    {{ format_money($payment->amount, compact: true) }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-slate-600">
                                    {{ format_date($payment->paid_at) }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5">
                                    @if ($payment->status === 'paid')
                                        <span class="inline-flex whitespace-nowrap items-center gap-1 rounded-full px-2.5 py-0.5 text-sm font-semibold ring-1 ring-inset bg-emerald-50 text-emerald-700 ring-emerald-600/20">
                                            <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                            {{ ucfirst($payment->payment_method ?? 'Wave') }} ✓
                                        </span>
                                    @else
                                        <span class="inline-flex whitespace-nowrap items-center gap-1 rounded-full px-2.5 py-0.5 text-sm font-semibold ring-1 ring-inset bg-amber-50 text-amber-700 ring-amber-600/20">
                                            <span class="size-1.5 rounded-full bg-amber-500"></span>
                                            {{ __('À venir') }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

</div>
