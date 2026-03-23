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
                <p class="text-sm font-semibold uppercase tracking-[0.24em] text-teal">{{ __('Programme Partenaire') }}</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-ink">{{ __('Commissions & Invitations') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('Gérez vos revenus partenaires et invitez vos clients PME') }}</p>
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
        <h3 class="text-lg font-bold text-ink">{{ __('Statut partenaire') }}</h3>

        @if (! $isPlatinum)
            <p class="mt-1 text-sm text-slate-500">
                {{ __('Éligible') }} {{ $nextTierLabel }} {{ __('ce mois') }}
                @if ($tierProgress >= 100) ✓ @endif
            </p>
        @endif

        {{-- Tiers comparison --}}
        <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
            {{-- Partner --}}
            <div @class([
                'rounded-2xl border p-5',
                'border-primary/30 bg-primary/5' => $tierValue === 'partner',
                'border-slate-200' => $tierValue !== 'partner',
            ])>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Partner</p>
                <p class="mt-1 text-lg font-bold text-ink">1–4 clients actifs</p>
                @if ($tierValue === 'partner')
                    <span class="mt-2 inline-flex items-center rounded-full bg-primary px-2.5 py-0.5 text-xs font-semibold text-white">Actuel</span>
                @endif
                <ul class="mt-3 space-y-1 text-sm text-slate-600">
                    <li>Commission 15%</li>
                    <li>Accès Compta complet</li>
                </ul>
            </div>

            {{-- Gold --}}
            <div @class([
                'rounded-2xl border p-5',
                'border-amber-300 bg-amber-50/50' => $tierValue === 'gold',
                'border-slate-200' => $tierValue !== 'gold',
            ])>
                <div class="flex items-center gap-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-amber-600">Gold ★</p>
                    @if ($tierValue === 'gold')
                        <span class="text-xs font-medium text-amber-600">· Actuel</span>
                    @endif
                </div>
                <p class="mt-1 text-lg font-bold text-ink">5–14 clients actifs</p>
                @if ($tierValue === 'gold')
                    <span class="mt-2 inline-flex items-center rounded-full bg-amber-400 px-2.5 py-0.5 text-xs font-semibold text-amber-950">Actuel</span>
                @elseif ($tierValue === 'partner')
                    <span class="mt-2 inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-500">Prochain</span>
                @endif
                <ul class="mt-3 space-y-1 text-sm text-slate-600">
                    <li class="font-medium text-amber-700">Commission 15% récurrente</li>
                    <li>Badge officiel + leads PME entrants</li>
                    <li>Groupe WhatsApp Partners</li>
                </ul>
            </div>

            {{-- Platinum --}}
            <div @class([
                'rounded-2xl border p-5',
                'border-slate-700 bg-slate-50' => $tierValue === 'platinum',
                'border-slate-200' => $tierValue !== 'platinum',
            ])>
                <div class="flex items-center gap-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Platinum</p>
                    @if ($tierValue === 'platinum')
                        <span class="text-xs font-medium text-slate-600">· Actuel</span>
                    @elseif ($tierValue === 'gold')
                        <span class="text-xs font-medium text-slate-400">· Prochain</span>
                    @endif
                </div>
                <p class="mt-1 text-lg font-bold text-ink">15+ clients actifs</p>
                @if ($tierValue === 'platinum')
                    <span class="mt-2 inline-flex items-center rounded-full bg-ink px-2.5 py-0.5 text-xs font-semibold text-accent">Actuel</span>
                @endif
                <ul class="mt-3 space-y-1 text-sm text-slate-600">
                    <li>Tout Gold +</li>
                    <li>Account manager dédié</li>
                    <li>Co-marketing + événements</li>
                    <li>Bonus trimestriel top prescripteur</li>
                </ul>
            </div>
        </div>

        {{-- Progress bar --}}
        <div class="mt-5">
            <div class="flex items-center justify-between text-sm">
                <span class="font-medium text-slate-600">
                    {{ $activeClientsCount }} {{ $activeClientsCount > 1 ? 'clients actifs' : 'client actif' }}
                    · Seuil {{ $nextTierLabel ?: 'max' }} : {{ $nextThreshold }}
                    @if ($tierProgress >= 100 && ! $isPlatinum)
                        · {{ __('Déjà éligible') }} ✓
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
        <article class="app-shell-stat-card border-l-4 border-l-accent">
            <p class="text-sm font-medium text-slate-500">Commission {{ strtolower($currentMonth) }}</p>
            <p class="mt-2 text-3xl font-bold tracking-tight text-ink">
                {{ number_format($this->monthTotal, 0, ',', ' ') }} F
            </p>
            <p class="mt-1 text-xs text-slate-400">Versement Wave · {{ $this->nextPaymentDate }}</p>
        </article>

        {{-- Clients actifs référés --}}
        <article class="app-shell-stat-card">
            <p class="text-sm font-medium text-slate-500">{{ __('Clients actifs référés') }}</p>
            <p class="mt-2 text-3xl font-bold tracking-tight text-ink">{{ $activeClientsCount }}</p>
            @if ($pendingClientsCount > 0)
                <p class="mt-1 text-xs text-slate-400">{{ $pendingClientsCount }} {{ __('en attente') }}</p>
            @endif
        </article>

        {{-- Cumul annuel --}}
        <article class="app-shell-stat-card">
            <p class="text-sm font-medium text-slate-500">Cumul {{ now()->year }}</p>
            <p class="mt-2 text-3xl font-bold tracking-tight text-ink">
                {{ number_format($this->yearTotal, 0, ',', ' ') }} F
            </p>
            <p class="mt-1 text-xs text-slate-400">{{ $this->yearMonthsLabel }}</p>
        </article>

        {{-- Prévision prochain mois --}}
        <article class="app-shell-stat-card">
            <p class="text-sm font-medium text-slate-500">Prévision {{ now()->locale('fr_FR')->addMonth()->translatedFormat('F') }}</p>
            <p class="mt-2 text-3xl font-bold tracking-tight text-ink">
                ~{{ number_format($this->monthTotal, 0, ',', ' ') }} F
            </p>
            <p class="mt-1 text-xs text-slate-400">
                @if ($pendingClientsCount > 0)
                    Si {{ $pendingClientsCount }} activations
                @else
                    {{ __('Base actuelle') }}
                @endif
            </p>
        </article>
    </section>

    {{-- ─── Détail des commissions ─────────────────────────────────────── --}}
    <section class="app-shell-panel">
        <div class="flex items-center justify-between px-6 pt-6 pb-4">
            <h3 class="text-lg font-bold text-ink">
                {{ __('Détail des commissions') }} · {{ $currentMonth }}
            </h3>
            @if ($this->monthTotal > 0)
                <span class="text-sm font-bold text-accent">
                    Total : {{ number_format($this->monthTotal, 0, ',', ' ') }} F
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
                            <th class="px-6 py-3">{{ __('Plan') }}</th>
                            <th class="px-6 py-3">{{ __('Abonnement mensuel') }}</th>
                            <th class="px-6 py-3">{{ __('Taux commission') }}</th>
                            <th class="px-6 py-3">{{ __('Commission') }}</th>
                            <th class="px-6 py-3">{{ __('Statut client') }}</th>
                            <th class="px-6 py-3">{{ __('Inscrit via') }}</th>
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
                                    {{ number_format($price, 0, ',', ' ') }} F
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-slate-600">{{ $rate }}%</td>
                                <td class="whitespace-nowrap px-6 py-3.5 font-semibold text-accent">
                                    {{ number_format($commission->amount, 0, ',', ' ') }} F
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5">
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                        {{ ucfirst($commission->status) }}
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
                        + {{ $remainingCount }} {{ __('autres clients') }} · <span class="text-primary underline">{{ __('Afficher tout') }}</span>
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
        </div>

        @if ($this->payments->isEmpty())
            <div class="px-6 pb-6">
                <p class="text-sm text-slate-400">{{ __('Aucun versement enregistré.') }}</p>
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
                                    {{ number_format($payment->amount, 0, ',', ' ') }} F
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
