<?php

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Auth\Models\Company;
use Modules\Compta\Partnership\Enums\PartnerTier;
use Modules\Compta\Partnership\Models\Commission;
use Modules\Compta\Portfolio\Models\DismissedAlert;
use Modules\Compta\Portfolio\Services\AlertService;
use Modules\Compta\Portfolio\Services\PortfolioService;
use Modules\PME\Invoicing\Enums\InvoiceStatus;
use Modules\PME\Invoicing\Models\Invoice;

new #[Title('Dashboard')] class extends Component
{
    public ?Company $firm = null;

    public string $heroSummary = '';

    public int $activeClientsCount = 0;

    public int $upToDateCount = 0;

    public int $watchCount = 0;

    public int $criticalCount = 0;

    public int $commissionAmount = 0;

    public string $nextPaymentDate = '';

    public string $tierValue = 'partner';

    public string $tierLabel = 'Partner';

    public int $tierProgress = 0;

    public int $nextThreshold = 5;

    public string $tierRangeLabel = '1–4 clients actifs';

    public string $nextTierLabel = 'Gold';

    public bool $isPlatinum = false;

    /** @var array<int, array<string, mixed>> */
    public array $portfolio = [];

    public string $currentMonth = '';

    public function mount(): void
    {
        $this->currentMonth = format_month(now());
        $this->firm = auth()->user()->accountantFirm();

        if (! $this->firm) {
            return;
        }

        $smeIds = app(PortfolioService::class)->activeSmeIds($this->firm);

        $this->activeClientsCount = $smeIds->count();

        $allInvoices = Invoice::query()
            ->whereIn('company_id', $smeIds)
            ->get()
            ->groupBy('company_id');

        $portfolioService = app(PortfolioService::class);
        $criticalIds = [];
        $watchIds = [];

        foreach ($smeIds as $smeId) {
            $status = $portfolioService->clientStatus($allInvoices->get($smeId, collect()));

            if ($status === 'critical') {
                $criticalIds[] = $smeId;
            } elseif ($status === 'watch') {
                $watchIds[] = $smeId;
            }
        }

        $this->criticalCount = count($criticalIds);
        $this->watchCount = count($watchIds);
        $this->upToDateCount = $this->activeClientsCount - $this->criticalCount - $this->watchCount;

        $this->commissionAmount = Commission::query()
            ->where('accountant_firm_id', $this->firm->id)
            ->whereYear('period_month', now()->year)
            ->whereMonth('period_month', now()->month)
            ->sum('amount');

        $this->nextPaymentDate = format_date(now()->addMonth()->startOfMonth()->addDays(4), withYear: false);

        $tier = PartnerTier::fromActiveClients($this->activeClientsCount);
        $this->tierValue = $tier->value;
        $this->tierLabel = match ($tier) {
            PartnerTier::Partner => 'Partner',
            PartnerTier::Gold => 'Gold',
            PartnerTier::Platinum => 'Platinum',
        };
        $this->isPlatinum = $tier === PartnerTier::Platinum;

        [$this->tierProgress, $this->nextThreshold, $this->tierRangeLabel, $this->nextTierLabel] = $this->computeTierProgress($tier);

        $this->portfolio = $this->buildPortfolio($smeIds, $allInvoices);

        $criticalSummary = match ($this->criticalCount) {
            0 => 'Aucun impayé critique à traiter',
            1 => '1 impayé critique à traiter',
            default => $this->criticalCount.' impayés critiques à traiter',
        };

        $this->heroSummary = $this->currentMonth.' · '.$criticalSummary;

        if ($this->nextPaymentDate !== '') {
            $this->heroSummary .= ' · Versement partenaire le '.$this->nextPaymentDate;
        }
    }

    public function dismiss(string $alertKey): void
    {
        DismissedAlert::firstOrCreate(
            ['user_id' => auth()->id(), 'alert_key' => $alertKey],
            ['dismissed_at' => now()]
        );

        unset($this->alerts);
        $this->dispatch('alerts-updated');
    }

    /** @return array<string> */
    private function dismissedKeys(): array
    {
        return DismissedAlert::where('user_id', auth()->id())
            ->pluck('alert_key')
            ->toArray();
    }

    /** @return array<int, array<string, mixed>> */
    #[Computed]
    public function alerts(): array
    {
        if (! $this->firm) {
            return [];
        }

        $all = app(AlertService::class)->build($this->firm);
        $dismissedKeys = $this->dismissedKeys();

        $active = array_values(array_filter(
            $all,
            fn (array $a) => ! in_array($a['alert_key'], $dismissedKeys)
        ));

        return array_slice($active, 0, 5);
    }

    /** @return array{int, int, string, string} */
    private function computeTierProgress(PartnerTier $tier): array
    {
        return match ($tier) {
            PartnerTier::Partner => [
                min(100, (int) round($this->activeClientsCount / 5 * 100)),
                5,
                '1–4 clients actifs',
                'Gold',
            ],
            PartnerTier::Gold => [
                min(100, (int) round(($this->activeClientsCount - 5) / 10 * 100)),
                15,
                '5–14 clients actifs',
                'Platinum',
            ],
            PartnerTier::Platinum => [100, 15, '15+ clients actifs', ''],
        };
    }

    /** @return array<int, array<string, mixed>> */
    private function buildPortfolio(Collection $smeIds, Collection $allInvoices): array
    {
        $companies = Company::query()
            ->whereIn('id', $smeIds)
            ->with('subscription')
            ->get()
            ->keyBy('id');

        $portfolioService = app(PortfolioService::class);
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
                $daysDiff = (int) abs(now()->diffInDays($lastInvoice->issued_at));
                $lastInvoiceLabel = match (true) {
                    $daysDiff === 0 => "Aujourd'hui",
                    $daysDiff === 1 => 'Hier',
                    default => 'Il y a '.$daysDiff.'j',
                };
            } else {
                $lastInvoiceLabel = '—';
            }

            $status = $portfolioService->clientStatus($invoices);

            $nameParts = collect(explode(' ', $company->name));
            $initials = $nameParts->map(fn ($w) => strtoupper($w[0] ?? ''))->take(2)->join('');

            $portfolio[] = [
                'id' => $company->id,
                'name' => $company->name,
                'initials' => $initials,
                'plan' => ucfirst($company->subscription?->plan_slug ?? $company->plan ?? '—'),
                'last_invoice_label' => $lastInvoiceLabel,
                'unpaid_count' => $unpaidInvoices->count(),
                'pending_amount' => $pendingAmount,
                'recovery_rate' => $recoveryRate,
                'status' => $status,
            ];
        }

        usort($portfolio, fn ($a, $b) => ['critical' => 0, 'watch' => 1, 'current' => 2][$a['status']] <=> ['critical' => 0, 'watch' => 1, 'current' => 2][$b['status']]);

        return array_slice($portfolio, 0, 10);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- En-tête --}}
    <section class="app-shell-panel overflow-hidden">
        <div class="flex flex-col gap-4 p-6 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.24em] text-teal">{{ __('Vue principale') }}</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-ink">
                    {{ __('Bonjour,') }} {{ $firm?->name ?? auth()->user()->first_name }}
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $firm ? $heroSummary : $currentMonth }}
                </p>
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
                    </span>
                    <p class="text-sm text-slate-500">
                        {{ $tierRangeLabel }}
                        @if (! $isPlatinum)
                            · Prochain: {{ $nextTierLabel }} à {{ $nextThreshold }}
                        @endif
                    </p>
                </div>
            @endif
        </div>
    </section>

    {{-- 5 Stat cards --}}
    <section class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-5">

        {{-- Clients actifs --}}
        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-teal-50">
                    <flux:icon name="user-group" class="size-5 text-primary" />
                </div>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-sm font-medium text-slate-500">
                    Portefeuille
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Clients suivis') }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ __('Portefeuille actif') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-ink">{{ $activeClientsCount }}</p>
        </article>

        {{-- À jour --}}
        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-emerald-50">
                    <flux:icon name="check-circle" class="size-5 text-accent" />
                </div>
                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-sm font-semibold text-emerald-700">
                    À jour
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Clients à jour') }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ __('Aucun retard en cours') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-accent">{{ $upToDateCount }}</p>
        </article>

        {{-- À surveiller --}}
        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-amber-50">
                    <flux:icon name="eye" class="size-5 text-amber-500" />
                </div>
                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-sm font-semibold text-amber-700">
                    À surveiller
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Dossiers à relancer') }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ __('Clients à surveiller') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-amber-500">{{ $watchCount }}</p>
        </article>

        {{-- Critiques --}}
        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-rose-50">
                    <flux:icon name="exclamation-triangle" class="size-5 text-rose-500" />
                </div>
                <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-1 text-sm font-semibold text-rose-700">
                    &gt; 60j
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Impayés critiques') }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ __('Plus de 60 jours') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-rose-500">{{ $criticalCount }}</p>
        </article>

        {{-- Commission --}}
        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-primary/8">
                    <flux:icon name="banknotes" class="size-5 text-primary" />
                </div>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-sm font-semibold text-slate-600">
                    {{ $currentMonth }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Commissions du mois') }}</p>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-primary">
                {{ format_money($commissionAmount) }}
            </p>
            <p class="mt-1 text-sm text-slate-500">
                {{ $currentMonth }}
                @if ($nextPaymentDate)
                    · {{ __('Versement prévu le') }} {{ $nextPaymentDate }}
                @endif
            </p>
        </article>

    </section>

    {{-- Alertes récentes --}}
    <section class="app-shell-panel p-6">
        <div class="flex items-center justify-between gap-4">
            <h3 class="text-xl font-semibold tracking-tight text-ink">{{ __('Alertes récentes') }}</h3>
            <a href="{{ route('alerts.index') }}" wire:navigate
               class="text-sm font-semibold text-primary hover:underline">
                {{ __('Voir toutes les alertes') }} →
            </a>
        </div>

        @if (count($this->alerts) > 0)
            <div class="mt-4 divide-y divide-slate-100">
                @foreach ($this->alerts as $alert)
                    @php
                        $alertTitle = (string) str($alert['title'])->before(' — ');
                        $alertBadge = match ($alert['type']) {
                            'critical' => __('Impayé critique'),
                            'watch' => __('Client à surveiller'),
                            default => __('Nouvelle inscription'),
                        };
                    @endphp
                    <div wire:key="dashboard-alert-{{ $alert['alert_key'] }}" class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:gap-4">
                        <span @class([
                            'flex size-10 shrink-0 items-center justify-center rounded-2xl text-base font-bold',
                            'bg-rose-100 text-rose-600'      => $alert['type'] === 'critical',
                            'bg-amber-100 text-amber-600'    => $alert['type'] === 'watch',
                            'bg-emerald-100 text-emerald-600' => $alert['type'] === 'new',
                        ])>
                            @if ($alert['type'] === 'critical') ! @elseif ($alert['type'] === 'watch') ~ @else + @endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="truncate font-semibold text-ink">{{ $alertTitle }}</p>
                                <span @class([
                                    'inline-flex items-center rounded-full px-2.5 py-0.5 text-sm font-semibold ring-1 ring-inset',
                                    'bg-rose-50 text-rose-700 ring-rose-600/20' => $alert['type'] === 'critical',
                                    'bg-amber-50 text-amber-700 ring-amber-600/20' => $alert['type'] === 'watch',
                                    'bg-green-50 text-green-700 ring-green-600/20' => $alert['type'] === 'new',
                                ])>
                                    {{ $alertBadge }}
                                </span>
                            </div>
                            <p class="mt-0.5 truncate text-sm text-slate-500">{{ $alert['subtitle'] }}</p>
                        </div>
                        <div class="flex shrink-0 items-center self-start sm:self-center">
                            <flux:dropdown position="bottom" align="end">
                                <button type="button" class="inline-flex items-center gap-x-1.5 rounded-xl bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-xs ring-1 ring-inset ring-slate-300 hover:bg-slate-50">
                                    {{ __('Actions') }}
                                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="-mr-0.5 size-4 text-slate-400">
                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" />
                                    </svg>
                                </button>
                                <flux:menu>
                                    @if ($alert['company_id'] ?? null)
                                        <flux:menu.item :href="route('clients.show', $alert['company_id'])" wire:navigate>
                                            <x-app.icon name="user" class="size-4 text-slate-500" />
                                            {{ __('Voir le client') }}
                                        </flux:menu.item>
                                    @endif

                                    <flux:menu.separator />

                                    <flux:menu.item wire:click="dismiss('{{ $alert['alert_key'] }}')">
                                        <x-app.icon name="check" class="size-4 text-slate-500" />
                                        {{ __('Marquer comme traité') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </div>
                @endforeach
            </div>

        @else
            <p class="mt-4 text-sm text-slate-500">{{ __('Tous vos clients sont à jour. Beau travail !') }}</p>
        @endif
    </section>

    {{-- Votre niveau partenaire --}}
    @if ($firm)
        <section class="app-shell-panel p-6">
            <div class="flex flex-col gap-1">
                <h3 class="text-xl font-semibold tracking-tight text-ink">{{ __('Votre niveau partenaire') }}</h3>
                <p class="text-sm text-slate-500">{{ __('Votre statut évolue selon le nombre de clients référés actifs sur Fayeku.') }}</p>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div @class([
                    'rounded-2xl border border-slate-200 bg-slate-50 p-4',
                    'ring-2 ring-slate-300' => $tierValue === 'partner',
                ])>
                    <p class="text-sm font-semibold uppercase tracking-wider text-slate-700">Partner</p>
                    <p class="mt-1 text-base font-bold text-ink">1–4 clients actifs</p>
                    @if ($tierValue === 'partner')
                        <span class="mt-2 inline-flex items-center rounded-full bg-primary px-2.5 py-0.5 text-sm font-semibold text-white">{{ __('Niveau actuel') }}</span>
                    @endif
                    <ul class="mt-3 space-y-1 text-sm text-slate-600">
                        <li>{{ __('Commission de 15 %') }}</li>
                        <li>{{ __('Accès Fayeku Compta') }}</li>
                    </ul>
                </div>
                <div @class([
                    'rounded-2xl border border-amber-200 bg-amber-50 p-4',
                    'ring-2 ring-amber-300' => $tierValue === 'gold',
                ])>
                    <p class="text-sm font-semibold uppercase tracking-wider text-amber-700">Gold</p>
                    <p class="mt-1 text-base font-bold text-ink">5–14 clients actifs</p>
                    @if ($tierValue === 'gold')
                        <span class="mt-2 inline-flex items-center rounded-full bg-amber-400 px-2.5 py-0.5 text-sm font-semibold text-amber-950">{{ __('Niveau actuel') }}</span>
                    @endif
                    <ul class="mt-3 space-y-1 text-sm text-slate-600">
                        <li class="font-medium text-amber-700">{{ __('Commission récurrente de 15 %') }}</li>
                        <li>{{ __('Badge partenaire · Leads PME') }}</li>
                    </ul>
                </div>
                <div @class([
                    'rounded-2xl border border-sky-200 bg-sky-50 p-4',
                    'ring-2 ring-sky-300' => $tierValue === 'platinum',
                ])>
                    <p class="text-sm font-semibold uppercase tracking-wider text-sky-800">Platinum</p>
                    <p class="mt-1 text-base font-bold text-ink">15+ clients actifs</p>
                    @if ($tierValue === 'platinum')
                        <span class="mt-2 inline-flex items-center rounded-full bg-ink px-2.5 py-0.5 text-sm font-semibold text-accent">{{ __('Niveau actuel') }}</span>
                    @endif
                    <ul class="mt-3 space-y-1 text-sm text-slate-600">
                        <li>{{ __('Tous les avantages Gold') }}</li>
                        <li>{{ __('Account manager · Co-marketing') }}</li>
                    </ul>
                </div>
            </div>

            <div class="mt-5">
                <div class="flex items-center justify-between text-sm">
                    <span class="font-medium text-slate-600">
                        {{ $activeClientsCount }} {{ $activeClientsCount > 1 ? 'clients actifs' : 'client actif' }}
                        @if ($isPlatinum)
                            · {{ __('Niveau Platinum atteint') }}
                        @else
                            · {{ __('Prochain niveau') }} {{ $nextTierLabel }} : {{ $nextThreshold }}
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
    @endif

    {{-- Aperçu du portefeuille --}}
    <section class="app-shell-panel overflow-hidden">
        <div class="flex items-center justify-between gap-4 p-6 pb-4">
            <h3 class="text-xl font-semibold tracking-tight text-ink">{{ __('Aperçu du portefeuille') }}</h3>
            <a href="{{ route('clients.index') }}" wire:navigate class="text-sm font-semibold text-primary hover:underline">{{ __('Voir tout') }} →</a>
        </div>

        @if (count($portfolio) > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-y border-slate-100 bg-slate-50/80">
                            <th class="px-6 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Client') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Offre') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500 whitespace-nowrap">{{ __('Dernière facture') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500 whitespace-nowrap">{{ __('Impayés') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500 whitespace-nowrap">{{ __('Montant en attente') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500 whitespace-nowrap">{{ __('Taux de recouvrement') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Statut') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($portfolio as $row)
                            <tr
                                wire:key="dashboard-portfolio-{{ $row['id'] }}"
                                class="cursor-pointer transition hover:bg-slate-50/60"
                                @click="Livewire.navigate('{{ route('clients.show', $row['id']) }}')"
                            >
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-2xl bg-mist text-sm font-bold text-primary">
                                            {{ $row['initials'] }}
                                        </span>
                                        <span class="font-semibold text-ink">{{ $row['name'] }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2.5 py-0.5 text-sm font-semibold',
                                        'bg-teal-100 text-teal-700'     => strtolower($row['plan']) === 'essentiel',
                                        'bg-violet-100 text-violet-700' => strtolower($row['plan']) === 'basique',
                                        'bg-amber-100 text-amber-700'   => strtolower($row['plan']) === 'premium',
                                        'bg-slate-100 text-slate-600'   => ! in_array(strtolower($row['plan']), ['essentiel', 'basique', 'premium']),
                                    ])>
                                        {{ $row['plan'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-slate-600">{{ $row['last_invoice_label'] }}</td>
                                <td class="px-4 py-4">
                                    @if ($row['unpaid_count'] > 0)
                                        <span @class([
                                            'font-semibold',
                                            'text-rose-500'  => $row['status'] === 'critical',
                                            'text-amber-500' => $row['status'] === 'watch',
                                        ])>{{ $row['unpaid_count'] }}</span>
                                    @else
                                        <span class="text-slate-500">0</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 font-semibold text-ink whitespace-nowrap">
                                    @if ($row['pending_amount'] > 0)
                                        {{ format_money($row['pending_amount'], compact: true) }}
                                    @else
                                        <span class="text-slate-500">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <span @class([
                                        'font-semibold',
                                        'text-rose-500'  => $row['recovery_rate'] < 75,
                                        'text-amber-500' => $row['recovery_rate'] >= 75 && $row['recovery_rate'] < 95,
                                        'text-accent'    => $row['recovery_rate'] >= 95,
                                    ])>{{ $row['recovery_rate'] }}%</span>
                                </td>
                                <td class="px-4 py-4">
                                    <span @class([
                                        'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-sm font-semibold ring-1 ring-inset',
                                        'bg-rose-50 text-rose-700 ring-rose-600/20'    => $row['status'] === 'critical',
                                        'bg-amber-50 text-amber-700 ring-amber-600/20' => $row['status'] === 'watch',
                                        'bg-green-50 text-green-700 ring-green-600/20' => $row['status'] === 'current',
                                    ])>
                                        <span @class([
                                            'size-1.5 rounded-full',
                                            'bg-rose-500'  => $row['status'] === 'critical',
                                            'bg-amber-500' => $row['status'] === 'watch',
                                            'bg-green-500' => $row['status'] === 'current',
                                        ])></span>
                                        @if ($row['status'] === 'critical') Critique
                                        @elseif ($row['status'] === 'watch') Attente
                                        @else À jour @endif
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="px-6 pb-6">
                <p class="text-sm text-slate-500">{{ __('Aucun client dans votre portefeuille pour le moment.') }}</p>
            </div>
        @endif
    </section>

</div>
