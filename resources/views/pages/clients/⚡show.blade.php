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

    public bool $showEditModal = false;

    public string $editName = '';

    public string $editPhone = '';

    public string $editPlan = '';

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

        // État du formulaire d'édition
        $this->editName = $company->name;
        $this->editPhone = $company->phone ?? '';
        $this->editPlan = $company->plan ?? '';
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
     * Factures du mois sélectionné, avec client et compteur de relances.
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
            ->with('client')
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

    public function updatedPerPage(): void
    {
        // réinitialise la fenêtre au changement de taille de page
    }

    public function saveEdit(): void
    {
        $this->validate([
            'editName'  => 'required|min:2|max:255',
            'editPhone' => 'nullable|max:25',
            'editPlan'  => 'required|in:basique,essentiel,premium',
        ]);

        $this->company->update([
            'name'  => $this->editName,
            'phone' => $this->editPhone ?: null,
            'plan'  => $this->editPlan,
        ]);

        // Recalcule les propriétés d'affichage
        $nameParts = collect(explode(' ', $this->editName));
        $this->initials = $nameParts->map(fn ($w) => strtoupper($w[0] ?? ''))->take(2)->join('');
        $this->companyRef = $this->initials.'-'.strtoupper(substr($this->company->id, -4));

        $this->showEditModal = false;
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

                <flux:button variant="ghost" class="border border-slate-200" icon="pencil-square"
                    wire:click="$set('showEditModal', true)">
                    {{ __('Modifier') }}
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
            <p class="text-xs font-medium text-slate-500">
                {{ __('CA facturé') }}
                ({{ ucfirst(now()->setYear($this->selectedYear())->setMonth($this->selectedMonth())->locale('fr_FR')->translatedFormat('M')) }})
            </p>
            <p class="mt-2 text-2xl font-bold tracking-tight text-ink">
                {{ number_format($this->stats['billed_month'], 0, ',', ' ') }} F
            </p>
        </section>

        {{-- Encaissé --}}
        <section class="app-shell-panel p-5">
            <p class="text-xs font-medium text-slate-500">{{ __('Encaissé') }}</p>
            <p class="mt-2 text-2xl font-bold tracking-tight text-accent">
                {{ number_format($this->stats['collected'], 0, ',', ' ') }} F
            </p>
        </section>

        {{-- En attente --}}
        <section class="app-shell-panel p-5">
            <p class="text-xs font-medium text-slate-500">{{ __('En attente') }}</p>
            <p @class([
                'mt-2 text-2xl font-bold tracking-tight',
                'text-rose-500'  => $this->statusValue === 'critique',
                'text-amber-500' => $this->statusValue !== 'critique' && $this->stats['pending_amount'] > 0,
                'text-ink'       => $this->stats['pending_amount'] === 0,
            ])>
                {{ number_format($this->stats['pending_amount'], 0, ',', ' ') }} F
            </p>
            @if ($this->stats['pending_count'] > 0)
                <p class="mt-1 text-xs text-slate-500">
                    {{ $this->stats['pending_count'] }} {{ $this->stats['pending_count'] > 1 ? __('factures impayées') : __('facture impayée') }}
                </p>
            @endif
        </section>

        {{-- Délai moyen · Taux --}}
        <section class="app-shell-panel p-5">
            <p class="text-xs font-medium text-slate-500">{{ __('Délai moyen · Taux') }}</p>
            <p class="mt-2 text-2xl font-bold tracking-tight text-amber-500">
                {{ $this->stats['avg_days'] }}j
                <span class="text-slate-400">·</span>
                {{ $this->stats['recovery_rate'] }}%
            </p>
            <p class="mt-1 text-xs text-slate-500">{{ __('Recouvrement') }}</p>
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
                        <tr class="transition hover:bg-slate-50/60">
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

    {{-- ─── Modal édition ────────────────────────────────────────────────── --}}
    <flux:modal wire:model="showEditModal" class="max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Modifier le client') }}</flux:heading>
                <flux:text class="mt-1 text-slate-500">{{ __('Informations de base de la PME.') }}</flux:text>
            </div>

            <form wire:submit="saveEdit" class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Nom de la société') }}</flux:label>
                    <flux:input wire:model="editName" />
                    <flux:error name="editName" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Téléphone') }}</flux:label>
                    <flux:input wire:model="editPhone" type="tel" />
                    <flux:error name="editPhone" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Plan') }}</flux:label>
                    <flux:select wire:model="editPlan">
                        <flux:select.option value="basique">{{ __('Basique') }}</flux:select.option>
                        <flux:select.option value="essentiel">{{ __('Essentiel') }}</flux:select.option>
                        <flux:select.option value="premium">{{ __('Premium') }}</flux:select.option>
                    </flux:select>
                    <flux:error name="editPlan" />
                </flux:field>

                <div class="flex justify-end gap-3 pt-2">
                    <flux:button variant="ghost" wire:click="$set('showEditModal', false)" type="button">
                        {{ __('Annuler') }}
                    </flux:button>
                    <flux:button variant="primary" type="submit">
                        {{ __('Enregistrer') }}
                    </flux:button>
                </div>
            </form>

            <div class="border-t border-slate-100 pt-4">
                <p class="mb-3 text-sm text-slate-500">{{ __('Zone dangereuse') }}</p>
                <flux:button
                    variant="danger"
                    wire:click="archive"
                    wire:confirm="{{ __('Archiver ce client ? Cette action retirera la PME de votre portefeuille actif.') }}"
                >
                    {{ __('Archiver le client') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

</div>
