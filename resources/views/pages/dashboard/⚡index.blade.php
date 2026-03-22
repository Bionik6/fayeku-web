<?php

use Illuminate\Support\Collection;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Auth\Models\AccountantCompany;
use Modules\Auth\Models\Company;
use Modules\Compta\Partnership\Enums\PartnerTier;
use Modules\Compta\Partnership\Models\Commission;
use Modules\Compta\Partnership\Models\PartnerInvitation;
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

        $smeIds = AccountantCompany::query()
            ->where('accountant_firm_id', $this->firm->id)
            ->whereNull('ended_at')
            ->pluck('sme_company_id');

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

        $this->alerts = $this->buildAlerts($smeIds, $allInvoices);
        $this->portfolio = $this->buildPortfolio($smeIds, $allInvoices);
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
    private function buildAlerts(Collection $smeIds, Collection $allInvoices): array
    {
        $alerts = [];

        $criticalInvoice = Invoice::query()
            ->whereIn('company_id', $smeIds)
            ->where('status', InvoiceStatus::Overdue->value)
            ->where('due_at', '<', now()->subDays(60))
            ->with('company')
            ->orderBy('due_at')
            ->first();

        if ($criticalInvoice) {
            $daysLate = (int) now()->diffInDays($criticalInvoice->due_at);
            $alerts[] = [
                'type' => 'critical',
                'title' => $criticalInvoice->company->name.' — impayé critique',
                'subtitle' => ($criticalInvoice->reference ?? 'FAC').' · '.number_format($criticalInvoice->total, 0, ',', ' ').' F · J+'.$daysLate.' · Aucune relance envoyée',
                'company_id' => $criticalInvoice->company_id,
            ];
        }

        $recentCompanyIds = Invoice::query()
            ->whereIn('company_id', $smeIds)
            ->where('issued_at', '>=', now()->subDays(30))
            ->pluck('company_id')
            ->unique();

        $inactiveIds = $smeIds->diff($recentCompanyIds);

        if ($inactiveIds->isNotEmpty()) {
            $inactiveCompany = Company::query()->find($inactiveIds->first());

            if ($inactiveCompany) {
                $invoices = $allInvoices->get($inactiveCompany->id, collect());
                $lastInvoice = $invoices->sortByDesc('issued_at')->first();
                $daysSince = $lastInvoice ? (int) now()->diffInDays($lastInvoice->issued_at) : null;

                $alerts[] = [
                    'type' => 'watch',
                    'title' => $inactiveCompany->name.' — inactif depuis '.($daysSince ? $daysSince.' jours' : 'longtemps'),
                    'subtitle' => 'Aucune facture émise ce mois'.($daysSince ? ' · Dernier contact il y a '.$daysSince.'j' : ''),
                    'company_id' => $inactiveCompany->id,
                ];
            }
        }

        $newInvitation = PartnerInvitation::query()
            ->where('accountant_firm_id', $this->firm->id)
            ->where('status', 'accepted')
            ->where('accepted_at', '>=', now()->subDays(7))
            ->orderByDesc('accepted_at')
            ->first();

        if ($newInvitation) {
            $newSme = $newInvitation->sme_company_id ? Company::query()->find($newInvitation->sme_company_id) : null;
            $alerts[] = [
                'type' => 'new',
                'title' => ($newSme?->name ?? $newInvitation->invitee_name)." — vient de s'inscrire",
                'subtitle' => 'Via votre lien partenaire · Plan '.ucfirst($newInvitation->recommended_plan ?? 'Essentiel').' · Trial 2 mois',
                'company_id' => $newInvitation->sme_company_id,
            ];
        }

        return $alerts;
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
            <article class="app-shell-stat-card">
                <p class="text-sm font-medium text-slate-500">{{ __('Clients actifs') }}</p>
                <p class="mt-2 text-4xl font-semibold tracking-tight text-ink">{{ $activeClientsCount }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ __('Portefeuille total') }}</p>
            </article>

            <article class="app-shell-stat-card border-l-2 border-l-accent">
                <p class="text-sm font-medium text-slate-500">{{ __('À jour') }}</p>
                <p class="mt-2 text-4xl font-semibold tracking-tight text-accent">{{ $upToDateCount }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ __('Factures à jour') }}</p>
            </article>

            <article class="app-shell-stat-card border-l-2 border-l-amber-400">
                <p class="text-sm font-medium text-slate-500">{{ __('À surveiller') }}</p>
                <p class="mt-2 text-4xl font-semibold tracking-tight text-amber-500">{{ $watchCount }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ __('Inactifs ou retard') }}</p>
            </article>

            <article class="app-shell-stat-card border-l-2 border-l-rose-400">
                <p class="text-sm font-medium text-slate-500">{{ __('Critiques') }}</p>
                <p class="mt-2 text-4xl font-semibold tracking-tight text-rose-500">{{ $criticalCount }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ __('Impayés >60j') }}</p>
            </article>

            <article class="app-shell-stat-card border-l-2 border-l-primary">
                <p class="text-sm font-medium text-slate-500">
                    {{ __('Commission') }} {{ ucfirst(now()->locale('fr_FR')->translatedFormat('M')) }}
                </p>
                <p class="mt-2 text-3xl font-semibold tracking-tight text-primary">
                    {{ number_format($commissionAmount, 0, ',', ' ') }} F
                </p>
                @if ($nextPaymentDate)
                    <p class="mt-1 text-xs text-slate-400">Versement {{ $nextPaymentDate }}</p>
                @endif
            </article>
        </section>

        {{-- Alertes du jour --}}
        <section class="app-shell-panel p-6">
            <div class="flex items-center justify-between gap-4">
                <h3 class="text-xl font-semibold tracking-tight text-ink">{{ __('Alertes du jour') }}</h3>
                @if (count($alerts) > 0)
                    <span class="inline-flex items-center rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700">
                        {{ count($alerts) }} {{ count($alerts) === 1 ? 'alerte' : 'alertes' }}
                    </span>
                @else
                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                        {{ __('Aucune alerte') }}
                    </span>
                @endif
            </div>

            @if (count($alerts) > 0)
                <div class="mt-4 divide-y divide-slate-100">
                    @foreach ($alerts as $alert)
                        <div class="flex items-center gap-4 py-4">
                            <span @class([
                                'flex size-10 shrink-0 items-center justify-center rounded-2xl text-base font-bold',
                                'bg-rose-100 text-rose-600' => $alert['type'] === 'critical',
                                'bg-amber-100 text-amber-600' => $alert['type'] === 'watch',
                                'bg-emerald-100 text-emerald-600' => $alert['type'] === 'new',
                            ])>
                                @if ($alert['type'] === 'critical') ! @elseif ($alert['type'] === 'watch') ~ @else + @endif
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold text-ink">{{ $alert['title'] }}</p>
                                <p class="mt-0.5 truncate text-sm text-slate-500">{{ $alert['subtitle'] }}</p>
                            </div>
                            @if ($alert['company_id'])
                                <a
                                    href="{{ route('clients.show', $alert['company_id']) }}"
                                    class="shrink-0 rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-ink transition hover:border-primary/20 hover:text-primary"
                                >
                                    {{ __('Voir fiche') }}
                                </a>
                            @endif
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
                                <tr @click="Livewire.navigate('{{ route('clients.show', $row['id']) }}')" class="cursor-pointer transition hover:bg-slate-50/60" data-navigate="{{ route('clients.show', $row['id']) }}">
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
                                            'bg-teal-100 text-teal-700' => strtolower($row['plan']) === 'essentiel',
                                            'bg-violet-100 text-violet-700' => strtolower($row['plan']) === 'basique',
                                            'bg-amber-100 text-amber-700' => strtolower($row['plan']) === 'premium',
                                            'bg-slate-100 text-slate-600' => ! in_array(strtolower($row['plan']), ['essentiel', 'basique', 'premium']),
                                        ])>
                                            {{ $row['plan'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-slate-600">{{ $row['last_invoice_label'] }}</td>
                                    <td class="px-4 py-4">
                                        @if ($row['unpaid_count'] > 0)
                                            <span @class([
                                                'font-semibold',
                                                'text-rose-500' => $row['status'] === 'critique',
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
                                            'text-rose-500' => $row['recovery_rate'] < 75,
                                            'text-amber-500' => $row['recovery_rate'] >= 75 && $row['recovery_rate'] < 95,
                                            'text-accent' => $row['recovery_rate'] >= 95,
                                        ])>{{ $row['recovery_rate'] }}%</span>
                                    </td>
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
