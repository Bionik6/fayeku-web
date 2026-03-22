<?php

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Auth\Models\AccountantCompany;
use Modules\Auth\Models\Company;
use Modules\Compta\Partnership\Enums\PartnerTier;
use Modules\PME\Invoicing\Enums\InvoiceStatus;
use Modules\PME\Invoicing\Models\Invoice;

new #[Title('Fiche client')] class extends Component {
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
        $this->firm = auth()->user()->companies()
            ->where('type', 'accountant_firm')
            ->first();

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
        $this->clientSince = ucfirst($this->relation->started_at->locale('fr_FR')->translatedFormat('M Y'));
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
                'label' => ucfirst($inv->issued_at->locale('fr_FR')->translatedFormat('F Y')),
            ])
            ->unique('value')
            ->values()
            ->toArray();

        $current = now()->format('Y-m');
        if (collect($months)->where('value', $current)->isEmpty()) {
            array_unshift($months, [
                'value' => $current,
                'label' => ucfirst(now()->locale('fr_FR')->translatedFormat('F Y')),
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

        return $hasPending ? 'attente' : 'a_jour';
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
                    <p class="text-sm font-semibold uppercase tracking-[0.24em] text-teal">{{ __('Fiche client') }}</p>
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight text-ink">{{ $company->name }}</h2>
                    <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                        <span @class([
                            'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
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
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <flux:button variant="ghost" class="border border-slate-200" icon="arrow-down-tray">
                    {{ __('Exporter') }}
                </flux:button>

                <flux:button
                    variant="danger"
                    wire:click="archive"
                    wire:confirm="{{ __('Archiver ce client ? Cette action retirera la PME de votre portefeuille actif.') }}"
                    icon="archive-box-x-mark"
                >
                    {{ __('Archiver') }}
                </flux:button>

                <span @class([
                    'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm font-semibold',
                    'border-rose-200 bg-rose-50 text-rose-700'      => $this->statusValue === 'critique',
                    'border-amber-200 bg-amber-50 text-amber-700'   => $this->statusValue === 'attente',
                    'border-emerald-200 bg-emerald-50 text-emerald-700' => $this->statusValue === 'a_jour',
                ])>
                    <span @class([
                        'size-2 rounded-full',
                        'bg-rose-500'    => $this->statusValue === 'critique',
                        'bg-amber-400'   => $this->statusValue === 'attente',
                        'bg-emerald-500' => $this->statusValue === 'a_jour',
                    ])></span>
                    @if ($this->statusValue === 'critique') Critique
                    @elseif ($this->statusValue === 'attente') Attente
                    @else À jour
                    @endif
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
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                    {{ ucfirst(now()->setYear($this->selectedYear())->setMonth($this->selectedMonth())->locale('fr_FR')->translatedFormat('M Y')) }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('CA facturé') }}</p>
            <p class="mt-1 text-2xl font-bold tracking-tight text-ink">
                {{ number_format($this->stats['billed_month'], 0, ',', ' ') }} FCFA
            </p>
        </section>

        {{-- Encaissé --}}
        <section class="app-shell-panel p-5">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-emerald-50">
                    <flux:icon name="banknotes" class="size-5 text-accent" />
                </div>
                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                    All-time
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Encaissé') }}</p>
            <p class="mt-1 text-2xl font-bold tracking-tight text-accent">
                {{ number_format($this->stats['collected'], 0, ',', ' ') }} FCFA
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
                        'text-slate-400'  => $this->stats['pending_amount'] === 0,
                    ]) />
                </div>
                @if ($this->stats['pending_count'] > 0)
                    <span @class([
                        'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold',
                        'bg-rose-50 text-rose-700'   => $this->statusValue === 'critique',
                        'bg-amber-50 text-amber-700' => $this->statusValue !== 'critique',
                    ])>
                        {{ $this->stats['pending_count'] }} {{ $this->stats['pending_count'] > 1 ? __('factures') : __('facture') }}
                    </span>
                @else
                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                        À jour
                    </span>
                @endif
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('En attente') }}</p>
            <p @class([
                'mt-1 text-2xl font-bold tracking-tight',
                'text-rose-500'  => $this->statusValue === 'critique',
                'text-amber-500' => $this->statusValue !== 'critique' && $this->stats['pending_amount'] > 0,
                'text-ink'       => $this->stats['pending_amount'] === 0,
            ])>
                {{ number_format($this->stats['pending_amount'], 0, ',', ' ') }} FCFA
            </p>
        </section>

        {{-- Délai moyen · Taux --}}
        <section class="app-shell-panel p-5">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-amber-50">
                    <flux:icon name="clock" class="size-5 text-amber-500" />
                </div>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                    Recouvrement
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Délai moyen · Taux') }}</p>
            <p class="mt-1 text-2xl font-bold tracking-tight text-amber-500">
                {{ $this->stats['avg_days'] }}j
                <span class="text-slate-400">·</span>
                {{ $this->stats['recovery_rate'] }}%
            </p>
        </section>

    </div>

    {{-- ─── Factures du mois ─────────────────────────────────────────────── --}}
    <section class="app-shell-panel overflow-hidden">

        {{-- En-tête section --}}
        <div class="flex flex-col gap-3 p-6 pb-4 sm:flex-row sm:items-center sm:justify-between">
            <h3 class="text-xl font-semibold tracking-tight text-ink">{{ __('Factures du mois') }}</h3>

            <div class="flex items-center gap-2">
                {{-- Par page --}}
                <select
                    wire:model.live="perPage"
                    class="rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2 text-sm text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                >
                    <option value="20">20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>

                {{-- Sélecteur de mois --}}
                <select
                    wire:model.live="selectedPeriod"
                    class="rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2 text-sm text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                >
                    @foreach ($this->availableMonths as $m)
                        <option value="{{ $m['value'] }}">{{ $m['label'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Tableau --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-y border-slate-100 bg-slate-50/80">
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Référence') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Client final') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Montant HT') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('TVA') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Montant TTC') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Émise le') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Échéance') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Délai') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Relances') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Statut') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($this->invoices->take($perPage) as $invoice)
                        @php
                            $isPaid    = $invoice->status === InvoiceStatus::Paid;
                            $isOverdue = $invoice->status === InvoiceStatus::Overdue;

                            if ($isPaid && $invoice->paid_at) {
                                $delayDays  = (int) abs($invoice->due_at->diffInDays($invoice->paid_at));
                                $delayClass = $invoice->paid_at->lte($invoice->due_at) ? 'text-accent' : 'text-rose-500';
                            } else {
                                $delayDays  = (int) abs(now()->diffInDays($invoice->due_at));
                                $delayClass = $isOverdue ? 'text-rose-500' : 'text-amber-500';
                            }

                            $statusConfig = match ($invoice->status) {
                                InvoiceStatus::Paid               => ['label' => 'Payée',       'class' => 'bg-teal-100 text-teal-700'],
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
                            <td class="px-6 py-4 font-mono text-xs font-semibold text-ink">
                                {{ $invoice->reference }}
                            </td>
                            <td class="px-4 py-4 text-slate-700">
                                {{ $invoice->client?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-4 text-right text-slate-600">
                                {{ number_format($invoice->subtotal, 0, ',', ' ') }} F
                            </td>
                            <td class="px-4 py-4 text-right text-slate-500">
                                {{ number_format($invoice->tax_amount, 0, ',', ' ') }} F
                            </td>
                            <td class="px-4 py-4 text-right font-semibold text-ink">
                                {{ number_format($invoice->total, 0, ',', ' ') }} F
                            </td>
                            <td class="px-4 py-4 text-slate-600">
                                {{ $invoice->issued_at->locale('fr_FR')->translatedFormat('j M.') }}
                            </td>
                            <td class="px-4 py-4 text-slate-600">
                                {{ $invoice->due_at->locale('fr_FR')->translatedFormat('j M.') }}
                            </td>
                            <td class="px-4 py-4 text-center">
                                <span class="font-semibold {{ $delayClass }}">J+{{ $delayDays }}</span>
                            </td>
                            <td class="px-4 py-4 text-center text-slate-600">
                                @if ($invoice->reminders_count === 0)
                                    <span class="text-slate-400">0</span>
                                @else
                                    {{ $invoice->reminders_count }}
                                    {{ $invoice->reminders_count === 1 ? __('envoyée') : __('envoyées') }}
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusConfig['class'] }}">
                                    {{ $statusConfig['label'] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-6 py-10 text-center text-sm text-slate-400">
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
        @php
            $inv = $this->selectedInvoice;
            $client = $inv->client;

            $statusConfig = match ($inv->status) {
                InvoiceStatus::Paid               => ['label' => 'Payée',       'class' => 'bg-teal-100 text-teal-700'],
                InvoiceStatus::Overdue            => ['label' => 'Impayée',     'class' => 'bg-rose-100 text-rose-700'],
                InvoiceStatus::PartiallyPaid      => ['label' => 'Partiel',     'class' => 'bg-orange-100 text-orange-700'],
                InvoiceStatus::Sent,
                InvoiceStatus::Certified          => ['label' => 'En attente',  'class' => 'bg-amber-50 text-amber-700'],
                InvoiceStatus::Draft              => ['label' => 'Brouillon',   'class' => 'bg-slate-100 text-slate-600'],
                InvoiceStatus::Cancelled          => ['label' => 'Annulée',     'class' => 'bg-slate-100 text-slate-500'],
                default                           => ['label' => ucfirst($inv->status->value), 'class' => 'bg-slate-100 text-slate-600'],
            };
        @endphp

        {{-- Overlay --}}
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            wire:click.self="closeInvoice"
        >
            <div class="relative w-full max-w-[1200px] overflow-hidden rounded-2xl bg-white shadow-2xl">

                {{-- Header --}}
                <div class="flex items-start justify-between border-b border-slate-100 px-10 py-7">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">{{ __('Facture') }}</p>
                        <h2 class="mt-1 text-xl font-bold text-ink">{{ $inv->reference }}</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ __('Émise le') }} {{ $inv->issued_at->locale('fr_FR')->translatedFormat('j F Y') }}
                            &nbsp;·&nbsp;
                            {{ __('Échéance') }} {{ $inv->due_at->locale('fr_FR')->translatedFormat('j F Y') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $statusConfig['class'] }}">
                            {{ $statusConfig['label'] }}
                        </span>
                        <button
                            wire:click="closeInvoice"
                            class="rounded-full p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                        >
                            <flux:icon name="x-mark" class="size-5" />
                        </button>
                    </div>
                </div>

                {{-- Body --}}
                <div class="max-h-[80vh] overflow-y-auto">
                    <div class="grid grid-cols-1 gap-0 lg:grid-cols-3">

                        {{-- Colonne principale : Destinataire + lignes --}}
                        <div class="col-span-2 px-10 py-8">

                            {{-- Destinataire --}}
                            @if ($client)
                                <div class="mb-6">
                                    <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-slate-400">{{ __('Destinataire') }}</p>
                                    <div class="rounded-xl border border-slate-100 bg-slate-50/60 px-5 py-4">
                                        <p class="font-semibold text-ink">{{ $client->name }}</p>
                                        @if ($client->phone)
                                            <p class="mt-1 flex items-center gap-1.5 text-sm text-slate-500">
                                                <flux:icon name="phone" class="size-3.5 shrink-0" />
                                                {{ $client->phone }}
                                            </p>
                                        @endif
                                        @if ($client->email)
                                            <p class="mt-0.5 flex items-center gap-1.5 text-sm text-slate-500">
                                                <flux:icon name="envelope" class="size-3.5 shrink-0" />
                                                {{ $client->email }}
                                            </p>
                                        @endif
                                        @if ($client->address)
                                            <p class="mt-0.5 flex items-center gap-1.5 text-sm text-slate-500">
                                                <flux:icon name="map-pin" class="size-3.5 shrink-0" />
                                                {{ $client->address }}
                                            </p>
                                        @endif
                                        @if ($client->tax_id)
                                            <p class="mt-1 text-xs font-mono text-slate-400">{{ __('Réf. fiscale') }} : {{ $client->tax_id }}</p>
                                        @endif
                                    </div>
                                </div>
                            @else
                                <div class="mb-6 rounded-xl border border-amber-100 bg-amber-50 px-5 py-4 text-sm text-amber-700">
                                    {{ __('Aucun client final renseigné sur cette facture.') }}
                                </div>
                            @endif

                            {{-- Lignes de facture --}}
                            <div>
                                <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-slate-400">{{ __('Détail des prestations') }}</p>
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-slate-100 text-left">
                                            <th class="pb-2 pr-4 text-xs font-semibold text-slate-500">{{ __('Description') }}</th>
                                            <th class="pb-2 px-4 text-right text-xs font-semibold text-slate-500">{{ __('Qté') }}</th>
                                            <th class="pb-2 px-4 text-right text-xs font-semibold text-slate-500">{{ __('PU HT') }}</th>
                                            <th class="pb-2 px-4 text-right text-xs font-semibold text-slate-500">{{ __('TVA') }}</th>
                                            <th class="pb-2 pl-4 text-right text-xs font-semibold text-slate-500">{{ __('Total HT') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50">
                                        @forelse ($inv->lines as $line)
                                            <tr>
                                                <td class="py-3 pr-4 text-ink">{{ $line->description }}</td>
                                                <td class="py-3 px-4 text-right tabular-nums text-slate-600">{{ $line->quantity }}</td>
                                                <td class="py-3 px-4 text-right tabular-nums text-slate-600">
                                                    {{ number_format($line->unit_price, 0, ',', ' ') }} F
                                                </td>
                                                <td class="py-3 px-4 text-right tabular-nums text-slate-500">{{ $line->tax_rate }} %</td>
                                                <td class="py-3 pl-4 text-right tabular-nums font-medium text-ink">
                                                    {{ number_format($line->total, 0, ',', ' ') }} F
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="py-4 text-center text-slate-400">{{ __('Aucune ligne.') }}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    <tfoot class="border-t border-slate-200">
                                        <tr>
                                            <td colspan="4" class="pt-4 pr-4 text-right text-sm text-slate-500">{{ __('Sous-total HT') }}</td>
                                            <td class="pt-4 pl-4 text-right tabular-nums text-sm text-ink">
                                                {{ number_format($inv->subtotal, 0, ',', ' ') }} F
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="4" class="pt-1 pr-4 text-right text-sm text-slate-500">{{ __('TVA') }}</td>
                                            <td class="pt-1 pl-4 text-right tabular-nums text-sm text-ink">
                                                {{ number_format($inv->tax_amount, 0, ',', ' ') }} F
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="4" class="pt-2 pr-4 text-right text-base font-semibold text-ink">{{ __('Total TTC') }}</td>
                                            <td class="pt-2 pl-4 text-right tabular-nums text-base font-bold text-ink">
                                                {{ number_format($inv->total, 0, ',', ' ') }} F
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        {{-- Colonne latérale : récap montants --}}
                        <div class="border-t border-slate-100 bg-slate-50/60 px-8 py-8 lg:border-t-0 lg:border-l">
                            <p class="mb-4 text-xs font-semibold uppercase tracking-widest text-slate-400">{{ __('Récapitulatif') }}</p>

                            <dl class="space-y-3 text-sm">
                                <div class="flex justify-between">
                                    <dt class="text-slate-500">{{ __('Montant HT') }}</dt>
                                    <dd class="tabular-nums font-medium text-ink">{{ number_format($inv->subtotal, 0, ',', ' ') }} FCFA</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-slate-500">{{ __('TVA') }}</dt>
                                    <dd class="tabular-nums font-medium text-ink">{{ number_format($inv->tax_amount, 0, ',', ' ') }} FCFA</dd>
                                </div>
                                <div class="flex justify-between border-t border-slate-200 pt-3">
                                    <dt class="font-semibold text-ink">{{ __('Total TTC') }}</dt>
                                    <dd class="tabular-nums text-lg font-bold text-ink">{{ number_format($inv->total, 0, ',', ' ') }} FCFA</dd>
                                </div>

                                @if ($inv->status === InvoiceStatus::PartiallyPaid)
                                    <div class="flex justify-between text-amber-600">
                                        <dt>{{ __('Encaissé') }}</dt>
                                        <dd class="tabular-nums font-medium">{{ number_format($inv->amount_paid, 0, ',', ' ') }} FCFA</dd>
                                    </div>
                                    <div class="flex justify-between text-rose-600">
                                        <dt class="font-semibold">{{ __('Reste dû') }}</dt>
                                        <dd class="tabular-nums font-bold">{{ number_format($inv->total - $inv->amount_paid, 0, ',', ' ') }} FCFA</dd>
                                    </div>
                                @endif
                            </dl>

                            <div class="mt-6 space-y-2 border-t border-slate-200 pt-4 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-slate-500">{{ __('Émise le') }}</span>
                                    <span class="text-ink">{{ $inv->issued_at->locale('fr_FR')->translatedFormat('j M Y') }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500">{{ __('Échéance') }}</span>
                                    <span @class([
                                        'font-medium',
                                        'text-rose-600' => $inv->status === InvoiceStatus::Overdue,
                                        'text-ink'      => $inv->status !== InvoiceStatus::Overdue,
                                    ])>{{ $inv->due_at->locale('fr_FR')->translatedFormat('j M Y') }}</span>
                                </div>
                                @if ($inv->paid_at)
                                    <div class="flex justify-between">
                                        <span class="text-slate-500">{{ __('Payée le') }}</span>
                                        <span class="text-teal-600">{{ $inv->paid_at->locale('fr_FR')->translatedFormat('j M Y') }}</span>
                                    </div>
                                @endif
                            </div>

                            <div class="mt-6">
                                <span class="inline-flex items-center rounded-full px-3 py-1.5 text-sm font-semibold {{ $statusConfig['class'] }}">
                                    {{ $statusConfig['label'] }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="flex justify-end border-t border-slate-100 px-10 py-5">
                    <flux:button variant="ghost" wire:click="closeInvoice">
                        {{ __('Fermer') }}
                    </flux:button>
                </div>
            </div>
        </div>
    @endif

</div>
