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

new #[Title('Commissions')] class extends Component {
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

    public function mount(): void
    {
        $this->currentMonth = ucfirst(now()->locale('fr_FR')->translatedFormat('F Y'));
        $this->firm = auth()->user()->companies()
            ->where('type', 'accountant_firm')
            ->first();

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
            ->get();
    }

    #[Computed]
    public function monthTotal(): int
    {
        return $this->monthCommissions->sum('amount');
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
            $months[] = ucfirst(now()->setMonth($m)->locale('fr_FR')->translatedFormat('M'));
        }

        if (count($months) <= 3) {
            return implode(' + ', $months);
        }

        return $months[0].' → '.$months[count($months) - 1];
    }

    #[Computed]
    public function nextPaymentDate(): string
    {
        return now()->locale('fr_FR')->addMonth()->startOfMonth()->addDays(4)->translatedFormat('j F');
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
        <h3 class="text-lg font-bold text-ink">{{ __('Votre niveau partenaire') }}</h3>
        <p class="mt-1 text-sm text-slate-500">{{ __('Votre statut évolue selon le nombre de clients référés actifs sur Fayeku.') }}</p>

        {{-- Tiers comparison --}}
        <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
            {{-- Partner --}}
            <div @class([
                'rounded-2xl border bg-slate-50 border-slate-200 p-5',
                'ring-2 ring-slate-300' => $tierValue === 'partner',
            ])>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-700">Partner</p>
                <p class="mt-1 text-lg font-bold text-ink">1–4 clients actifs</p>
                @if ($tierValue === 'partner')
                    <span class="mt-2 inline-flex items-center rounded-full bg-primary px-2.5 py-0.5 text-xs font-semibold text-white">{{ __('Niveau actuel') }}</span>
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
                <p class="text-xs font-semibold uppercase tracking-wider text-amber-700">Gold ★</p>
                <p class="mt-1 text-lg font-bold text-ink">5–14 clients actifs</p>
                @if ($tierValue === 'gold')
                    <span class="mt-2 inline-flex items-center rounded-full bg-amber-400 px-2.5 py-0.5 text-xs font-semibold text-amber-950">{{ __('Niveau actuel') }}</span>
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
                <p class="text-xs font-semibold uppercase tracking-wider text-sky-800">Platinum</p>
                <p class="mt-1 text-lg font-bold text-ink">15+ clients actifs</p>
                @if ($tierValue === 'platinum')
                    <span class="mt-2 inline-flex items-center rounded-full bg-ink px-2.5 py-0.5 text-xs font-semibold text-accent">{{ __('Niveau actuel') }}</span>
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
                        'bg-gradient-to-r from-amber-400 to-primary' => $tierValue === 'platinum',
                    ])
                    style="width: {{ $tierProgress }}%"
                ></div>
            </div>
        </div>
    </section>

    {{-- ─── KPI Cards ──────────────────────────────────────────────────── --}}
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">

        {{-- Commission du mois --}}
        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-emerald-50">
                    <flux:icon name="banknotes" class="size-5 text-accent" />
                </div>
                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                    {{ $currentMonth }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Commission du mois') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-accent">
                {{ number_format($this->monthTotal, 0, ',', ' ') }} FCFA
            </p>
            <p class="mt-1 text-xs text-slate-400">Versement prévu le {{ $this->nextPaymentDate }} via Wave</p>
        </article>

        {{-- Clients référés actifs --}}
        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-teal-50">
                    <flux:icon name="user-group" class="size-5 text-primary" />
                </div>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-500">
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
                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
                    {{ now()->year }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Commissions cumulées') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-ink">
                {{ number_format($this->yearTotal, 0, ',', ' ') }} FCFA
            </p>
            <p class="mt-1 text-xs text-slate-400">{{ __('Depuis janvier') }} {{ now()->year }}</p>
        </article>

        {{-- Estimation du mois prochain --}}
        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-sky-50">
                    <flux:icon name="arrow-trending-up" class="size-5 text-sky-600" />
                </div>
                <span class="inline-flex items-center rounded-full bg-sky-50 px-2.5 py-1 text-xs font-medium text-sky-700">
                    {{ __('Prévision') }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Estimation du mois prochain') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-ink">
                ~{{ number_format($this->monthTotal, 0, ',', ' ') }} FCFA
            </p>
            <p class="mt-1 text-xs text-slate-400">{{ __('Projection basée sur les clients actifs actuels') }}</p>
        </article>
    </section>

    {{-- ─── Détail des commissions ─────────────────────────────────────── --}}
    <section class="app-shell-panel">
        <div class="flex items-center justify-between px-6 pt-6 pb-2">
            <div>
                <h3 class="text-lg font-bold text-ink">
                    {{ __('Commissions du mois') }} · {{ $currentMonth }}
                </h3>
                <p class="mt-0.5 text-xs text-slate-400">{{ __('Chaque ligne correspond à un client référé éligible à commission.') }}</p>
            </div>
            @if ($this->monthTotal > 0)
                <span class="text-sm font-bold text-accent">
                    Total du mois : {{ number_format($this->monthTotal, 0, ',', ' ') }} FCFA
                </span>
            @endif
        </div>

        @if ($this->monthCommissions->isEmpty())
            <div class="px-6 pb-6">
                <p class="text-sm text-slate-400">{{ __('Aucune commission ce mois-ci.') }}</p>
            </div>
        @else
            @php
                $visibleCommissions = $showAllCommissions
                    ? $this->monthCommissions
                    : $this->monthCommissions->take(3);
                $remainingCount = $this->monthCommissions->count() - 3;
            @endphp

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-t border-slate-100 text-xs font-semibold uppercase tracking-wider text-slate-400">
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
                                        'rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-emerald-50 text-emerald-700' => strtolower($planSlug) === 'essentiel',
                                        'bg-slate-100 text-slate-600' => strtolower($planSlug) !== 'essentiel',
                                    ])>
                                        {{ ucfirst($planSlug) }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-slate-600">
                                    {{ number_format($price, 0, ',', ' ') }} FCFA
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-slate-600">{{ $rate }}%</td>
                                <td class="whitespace-nowrap px-6 py-3.5 font-semibold text-accent">
                                    {{ number_format($commission->amount, 0, ',', ' ') }} FCFA
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
                                        'rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-emerald-50 text-emerald-700' => $commission->status === 'paid',
                                        'bg-amber-50 text-amber-700' => $commission->status === 'pending',
                                        'bg-slate-100 text-slate-600' => ! in_array($commission->status, ['paid', 'pending']),
                                    ])>
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
    </section>

    {{-- ─── Historique des versements ──────────────────────────────────── --}}
    <section class="app-shell-panel">
        <div class="px-6 pt-6 pb-4">
            <h3 class="text-lg font-bold text-ink">{{ __('Historique des versements') }}</h3>
            <p class="mt-0.5 text-xs text-slate-400">{{ __('Retrouvez ici tous les paiements de commissions effectués sur votre compte.') }}</p>
        </div>

        @if ($this->payments->isEmpty())
            <div class="px-6 pb-6">
                <p class="text-sm text-slate-400">{{ __('Aucun versement enregistré pour le moment.') }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ __('Vos prochaines commissions validées apparaîtront ici après paiement.') }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-t border-slate-100 text-xs font-semibold uppercase tracking-wider text-slate-400">
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
                                    {{ ucfirst($payment->period_month->locale('fr_FR')->translatedFormat('M Y')) }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-slate-600">
                                    {{ $payment->active_clients_count }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5 font-semibold text-accent">
                                    {{ number_format($payment->amount, 0, ',', ' ') }} FCFA
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-slate-600">
                                    {{ $payment->paid_at?->locale('fr_FR')->translatedFormat('j M') ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5">
                                    @if ($payment->status === 'paid')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                            {{ ucfirst($payment->payment_method ?? 'Wave') }} ✓
                                        </span>
                                    @else
                                        <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
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
