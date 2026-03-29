<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Auth\Models\Company;
use Modules\PME\Collection\Enums\ReminderChannel;
use Modules\PME\Invoicing\Enums\InvoiceStatus;
use Modules\PME\Invoicing\Models\Invoice;

new #[Title('Tableau de bord')] #[Layout('layouts::pme')] class extends Component {
    public ?Company $company = null;

    public string $currentMonth = '';

    public string $planLabel = '';

    public int $invoicedAmount = 0;

    public int $collectedAmount = 0;

    public int $pendingAmount = 0;

    public int $overdueAmount = 0;

    public int $overdueCount = 0;

    public int $avgPaymentDays = 0;

    public int $collectedPct = 0;

    public int $pendingPct = 0;

    public int $overduePct = 0;

    /** @var array<int, array<string, mixed>> */
    public array $recentInvoices = [];

    /** @var array<int, array<string, mixed>> */
    public array $urgentOverdue = [];

    public function mount(): void
    {
        $this->currentMonth = ucfirst(now()->locale('fr_FR')->translatedFormat('F Y'));
        $this->company = auth()->user()->smeCompany();

        if (! $this->company) {
            return;
        }

        $this->planLabel = ucfirst($this->company->subscription?->plan_slug ?? $this->company->plan ?? '');

        $allInvoices = Invoice::query()
            ->where('company_id', $this->company->id)
            ->whereNotIn('status', [InvoiceStatus::Cancelled, InvoiceStatus::Draft])
            ->with(['client', 'reminders'])
            ->get();

        // KPI: CA facturé ce mois (toutes factures hors annulées/brouillons émises ce mois)
        $this->invoicedAmount = $allInvoices
            ->filter(fn ($inv) => $inv->issued_at->isCurrentMonth())
            ->sum('total');

        // KPI: Encaissé (total amount_paid toutes factures)
        $this->collectedAmount = $allInvoices->sum('amount_paid');

        // KPI: À encaisser (sent, certified, partially_paid)
        $pendingStatuses = [InvoiceStatus::Sent, InvoiceStatus::Certified, InvoiceStatus::CertificationFailed, InvoiceStatus::PartiallyPaid];
        $this->pendingAmount = $allInvoices
            ->filter(fn ($inv) => in_array($inv->status, $pendingStatuses))
            ->sum(fn ($inv) => $inv->total - $inv->amount_paid);

        // KPI: En retard
        $overdueInvoices = $allInvoices->filter(fn ($inv) => $inv->status === InvoiceStatus::Overdue);
        $this->overdueAmount = $overdueInvoices->sum(fn ($inv) => $inv->total - $inv->amount_paid);
        $this->overdueCount = $overdueInvoices->count();

        // Barre trésorerie
        $treasuryTotal = $this->collectedAmount + $this->pendingAmount + $this->overdueAmount;
        if ($treasuryTotal > 0) {
            $this->collectedPct = (int) round($this->collectedAmount / $treasuryTotal * 100);
            $this->pendingPct = (int) round($this->pendingAmount / $treasuryTotal * 100);
            $this->overduePct = max(0, 100 - $this->collectedPct - $this->pendingPct);
        }

        // Délai moyen paiement
        $paidInvoices = $allInvoices->filter(fn ($inv) => $inv->status === InvoiceStatus::Paid && $inv->paid_at);
        if ($paidInvoices->isNotEmpty()) {
            $totalDays = $paidInvoices->sum(fn ($inv) => $inv->issued_at->diffInDays($inv->paid_at));
            $this->avgPaymentDays = (int) round($totalDays / $paidInvoices->count());
        }

        // Activité récente (5 dernières factures)
        $this->recentInvoices = $allInvoices
            ->sortByDesc('issued_at')
            ->take(5)
            ->values()
            ->map(function ($inv) {
                $days = (int) now()->diffInDays($inv->issued_at);
                $dateLabel = match (true) {
                    $days === 0 => "Aujourd'hui",
                    $days === 1 => 'Hier',
                    default => 'Il y a '.$days.'j',
                };

                return [
                    'id'         => $inv->id,
                    'reference'  => $inv->reference,
                    'client'     => $inv->client?->name ?? '—',
                    'date_label' => $dateLabel,
                    'total'      => $inv->total,
                    'status'     => $inv->status->value,
                ];
            })
            ->toArray();

        // Impayés urgents (overdue, plus anciens en premier, limit 5)
        $this->urgentOverdue = $overdueInvoices
            ->sortBy('due_at')
            ->take(5)
            ->values()
            ->map(function ($inv) {
                $delayDays = $inv->due_at ? (int) now()->diffInDays($inv->due_at) : 0;
                $isCritical = $delayDays > 60;

                $lastReminder = $inv->reminders->sortByDesc('sent_at')->first();
                $lastReminderLabel = '—';
                if ($lastReminder && $lastReminder->sent_at) {
                    $reminderDays = (int) now()->diffInDays($lastReminder->sent_at);
                    $channelLabel = match ($lastReminder->channel) {
                        ReminderChannel::WhatsApp => 'WhatsApp',
                        ReminderChannel::Sms => 'SMS',
                        ReminderChannel::Email => 'Email',
                    };
                    $lastReminderLabel = 'Il y a '.$reminderDays.'j · '.$channelLabel;
                } elseif ($inv->reminders->isEmpty()) {
                    $lastReminderLabel = 'Aucune relance';
                }

                return [
                    'id'                  => $inv->id,
                    'reference'           => $inv->reference,
                    'client'              => $inv->client?->name ?? '—',
                    'total'               => $inv->total - $inv->amount_paid,
                    'issued_at'           => $inv->issued_at->translatedFormat('j M. Y'),
                    'delay_days'          => $delayDays,
                    'last_reminder_label' => $lastReminderLabel,
                    'is_critical'         => $isCritical,
                ];
            })
            ->toArray();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- Bloc A — En-tête --}}
    <section class="app-shell-panel overflow-hidden">
        <div class="flex flex-col gap-4 p-6 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.24em] text-teal">
                    {{ $currentMonth }}
                    @if ($planLabel)
                        · {{ $planLabel }}
                    @endif
                </p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-ink">
                    {{ __('Bonjour,') }} {{ $company?->name ?? auth()->user()->first_name }}
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ __('Voici un aperçu de votre activité.') }}
                </p>
            </div>

            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <a
                    href="{{ route('pme.invoices.create') }}"
                    wire:navigate
                    class="inline-flex items-center gap-2 rounded-2xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-strong"
                >
                    <flux:icon name="plus" class="size-4" />
                    {{ __('Nouvelle facture') }}
                </a>
                <a
                    href="{{ route('pme.invoices.index') }}"
                    wire:navigate
                    class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary"
                >
                    {{ __('Nouveau devis') }}
                </a>
                <a
                    href="{{ route('pme.treasury.index') }}"
                    wire:navigate
                    class="text-sm font-semibold text-slate-500 transition hover:text-primary"
                >
                    {{ __('Voir l\'analyse') }} →
                </a>
            </div>
        </div>
    </section>

    {{-- Bloc B — 4 KPI cards --}}
    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">

        {{-- CA facturé --}}
        <a href="{{ route('pme.invoices.index') }}" wire:navigate class="app-shell-stat-card block transition hover:shadow-[0_20px_45px_rgba(15,23,42,0.1)]">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-teal-50">
                    <flux:icon name="document-text" class="size-5 text-primary" />
                </div>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-sm font-medium text-slate-500">
                    {{ __('Ce mois') }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('CA facturé') }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ $currentMonth }}</p>
            <p class="mt-1 text-3xl font-semibold tracking-tight text-ink">
                @if ($invoicedAmount > 0)
                    {{ number_format($invoicedAmount, 0, ',', ' ') }} FCFA
                @else
                    —
                @endif
            </p>
        </a>

        {{-- Encaissé --}}
        <a href="{{ route('pme.invoices.index') }}" wire:navigate class="app-shell-stat-card block transition hover:shadow-[0_20px_45px_rgba(15,23,42,0.1)]">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-emerald-50">
                    <flux:icon name="check-circle" class="size-5 text-accent" />
                </div>
                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-sm font-semibold text-emerald-700">
                    {{ __('Encaissé') }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Montant encaissé') }}</p>
            <p class="mt-1 text-sm text-slate-500">
                @if ($invoicedAmount > 0)
                    {{ $collectedPct }}% {{ __('de recouvrement') }}
                @else
                    {{ __('Total perçu') }}
                @endif
            </p>
            <p class="mt-1 text-3xl font-semibold tracking-tight text-accent">
                @if ($collectedAmount > 0)
                    {{ number_format($collectedAmount, 0, ',', ' ') }} FCFA
                @else
                    —
                @endif
            </p>
        </a>

        {{-- À encaisser --}}
        <a href="{{ route('pme.invoices.index') }}" wire:navigate class="app-shell-stat-card block transition hover:shadow-[0_20px_45px_rgba(15,23,42,0.1)]">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-amber-50">
                    <flux:icon name="clock" class="size-5 text-amber-500" />
                </div>
                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-sm font-semibold text-amber-700">
                    {{ __('En attente') }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('À encaisser') }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ __('Factures en attente') }}</p>
            <p class="mt-1 text-3xl font-semibold tracking-tight text-amber-500">
                @if ($pendingAmount > 0)
                    {{ number_format($pendingAmount, 0, ',', ' ') }} FCFA
                @else
                    —
                @endif
            </p>
        </a>

        {{-- En retard --}}
        <a href="{{ route('pme.collection.index') }}" wire:navigate class="app-shell-stat-card block transition hover:shadow-[0_20px_45px_rgba(15,23,42,0.1)]">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-rose-50">
                    <flux:icon name="exclamation-triangle" class="size-5 text-rose-500" />
                </div>
                <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-1 text-sm font-semibold text-rose-700">
                    &gt; 30j
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('En retard') }}</p>
            <p class="mt-1 text-sm text-slate-500">
                @if ($overdueCount > 0)
                    {{ $overdueCount }} {{ $overdueCount > 1 ? __('factures critiques') : __('facture critique') }}
                @else
                    {{ __('Aucun impayé') }}
                @endif
            </p>
            <p class="mt-1 text-3xl font-semibold tracking-tight text-rose-500">
                @if ($overdueAmount > 0)
                    {{ number_format($overdueAmount, 0, ',', ' ') }} FCFA
                @else
                    —
                @endif
            </p>
        </a>

    </section>

    {{-- Blocs C + D — Trésorerie & Activité récente --}}
    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.65fr)_minmax(320px,0.85fr)]">

        {{-- Bloc C — Trésorerie 30 jours --}}
        <div class="app-shell-panel p-6">
            <div class="flex items-center justify-between gap-4">
                <h3 class="text-xl font-semibold tracking-tight text-ink">{{ __('Trésorerie · Vision 30 jours') }}</h3>
                <a href="{{ route('pme.treasury.index') }}" wire:navigate
                   class="text-sm font-semibold text-primary hover:underline">
                    {{ __('Voir détail') }} →
                </a>
            </div>

            @if ($collectedAmount > 0 || $pendingAmount > 0 || $overdueAmount > 0)
                <div class="mt-5">
                    <p class="text-sm text-slate-500">{{ __('Cash disponible estimé · Mise à jour aujourd\'hui') }}</p>
                    <p class="mt-1 text-4xl font-semibold tracking-tight text-ink">
                        {{ number_format($collectedAmount, 0, ',', ' ') }} FCFA
                    </p>

                    {{-- Barre visuelle tricolore --}}
                    <div class="mt-4 flex h-3 w-full overflow-hidden rounded-full bg-slate-100">
                        @if ($collectedPct > 0)
                            <div class="h-full bg-accent transition-all" style="width: {{ $collectedPct }}%"></div>
                        @endif
                        @if ($pendingPct > 0)
                            <div class="h-full bg-amber-400 transition-all" style="width: {{ $pendingPct }}%"></div>
                        @endif
                        @if ($overduePct > 0)
                            <div class="h-full bg-rose-400 transition-all" style="width: {{ $overduePct }}%"></div>
                        @endif
                    </div>

                    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-500">
                        <span class="flex items-center gap-1.5">
                            <span class="size-2 rounded-full bg-accent"></span>
                            {{ __('Encaissé') }} {{ $collectedPct }}%
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="size-2 rounded-full bg-amber-400"></span>
                            {{ __('Attendu') }} {{ $pendingPct }}%
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="size-2 rounded-full bg-rose-400"></span>
                            {{ __('En retard') }}
                        </span>
                    </div>

                    <div class="mt-5 grid grid-cols-2 gap-4 border-t border-slate-100 pt-5">
                        <div>
                            <p class="text-sm text-slate-500">{{ __('Entrées prévues (30j)') }}</p>
                            <p class="mt-0.5 text-lg font-semibold text-accent">
                                +{{ number_format($pendingAmount, 0, ',', ' ') }} FCFA
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-slate-500">{{ __('Délai moyen paiement') }}</p>
                            <p class="mt-0.5 text-lg font-semibold text-ink">
                                @if ($avgPaymentDays > 0)
                                    {{ $avgPaymentDays }} {{ __('jours') }}
                                @else
                                    —
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            @else
                <p class="mt-4 text-sm text-slate-500">{{ __('Aucune donnée de trésorerie pour le moment.') }}</p>
            @endif
        </div>

        {{-- Bloc D — Activité récente --}}
        <div class="app-shell-panel p-6">
            <div class="flex items-center justify-between gap-4">
                <h3 class="text-xl font-semibold tracking-tight text-ink">{{ __('Activité récente') }}</h3>
                <a href="{{ route('pme.invoices.index') }}" wire:navigate
                   class="text-sm font-semibold text-primary hover:underline">
                    {{ __('Voir tout') }} →
                </a>
            </div>

            @if (count($recentInvoices) > 0)
                <div class="mt-4 divide-y divide-slate-100">
                    @foreach ($recentInvoices as $inv)
                        @php
                            $statusConfig = match ($inv['status']) {
                                'paid'          => ['label' => 'Encaissée',  'class' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'],
                                'sent',
                                'certified'     => ['label' => 'Envoyée',    'class' => 'bg-blue-50 text-blue-700 ring-blue-600/20'],
                                'overdue'       => ['label' => 'En retard',  'class' => 'bg-rose-50 text-rose-700 ring-rose-600/20'],
                                'partially_paid' => ['label' => 'Part. payée', 'class' => 'bg-amber-50 text-amber-700 ring-amber-600/20'],
                                default         => ['label' => 'À facturer', 'class' => 'bg-slate-100 text-slate-600 ring-slate-600/20'],
                            };
                        @endphp
                        <div wire:key="activity-{{ $inv['id'] }}" class="flex items-center gap-3 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-ink">{{ $inv['reference'] }}</p>
                                <p class="truncate text-sm text-slate-500">{{ $inv['client'] }} · {{ $inv['date_label'] }}</p>
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-1">
                                <p class="text-sm font-semibold text-ink">{{ number_format($inv['total'], 0, ',', ' ') }} FCFA</p>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-sm font-semibold ring-1 ring-inset {{ $statusConfig['class'] }}">
                                    {{ $statusConfig['label'] }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="mt-4 text-sm text-slate-500">{{ __('Aucune activité récente.') }}</p>
            @endif
        </div>

    </section>

    {{-- Bloc E — Impayés urgents --}}
    @if (count($urgentOverdue) > 0)
        <section class="app-shell-panel overflow-hidden">
            <div class="flex items-center justify-between gap-4 p-6 pb-4">
                <h3 class="text-xl font-semibold tracking-tight text-ink">{{ __('Impayés urgents') }}</h3>
                <a href="{{ route('pme.collection.index') }}" wire:navigate
                   class="text-sm font-semibold text-primary hover:underline">
                    {{ __('Gérer les relances') }} →
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-y border-slate-100 bg-slate-50/80">
                            <th class="px-6 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Facture') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Client') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Montant TTC') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Émise le') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Retard') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Dernière relance') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Statut') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($urgentOverdue as $row)
                            <tr wire:key="overdue-{{ $row['id'] }}" class="transition hover:bg-slate-50/60">
                                <td class="px-6 py-4 font-semibold text-ink">{{ $row['reference'] }}</td>
                                <td class="px-4 py-4 font-semibold text-ink">{{ $row['client'] }}</td>
                                <td class="px-4 py-4 font-semibold text-ink">{{ number_format($row['total'], 0, ',', ' ') }} F</td>
                                <td class="px-4 py-4 text-slate-500">{{ $row['issued_at'] }}</td>
                                <td class="px-4 py-4">
                                    <span @class([
                                        'font-bold',
                                        'text-rose-500'  => $row['is_critical'],
                                        'text-amber-500' => ! $row['is_critical'],
                                    ])>
                                        J+{{ $row['delay_days'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-slate-500">{{ $row['last_reminder_label'] }}</td>
                                <td class="px-4 py-4">
                                    <span @class([
                                        'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-sm font-semibold ring-1 ring-inset',
                                        'bg-rose-50 text-rose-700 ring-rose-600/20'    => $row['is_critical'],
                                        'bg-amber-50 text-amber-700 ring-amber-600/20' => ! $row['is_critical'],
                                    ])>
                                        <span @class([
                                            'size-1.5 rounded-full',
                                            'bg-rose-500'  => $row['is_critical'],
                                            'bg-amber-500' => ! $row['is_critical'],
                                        ])></span>
                                        @if ($row['is_critical']) {{ __('Critique') }} @else {{ __('Attention') }} @endif
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-2">
                                        <a
                                            href="{{ route('pme.collection.index') }}"
                                            wire:navigate
                                            class="inline-flex items-center rounded-xl bg-primary px-4 py-1.5 text-sm font-semibold text-white transition hover:bg-primary-strong"
                                        >
                                            {{ __('Relancer') }}
                                        </a>
                                        <a
                                            href="{{ route('pme.invoices.index') }}"
                                            wire:navigate
                                            class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-1.5 text-sm font-semibold text-slate-600 transition hover:border-primary/30 hover:text-primary"
                                        >
                                            {{ __('Voir') }}
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    {{-- État vide (aucune facture) --}}
    @if (count($recentInvoices) === 0 && count($urgentOverdue) === 0)
        <section class="app-shell-panel flex flex-col items-center justify-center p-16 text-center">
            <div class="flex size-14 items-center justify-center rounded-2xl bg-mist">
                <x-app.icon name="dashboard" class="size-6 text-primary" />
            </div>
            <h3 class="mt-4 text-lg font-semibold text-ink">{{ __('Bienvenue sur votre tableau de bord') }}</h3>
            <p class="mt-2 max-w-sm text-sm text-slate-500">
                {{ __('Commencez par créer votre première facture pour suivre vos encaissements.') }}
            </p>
            <a
                href="{{ route('pme.invoices.index') }}"
                wire:navigate
                class="mt-6 inline-flex items-center gap-2 rounded-2xl bg-primary px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-strong"
            >
                <flux:icon name="plus" class="size-4" />
                {{ __('Créer ma première facture') }}
            </a>
        </section>
    @endif

</div>
