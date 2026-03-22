<?php

use Illuminate\Support\Collection;
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

new #[Title('Dashboard')] class extends Component {
    public ?Company $firm = null;

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

    public string $tierRangeLabel = '1-4 clients';

    public string $nextTierLabel = 'Gold';

    public bool $isPlatinum = false;

    /** @var array<int, array<string, mixed>> */
    public array $alerts = [];

    /** @var array<int, array<string, mixed>> */
    public array $portfolio = [];

    public string $currentMonth = '';

    public function mount(): void
    {
        $this->currentMonth = ucfirst(now()->locale('fr_FR')->translatedFormat('F Y'));
        $this->firm = auth()->user()->companies()
            ->where('type', 'accountant_firm')
            ->first();

        if (! $this->firm) {
            return;
        }

        $smeIds = app(PortfolioService::class)->activeSmeIds($this->firm);

        $this->activeClientsCount = $smeIds->count();

        $allInvoices = Invoice::query()
            ->whereIn('company_id', $smeIds)
            ->get()
            ->groupBy('company_id');

        $criticalIds = [];
        $watchIds = [];

        foreach ($smeIds as $smeId) {
            $invoices = $allInvoices->get($smeId, collect());
            $overdueInvoices = $invoices->filter(fn ($inv) => $inv->status === InvoiceStatus::Overdue);
            $criticalOverdue = $overdueInvoices->filter(fn ($inv) => $inv->due_at && $inv->due_at->lt(now()->subDays(60)));
            $latestIssued = $invoices->sortByDesc('issued_at')->first();

            if ($criticalOverdue->isNotEmpty()) {
                $criticalIds[] = $smeId;
            } elseif ($overdueInvoices->isNotEmpty() || ($latestIssued && $latestIssued->issued_at->lt(now()->subDays(30)))) {
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

        $this->nextPaymentDate = now()->locale('fr_FR')->addMonth()->startOfMonth()->addDays(4)->translatedFormat('j F');

        $tier = PartnerTier::fromActiveClients($this->activeClientsCount);
        $this->tierValue = $tier->value;
        $this->tierLabel = match ($tier) {
            PartnerTier::Partner => 'Partner',
            PartnerTier::Gold => 'Gold',
            PartnerTier::Platinum => 'Platinum',
        };
        $this->isPlatinum = $tier === PartnerTier::Platinum;

        [$this->tierProgress, $this->nextThreshold, $this->tierRangeLabel, $this->nextTierLabel] = $this->computeTierProgress($tier);

        $this->alerts = app(AlertService::class)->build($this->firm, null, 5);
        $this->portfolio = $this->buildPortfolio($smeIds, $allInvoices);
    }

    public function dismiss(string $alertKey): void
    {
        DismissedAlert::firstOrCreate(
            ['user_id' => auth()->id(), 'alert_key' => $alertKey],
            ['dismissed_at' => now()]
        );

        $this->alerts = array_values(array_filter(
            $this->alerts,
            fn (array $a) => $a['alert_key'] !== $alertKey
        ));
    }

    /** @return array{int, int, string, string} */
    private function computeTierProgress(PartnerTier $tier): array
    {
        return match ($tier) {
            PartnerTier::Partner => [
                min(100, (int) round($this->activeClientsCount / 5 * 100)),
                5,
                '1-4 clients',
                'Gold',
            ],
            PartnerTier::Gold => [
                min(100, (int) round(($this->activeClientsCount - 5) / 10 * 100)),
                15,
                '5-14 clients',
                'Platinum',
            ],
            PartnerTier::Platinum => [100, 15, '15+ clients', ''],
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
                $lastInvoiceLabel = '—';
            }

            $hasCritical = $invoices->filter(
                fn ($inv) => $inv->status === InvoiceStatus::Overdue && $inv->due_at && $inv->due_at->lt(now()->subDays(60))
            )->isNotEmpty();

            $status = match (true) {
                $hasCritical => 'critique',
                $unpaidInvoices->isNotEmpty() => 'attente',
                default => 'a_jour',
            };

            $nameParts = collect(explode(' ', $company->name));
            $initials = $nameParts->map(fn ($w) => strtoupper($w[0] ?? ''))->take(2)->join('');

            $portfolio[] = [
                'id'                 => $company->id,
                'name'               => $company->name,
                'initials'           => $initials,
                'plan'               => ucfirst($company->subscription?->plan_slug ?? $company->plan ?? '—'),
                'last_invoice_label' => $lastInvoiceLabel,
                'unpaid_count'       => $unpaidInvoices->count(),
                'pending_amount'     => $pendingAmount,
                'recovery_rate'      => $recoveryRate,
                'status'             => $status,
            ];
        }

        usort($portfolio, fn ($a, $b) => ['critique' => 0, 'attente' => 1, 'a_jour' => 2][$a['status']] <=> ['critique' => 0, 'attente' => 1, 'a_jour' => 2][$b['status']]);

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
                    {{ $currentMonth }}
                    @if ($firm && $nextPaymentDate)
                        · Versement Wave le {{ $nextPaymentDate }}
                    @endif
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
                    <p class="text-xs text-slate-500">
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
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">

        {{-- Clients actifs --}}
        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-teal-50">
                    <flux:icon name="user-group" class="size-5 text-primary" />
                </div>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-500">
                    Portefeuille
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Clients actifs suivis') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-ink">{{ $activeClientsCount }}</p>
        </article>

        {{-- À jour --}}
        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-emerald-50">
                    <flux:icon name="check-circle" class="size-5 text-accent" />
                </div>
                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                    À jour
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Factures à jour') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-accent">{{ $upToDateCount }}</p>
        </article>

        {{-- À surveiller --}}
        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-amber-50">
                    <flux:icon name="eye" class="size-5 text-amber-500" />
                </div>
                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
                    À surveiller
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Inactifs ou retard') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-amber-500">{{ $watchCount }}</p>
        </article>

        {{-- Critiques --}}
        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-rose-50">
                    <flux:icon name="exclamation-triangle" class="size-5 text-rose-500" />
                </div>
                <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700">
                    &gt; 60j
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Impayés critiques') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-rose-500">{{ $criticalCount }}</p>
        </article>

        {{-- Commission --}}
        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-primary/8">
                    <flux:icon name="banknotes" class="size-5 text-primary" />
                </div>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                    {{ ucfirst(now()->locale('fr_FR')->translatedFormat('M Y')) }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Commissions estimées') }}</p>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-primary">
                {{ number_format($commissionAmount, 0, ',', ' ') }} FCFA
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

        @if (count($alerts) > 0)
            <div class="mt-4 divide-y divide-slate-100">
                @foreach ($alerts as $alert)
                    <div class="flex items-center gap-4 py-4">
                        <span @class([
                            'flex size-10 shrink-0 items-center justify-center rounded-2xl text-base font-bold',
                            'bg-rose-100 text-rose-600'      => $alert['type'] === 'critical',
                            'bg-amber-100 text-amber-600'    => $alert['type'] === 'watch',
                            'bg-emerald-100 text-emerald-600' => $alert['type'] === 'new',
                        ])>
                            @if ($alert['type'] === 'critical') ! @elseif ($alert['type'] === 'watch') ~ @else + @endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-ink">{{ $alert['title'] }}</p>
                            <p class="mt-0.5 truncate text-sm text-slate-500">{{ $alert['subtitle'] }}</p>
                        </div>
                        {{-- Dropdown actions --}}
                        <flux:dropdown position="bottom" align="end">
                            <button type="button" class="inline-flex shrink-0 items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-1.5 text-sm font-semibold text-slate-600 transition hover:border-primary/30 hover:text-primary">
                                {{ __('Actions') }}
                                <x-app.icon name="chevron-down" class="size-3.5" />
                            </button>
                            <flux:menu>
                                @if ($alert['type'] === 'critical' && ($alert['invoice_id'] ?? null))
                                    <flux:menu.item :href="route('clients.show', $alert['company_id'])" wire:navigate>
                                        <x-app.icon name="invoice" class="size-4 text-slate-400" />
                                        {{ __('Voir Facture') }}
                                    </flux:menu.item>
                                @endif

                                @if ($alert['company_id'] ?? null)
                                    <flux:menu.item :href="route('clients.show', $alert['company_id'])" wire:navigate>
                                        <x-app.icon name="user" class="size-4 text-slate-400" />
                                        {{ __('Voir Fiche Client') }}
                                    </flux:menu.item>
                                @endif

                                <flux:menu.separator />

                                <flux:menu.item wire:click="dismiss('{{ $alert['alert_key'] }}')">
                                    <x-app.icon name="check" class="size-4 text-slate-400" />
                                    {{ __('Marquer comme vu') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </div>
                @endforeach
            </div>

        @else
            <p class="mt-4 text-sm text-slate-400">{{ __('Tous vos clients sont à jour. Beau travail !') }}</p>
        @endif
    </section>

    {{-- Progression statut partenaire --}}
    @if ($firm)
        <section class="app-shell-panel p-6">
            <div class="flex items-center justify-between gap-4">
                <h3 class="text-xl font-semibold tracking-tight text-ink">{{ __('Progression statut partenaire') }}</h3>
                <span @class([
                    'inline-flex items-center gap-1.5 rounded-full px-4 py-1.5 text-sm font-semibold',
                    'bg-primary text-white' => $tierValue === 'partner',
                    'bg-amber-400 text-amber-950' => $tierValue === 'gold',
                    'bg-ink text-accent' => $tierValue === 'platinum',
                ])>
                    {{ $tierLabel }} @if ($tierValue !== 'partner') ★ @endif
                </span>
            </div>

            <div class="mt-6 grid grid-cols-3 gap-px overflow-hidden rounded-2xl border border-slate-200">
                <div @class([
                    'p-4',
                    'bg-slate-50' => $tierValue !== 'partner',
                    'bg-primary/8' => $tierValue === 'partner',
                ])>
                    <p class="text-xs font-semibold text-slate-500">Partner · 1-4 clients</p>
                    <p class="mt-1 text-sm font-semibold text-ink">Commission 15%</p>
                </div>
                <div @class([
                    'p-4',
                    'bg-amber-50' => $tierValue === 'gold',
                    'bg-slate-50' => $tierValue !== 'gold',
                ])>
                    <p @class(['text-xs font-semibold', 'text-amber-700' => $tierValue === 'gold', 'text-slate-500' => $tierValue !== 'gold'])>
                        Gold ★ · 5-14 clients @if ($tierValue === 'gold') · Actuel @endif
                    </p>
                    <p class="mt-1 text-sm font-semibold text-ink">Commission 15% + badge + leads</p>
                </div>
                <div @class([
                    'p-4',
                    'bg-ink text-white' => $tierValue === 'platinum',
                    'bg-slate-50' => $tierValue !== 'platinum',
                ])>
                    <p @class(['text-xs font-semibold', 'text-accent' => $tierValue === 'platinum', 'text-slate-500' => $tierValue !== 'platinum'])>
                        Platinum · 15+ clients @if ($tierValue === 'platinum') · Actuel @endif
                    </p>
                    <p @class(['mt-1 text-sm font-semibold', 'text-white' => $tierValue === 'platinum', 'text-ink' => $tierValue !== 'platinum'])>
                        + account manager + co-mktg
                    </p>
                </div>
            </div>

            @if (! $isPlatinum)
                <div class="mt-4">
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium text-primary">
                            Progression vers {{ $nextTierLabel }} :
                            <span class="font-bold">{{ $activeClientsCount }}/{{ $nextThreshold }} clients</span>
                            @if ($activeClientsCount >= $nextThreshold)
                                — Éligible {{ $nextTierLabel }} dès ce mois ✓
                            @endif
                        </span>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                        <div
                            class="h-2 rounded-full bg-accent transition-all duration-500"
                            style="width: {{ $tierProgress }}%"
                        ></div>
                    </div>
                </div>
            @endif
        </section>
    @endif

    {{-- Aperçu portefeuille --}}
    <section class="app-shell-panel overflow-hidden">
        <div class="flex items-center justify-between gap-4 p-6 pb-4">
            <h3 class="text-xl font-semibold tracking-tight text-ink">{{ __('Aperçu portefeuille') }}</h3>
            <a href="{{ route('clients.index') }}" wire:navigate class="text-sm font-semibold text-primary hover:underline">{{ __('Voir tout') }} →</a>
        </div>

        @if (count($portfolio) > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-y border-slate-100 bg-slate-50/80">
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Client') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Plan') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Dernière facture') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Impayés') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Montant en attente') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Taux recouvrement') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Statut') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($portfolio as $row)
                            <tr
                                class="cursor-pointer transition hover:bg-slate-50/60"
                                @click="Livewire.navigate('{{ route('clients.show', $row['id']) }}')"
                            >
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-2xl bg-mist text-xs font-bold text-primary">
                                            {{ $row['initials'] }}
                                        </span>
                                        <span class="font-semibold text-ink">{{ $row['name'] }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
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
                                            'text-rose-500'  => $row['status'] === 'critique',
                                            'text-amber-500' => $row['status'] === 'attente',
                                        ])>{{ $row['unpaid_count'] }}</span>
                                    @else
                                        <span class="text-slate-400">0</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 font-semibold text-ink">
                                    @if ($row['pending_amount'] > 0)
                                        {{ number_format($row['pending_amount'], 0, ',', ' ') }} F
                                    @else
                                        <span class="text-slate-400">—</span>
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
                                        'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset',
                                        'bg-rose-50 text-rose-700 ring-rose-600/20'    => $row['status'] === 'critique',
                                        'bg-amber-50 text-amber-700 ring-amber-600/20' => $row['status'] === 'attente',
                                        'bg-green-50 text-green-700 ring-green-600/20' => $row['status'] === 'a_jour',
                                    ])>
                                        <span @class([
                                            'size-1.5 rounded-full',
                                            'bg-rose-500'  => $row['status'] === 'critique',
                                            'bg-amber-500' => $row['status'] === 'attente',
                                            'bg-green-500' => $row['status'] === 'a_jour',
                                        ])></span>
                                        @if ($row['status'] === 'critique') Critique
                                        @elseif ($row['status'] === 'attente') Attente
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
                <p class="text-sm text-slate-400">{{ __('Aucun client dans votre portefeuille pour le moment.') }}</p>
            </div>
        @endif
    </section>

</div>
