<?php

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Auth\Models\Company;
use Modules\Compta\Partnership\Enums\PartnerTier;
use Modules\Compta\Partnership\Models\Commission;
use Modules\Compta\Partnership\Models\CommissionPayment;
use Modules\Compta\Partnership\Models\PartnerInvitation;
use Modules\Compta\Portfolio\Services\PortfolioService;

new #[Title('Commissions')] class extends Component
{
    // ─── Firm / Tier ──────────────────────────────────────────────────────
    public ?Company $firm = null;

    public int $activeClientsCount = 0;

    public string $tierValue = 'partner';

    public string $tierLabel = 'Partner';

    public int $tierProgress = 0;

    public int $nextThreshold = 5;

    public string $tierRangeLabel = '1–4 clients';

    public string $nextTierLabel = 'Gold';

    public bool $isPlatinum = false;

    public string $currentMonth = '';

    // ─── Filtres commissions ───────────────────────────────────────────────
    public bool $showAllCommissions = false;

    public string $commissionSearch = '';

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

    // ─── Computed : Commissions ────────────────────────────────────────────

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
            ->when($this->commissionSearch !== '', fn ($c) => $c->filter(
                fn ($commission) => str_contains(
                    mb_strtolower($commission->smeCompany?->name ?? ''),
                    mb_strtolower(trim($this->commissionSearch))
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
        return $this->commissionSearch !== '' || $this->filterPlan !== '' || $this->filterStatus !== '';
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

    // ─── Computed : Pont vers Invitations ─────────────────────────────────

    #[Computed]
    public function pendingInvitationsCount(): int
    {
        if (! $this->firm) {
            return 0;
        }

        return PartnerInvitation::query()
            ->where('accountant_firm_id', $this->firm->id)
            ->where('status', '!=', 'accepted')
            ->where('status', '!=', 'expired')
            ->count();
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
        $this->commissionSearch = '';
        $this->filterPlan = '';
        $this->filterStatus = '';
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- Bloc 1. Hero                                                        --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <section class="app-shell-panel overflow-hidden">
        <div class="flex flex-col gap-5 p-6 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex-1">
                <p class="text-sm font-semibold uppercase tracking-[0.24em] text-teal">{{ __('Programme Partenaire Fayeku') }}</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-ink">{{ __('Commissions & partenariat') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('Invitez vos clients PME sur Fayeku et suivez les revenus générés par votre programme partenaire.') }}</p>
                <p class="mt-3 inline-flex items-center gap-2 rounded-xl border border-teal/20 bg-teal/5 px-3.5 py-2 text-sm font-medium text-teal">
                    <flux:icon name="information-circle" class="size-4 shrink-0" />
                    {{ __('Vous recevez une prime sur la première souscription de chaque PME activée, puis une commission récurrente de 15 % sur les abonnements éligibles.') }}
                </p>
            </div>

            <div class="flex shrink-0 flex-col items-start gap-3 lg:items-end">
                @if ($firm)
                    <span @class([
                        'inline-flex items-center gap-1.5 rounded-full px-4 py-1.5 text-sm font-semibold',
                        'bg-primary text-white' => $tierValue === 'partner',
                        'bg-amber-400 text-amber-950' => $tierValue === 'gold',
                        'bg-ink text-accent' => $tierValue === 'platinum',
                    ])>
                        @if ($tierValue !== 'partner') ★ @endif
                        {{ $tierLabel }}
                        · {{ $activeClientsCount }} {{ $activeClientsCount > 1 ? 'clients actifs' : 'client actif' }}
                    </span>
                @endif

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        x-data="{ link: '{{ $firm?->invite_code ? route('join.landing', ['code' => $firm->invite_code]) : '' }}' }"
                        x-on:click="navigator.clipboard.writeText(link).then(() => $dispatch('toast', { type: 'success', title: 'Lien copié dans le presse-papiers !' }))"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-600 shadow-sm transition hover:bg-slate-50"
                    >
                        <flux:icon name="link" class="size-4" />
                        {{ __('Copier mon lien') }}
                    </button>
                    <button
                        type="button"
                        wire:click="$dispatch('open-invite-pme')"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-primary/20 bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-[0_8px_24px_rgba(2,77,77,0.18)] transition hover:bg-primary/90"
                    >
                        <flux:icon name="plus" class="size-4" />
                        {{ __('Inviter une PME') }}
                    </button>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- Bloc 2. Niveau partenaire                                          --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <section class="app-shell-panel p-6">
        <x-section-header
            :title="__('Votre niveau partenaire')"
            :subtitle="__('Votre statut évolue selon le nombre de PME actives référées sur Fayeku.')"
        />

        <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-3">

            {{-- Partner --}}
            <div @class([
                'rounded-2xl border p-5 transition',
                'border-slate-200 bg-slate-50' => $tierValue !== 'partner',
                'border-primary/30 bg-primary/5 ring-2 ring-primary/20' => $tierValue === 'partner',
            ])>
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-slate-700">Partner</p>
                    @if ($tierValue === 'partner')
                        <span class="inline-flex items-center rounded-full bg-primary px-2.5 py-0.5 text-xs font-semibold text-white">{{ __('Niveau actuel') }}</span>
                    @endif
                </div>
                <p class="mt-1 text-base font-bold text-ink">1–4 clients actifs</p>
                <ul class="mt-3 space-y-1.5 text-sm text-slate-600">
                    <li class="flex items-start gap-1.5"><span class="mt-0.5 text-primary">✓</span> {{ __('Commission récurrente de 15 %') }}</li>
                    <li class="flex items-start gap-1.5"><span class="mt-0.5 text-primary">✓</span> {{ __('Accès complet à Fayeku Compta') }}</li>
                </ul>
            </div>

            {{-- Gold --}}
            <div @class([
                'rounded-2xl border p-5 transition',
                'border-amber-200 bg-amber-50' => $tierValue !== 'gold',
                'border-amber-300 bg-amber-50 ring-2 ring-amber-300' => $tierValue === 'gold',
            ])>
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-amber-700">Gold ★</p>
                    @if ($tierValue === 'gold')
                        <span class="inline-flex items-center rounded-full bg-amber-400 px-2.5 py-0.5 text-xs font-semibold text-amber-950">{{ __('Niveau actuel') }}</span>
                    @endif
                </div>
                <p class="mt-1 text-base font-bold text-ink">5–14 clients actifs</p>
                <ul class="mt-3 space-y-1.5 text-sm text-slate-600">
                    <li class="flex items-start gap-1.5"><span class="mt-0.5 text-amber-600">✓</span> {{ __('Tous les avantages Partner') }}</li>
                    <li class="flex items-start gap-1.5"><span class="mt-0.5 text-amber-600">✓</span> {{ __('Badge officiel partenaire') }}</li>
                    <li class="flex items-start gap-1.5"><span class="mt-0.5 text-amber-600">✓</span> {{ __('Accès anticipé aux nouveautés') }}</li>
                </ul>
            </div>

            {{-- Platinum --}}
            <div @class([
                'rounded-2xl border p-5 transition',
                'border-sky-200 bg-sky-50' => $tierValue !== 'platinum',
                'border-sky-300 bg-sky-50 ring-2 ring-sky-300' => $tierValue === 'platinum',
            ])>
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-sky-800">Platinum</p>
                    @if ($tierValue === 'platinum')
                        <span class="inline-flex items-center rounded-full bg-ink px-2.5 py-0.5 text-xs font-semibold text-accent">{{ __('Niveau actuel') }}</span>
                    @endif
                </div>
                <p class="mt-1 text-base font-bold text-ink">15+ clients actifs</p>
                <ul class="mt-3 space-y-1.5 text-sm text-slate-600">
                    <li class="flex items-start gap-1.5"><span class="mt-0.5 text-sky-600">✓</span> {{ __('Tous les avantages Gold') }}</li>
                    <li class="flex items-start gap-1.5"><span class="mt-0.5 text-sky-600">✓</span> {{ __('Account manager dédié') }}</li>
                    <li class="flex items-start gap-1.5"><span class="mt-0.5 text-sky-600">✓</span> {{ __('Co-marketing et événements') }}</li>
                    <li class="flex items-start gap-1.5"><span class="mt-0.5 text-sky-600">✓</span> {{ __('Bonus trimestriel meilleurs prescripteurs') }}</li>
                </ul>
            </div>
        </div>

        {{-- Barre de progression --}}
        <div class="mt-5">
            <div class="flex items-center justify-between text-sm">
                <span class="font-medium text-slate-600">
                    {{ $activeClientsCount }} {{ $activeClientsCount > 1 ? __('clients actifs') : __('client actif') }}
                </span>
                <span @class([
                    'text-sm font-semibold',
                    'text-primary' => $tierValue === 'partner',
                    'text-amber-700' => $tierValue === 'gold',
                    'text-sky-700' => $tierValue === 'platinum',
                ])>
                    @if ($isPlatinum)
                        {{ __('Niveau Platinum atteint !') }}
                    @else
                        @php $remaining = $nextThreshold - $activeClientsCount; @endphp
                        {{ __('Encore') }} {{ $remaining }} {{ $remaining > 1 ? __('PME actives') : __('PME active') }} {{ __('pour atteindre le niveau') }} {{ $nextTierLabel }}
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

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- Bloc 3. KPI revenus                                                --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <section class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-4">

        {{-- Commission du mois --}}
        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-emerald-50">
                    <flux:icon name="banknotes" class="size-5 text-accent" />
                </div>
                <span class="inline-flex items-center whitespace-nowrap rounded-full bg-emerald-50 px-2.5 py-1 text-sm font-semibold text-emerald-700">
                    {{ $currentMonth }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Commission du mois') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-accent">
                {{ format_money($this->monthTotal) }}
            </p>
            <p class="mt-1 text-sm text-slate-500">{{ __('Versement prévu le') }} {{ $this->nextPaymentDate }} {{ __('via Wave') }}</p>
        </article>

        {{-- PME actives référées --}}
        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-teal-50">
                    <flux:icon name="user-group" class="size-5 text-primary" />
                </div>
                <span class="inline-flex items-center whitespace-nowrap rounded-full bg-slate-100 px-2.5 py-1 text-sm font-medium text-slate-500">
                    {{ __('Portefeuille') }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('PME actives référées') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-ink">{{ $activeClientsCount }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ __('Portefeuille partenaire actif') }}</p>
        </article>

        {{-- Commissions cumulées --}}
        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-amber-50">
                    <flux:icon name="chart-bar" class="size-5 text-amber-600" />
                </div>
                <span class="inline-flex items-center whitespace-nowrap rounded-full bg-amber-50 px-2.5 py-1 text-sm font-semibold text-amber-700">
                    {{ now()->year }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Commissions cumulées') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-ink">
                {{ format_money($this->yearTotal) }}
            </p>
            <p class="mt-1 text-sm text-slate-500">{{ __('Depuis janvier') }} {{ now()->year }}</p>
        </article>

        {{-- Projection mois prochain --}}
        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-sky-50">
                    <flux:icon name="arrow-trending-up" class="size-5 text-sky-600" />
                </div>
                <span class="inline-flex items-center whitespace-nowrap rounded-full bg-sky-50 px-2.5 py-1 text-sm font-medium text-sky-700">
                    {{ __('Prévision') }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Projection mois prochain') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-ink">
                ~{{ format_money($this->monthTotal) }}
            </p>
            <p class="mt-1 text-sm text-slate-500">{{ __('Estimation basée sur vos clients actifs actuels') }}</p>
        </article>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- Bloc 4. Pont vers Invitations                                      --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    @if ($this->pendingInvitationsCount > 0)
        <section class="app-shell-panel overflow-hidden">
            <div class="flex items-center gap-4 p-5">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-amber-100">
                    <flux:icon name="clock" class="size-5 text-amber-600" />
                </div>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-ink">
                        {{ __('Invitations en attente') }} · {{ $this->pendingInvitationsCount }} {{ $this->pendingInvitationsCount > 1 ? __('PME') : __('PME') }}
                    </p>
                    <p class="mt-0.5 text-sm text-slate-500">
                        {{ __("Certaines invitations n'ont pas encore été activées. Relancez-les pour accélérer votre portefeuille partenaire.") }}
                    </p>
                </div>
                <a
                    href="{{ route('invitations.index') }}"
                    wire:navigate
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-700 transition hover:bg-amber-100"
                >
                    {{ __('Voir mes invitations') }}
                    <flux:icon name="arrow-right" class="size-4" />
                </a>
            </div>
        </section>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- Bloc 5. Commissions du mois                                        --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <section class="app-shell-panel">
        <div class="flex items-center justify-between px-6 pt-6 pb-2">
            <x-section-header
                :title="__('Commissions du mois') . ' · ' . $currentMonth"
                :subtitle="__('Chaque ligne correspond à une PME référée éligible à commission pour la période sélectionnée.')"
            />
            @if ($this->monthTotal > 0)
                <span class="shrink-0 text-sm font-bold text-accent">
                    @if ($this->hasActiveFilters)
                        {{ __('Total filtré') }} : {{ format_money($this->filteredTotal) }}
                    @else
                        {{ __('Total du mois') }} : {{ format_money($this->monthTotal) }}
                    @endif
                </span>
            @endif
        </div>

        {{-- Filtres --}}
        <div class="border-t border-slate-100 px-6 py-5">
            <p class="mb-3 text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ __('Filtrer les commissions') }}</p>

            <div class="flex flex-wrap items-center gap-2">
                @foreach ([
                    'all'     => ['label' => 'Tous',       'dot' => null,           'activeClass' => 'bg-primary text-white',     'badgeInactive' => 'bg-slate-100 text-slate-500'],
                    'pending' => ['label' => 'En attente', 'dot' => 'bg-amber-400', 'activeClass' => 'bg-amber-500 text-white',   'badgeInactive' => 'bg-amber-100 text-amber-700'],
                    'paid'    => ['label' => 'Versées',    'dot' => 'bg-accent',    'activeClass' => 'bg-emerald-600 text-white', 'badgeInactive' => 'bg-emerald-100 text-emerald-700'],
                ] as $key => $tab)
                    @php $isActive = ($key === 'all' && $filterStatus === '') || $filterStatus === $key; @endphp
                    <x-ui.filter-chip
                        wire:click="setFilterStatus('{{ $key }}')"
                        :label="$tab['label']"
                        :dot="$tab['dot']"
                        :active="$isActive"
                        :activeClass="$tab['activeClass']"
                        :badgeInactive="$tab['badgeInactive']"
                        :count="$this->statusCounts[$key]"
                    />
                @endforeach
            </div>

            <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                <div class="relative flex-1">
                    <svg class="absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                    <input
                        wire:model.live.debounce.300ms="commissionSearch"
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
                            <th class="px-6 py-3">{{ __('Action') }}</th>
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
                                            'pending' => __('En attente de versement'),
                                            default => ucfirst($commission->status),
                                        };
                                    @endphp
                                    <span @class([
                                        'inline-flex items-center whitespace-nowrap gap-1 rounded-full px-2.5 py-0.5 text-sm font-semibold ring-1 ring-inset',
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
                                <td class="whitespace-nowrap px-6 py-3.5">
                                    @if ($commission->sme_company_id)
                                        <a
                                            href="{{ route('clients.show', $commission->sme_company_id) }}"
                                            wire:navigate
                                            class="text-sm font-medium text-primary hover:underline"
                                        >
                                            {{ __('Voir le client') }}
                                        </a>
                                    @endif
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

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- Bloc 6. Historique des versements                                  --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
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
                            <th class="px-6 py-3">{{ __('Statut') }}</th>
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
                                    {{ $payment->paid_at ? format_date($payment->paid_at) : '—' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-slate-600">
                                    {{ $payment->status === 'paid' ? ucfirst($payment->payment_method ?? 'Wave') : '—' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5">
                                    @if ($payment->status === 'paid')
                                        <span class="inline-flex items-center whitespace-nowrap gap-1 rounded-full px-2.5 py-0.5 text-sm font-semibold ring-1 ring-inset bg-emerald-50 text-emerald-700 ring-emerald-600/20">
                                            <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                            {{ __('Versé') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center whitespace-nowrap gap-1 rounded-full px-2.5 py-0.5 text-sm font-semibold ring-1 ring-inset bg-amber-50 text-amber-700 ring-amber-600/20">
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

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- Bloc 7. FAQ programme partenaire                                   --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <section class="app-shell-panel p-6" x-data="{ openItem: null }">
        <x-section-header
            :title="__('Comment fonctionne le programme partenaire ?')"
            :subtitle="__('Retrouvez les réponses aux questions les plus fréquentes sur le programme de commissions.')"
        />

        @php
            $faqs = [
                ['q' => "Qu'est-ce qu'une PME référée ?",         'a' => "Une PME référée est un client invité par votre cabinet via Fayeku et ayant activé un abonnement éligible."],
                ['q' => 'Quand ma commission est-elle validée ?',  'a' => "Votre commission est validée une fois la souscription confirmée et le paiement encaissé."],
                ['q' => 'Quand suis-je payé ?',                    'a' => "Les commissions validées sont versées selon le calendrier défini par Fayeku, par exemple chaque début de mois via Wave."],
                ['q' => 'Que signifie "15 % à vie" ?',            'a' => "Votre cabinet reçoit 15 % des abonnements éligibles tant que la PME référée reste cliente active sur Fayeku."],
                ['q' => "Que se passe-t-il en cas d'annulation ?", 'a' => "En cas d'annulation ou de non-paiement, les commissions futures associées ne sont plus générées."],
            ];
        @endphp

        <div class="mt-5 divide-y divide-slate-100 rounded-2xl border border-slate-200">
            @foreach ($faqs as $i => $faq)
                <div>
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-5 py-4 text-left transition hover:bg-slate-50"
                        x-on:click="openItem = openItem === {{ $i }} ? null : {{ $i }}"
                    >
                        <span class="text-sm font-semibold text-ink">{{ $faq['q'] }}</span>
                        <flux:icon
                            name="chevron-down"
                            class="size-4 shrink-0 text-slate-400 transition-transform duration-200"
                            x-bind:class="openItem === {{ $i }} ? 'rotate-180' : ''"
                        />
                    </button>
                    <div
                        x-show="openItem === {{ $i }}"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 -translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-cloak
                        class="px-5 pb-4 text-sm text-slate-600"
                    >
                        {{ $faq['a'] }}
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ─── Modale : Inviter une PME ───────────────────────────────────────── --}}
    <livewire:invite-pme-modal />

</div>
