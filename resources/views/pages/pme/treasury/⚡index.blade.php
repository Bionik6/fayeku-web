<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Models\Auth\Company;
use App\Enums\PME\ReminderChannel;
use App\Services\PME\ReminderService;
use App\Models\PME\Invoice;
use App\Services\PME\TreasuryService;
use App\Exceptions\Shared\QuotaExceededException;

new #[Title('Trésorerie')] #[Layout('layouts::pme')] class extends Component
{
    #[Url(as: 'period')]
    public string $period = '90d';

    public ?Company $company = null;

    public ?string $selectedInvoiceId = null;

    public function mount(): void
    {
        $this->company = auth()->user()->smeCompany();
        $this->setPeriod($this->period);
    }

    public function setPeriod(string $period): void
    {
        $this->period = in_array($period, ['30d', '90d'], true) ? $period : '90d';
        unset($this->dashboardData);
    }

    public function viewInvoice(string $invoiceId): void
    {
        abort_unless($this->company, 403);

        Invoice::query()
            ->where('company_id', $this->company->id)
            ->findOrFail($invoiceId);

        $this->selectedInvoiceId = $invoiceId;
    }

    public function closeInvoice(): void
    {
        $this->selectedInvoiceId = null;
    }

    public function sendReminder(string $invoiceId): void
    {
        abort_unless($this->company, 403);

        $invoice = Invoice::query()
            ->with('client')
            ->where('company_id', $this->company->id)
            ->findOrFail($invoiceId);

        if (! $invoice->canReceiveReminder()) {
            $this->dispatch('toast', type: 'warning', title: __('Cette facture ne peut plus être relancée.'));

            return;
        }

        if (now()->isWeekend()) {
            $this->dispatch('toast', type: 'warning', title: __('Les relances ne peuvent être envoyées qu\'en jour ouvré (lundi au vendredi).'));

            return;
        }

        $channel = filled($invoice->client?->phone)
            ? ReminderChannel::WhatsApp
            : ReminderChannel::Email;

        if (! $this->hasRecipientForChannel($invoice, $channel)) {
            $this->dispatch('toast', type: 'warning', title: $this->missingRecipientMessage($channel));

            return;
        }

        try {
            app(ReminderService::class)->send($invoice, $this->company, $channel, mode: \App\Enums\PME\ReminderMode::Manual);
            unset($this->dashboardData, $this->selectedInvoice);

            $this->dispatch('toast', type: 'success', title: __('Relance envoyée avec succès.'));
        } catch (QuotaExceededException) {
            $this->dispatch('toast', type: 'warning', title: __('Quota de relances atteint pour ce mois.'));
        } catch (RuntimeException) {
            $this->dispatch('toast', type: 'warning', title: __('Service d\'envoi bientôt disponible. Votre relance sera envoyée prochainement.'));
        }
    }

    #[Computed]
    public function dashboardData(): array
    {
        if (! $this->company) {
            return [
                'period' => $this->period,
                'period_label' => $this->period === '30d' ? '30 jours' : '90 jours',
                'subtitle' => 'Vision 90 jours',
                'disclaimer' => 'Prévision des encaissements uniquement, hors sorties de trésorerie.',
                'kpis' => [
                    'collected_amount' => 0,
                    'expected_inflows' => 0,
                    'average_collection_days' => 0,
                    'at_risk_amount' => 0,
                ],
                'forecast_cards' => [],
                'rows' => [],
                'recommendations' => [],
            ];
        }

        return app(TreasuryService::class)->dashboard($this->company, $this->period);
    }

    #[Computed]
    public function exportUrl(): string
    {
        return route('pme.treasury.export', ['period' => $this->period]);
    }

    #[Computed]
    public function selectedInvoice(): ?Invoice
    {
        if (! $this->selectedInvoiceId || ! $this->company) {
            return null;
        }

        return Invoice::query()
            ->with(['client', 'lines'])
            ->where('company_id', $this->company->id)
            ->whereKey($this->selectedInvoiceId)
            ->first();
    }

    /** @return array<string, string> */
    public function periodOptions(): array
    {
        return [
            '30d' => '30 jours',
            '90d' => '90 jours',
        ];
    }

private function hasRecipientForChannel(Invoice $invoice, ReminderChannel $channel): bool
    {
        return match ($channel) {
            ReminderChannel::WhatsApp, ReminderChannel::Sms => filled($invoice->client?->phone),
            ReminderChannel::Email => filled($invoice->client?->email),
        };
    }

    private function missingRecipientMessage(ReminderChannel $channel): string
    {
        return match ($channel) {
            ReminderChannel::WhatsApp => __('Aucun numéro WhatsApp disponible pour ce client.'),
            ReminderChannel::Sms => __('Aucun numéro SMS disponible pour ce client.'),
            ReminderChannel::Email => __('Aucune adresse email disponible pour ce client.'),
        };
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <section class="app-shell-panel overflow-hidden">
        <div class="flex flex-col gap-5 p-6 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.24em] text-teal">{{ __('Trésorerie') }}</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-ink">{{ __('Trésorerie') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $this->dashboardData['subtitle'] }}</p>
                <div class="mt-4 inline-flex max-w-2xl items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    <span class="mt-0.5 inline-flex size-5 shrink-0 items-center justify-center rounded-full bg-amber-200 text-[11px] font-bold text-amber-900">i</span>
                    <p>{{ $this->dashboardData['disclaimer'] }}</p>
                </div>
            </div>

            <div class="flex shrink-0 flex-col items-stretch gap-3 sm:flex-row sm:items-center">
                <div class="inline-flex rounded-2xl border border-slate-200 bg-slate-50 p-1">
                    @foreach ($this->periodOptions() as $value => $label)
                        <button
                            type="button"
                            wire:click="setPeriod('{{ $value }}')"
                            @class([
                                'rounded-xl px-4 py-2 text-sm font-semibold transition',
                                'bg-white text-primary shadow-sm' => $period === $value,
                                'text-slate-500 hover:text-ink' => $period !== $value,
                            ])
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                <a
                    href="{{ $this->exportUrl }}"
                    class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary"
                >
                    <x-app.icon name="download" class="size-4" />
                    {{ __('Exporter') }}
                </a>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @php
            $kpis = [
                [
                    'title' => 'Encaissé à date',
                    'value' => $this->dashboardData['kpis']['collected_amount'],
                    'eyebrow' => 'Réalisé',
                    'tone' => 'emerald',
                    'icon' => 'commissions',
                ],
                [
                    'title' => 'Entrées prévues',
                    'value' => $this->dashboardData['kpis']['expected_inflows'],
                    'eyebrow' => $this->dashboardData['period_label'],
                    'tone' => 'teal',
                    'icon' => 'invoice',
                ],
                [
                    'title' => 'Délai moyen d’encaissement',
                    'value' => $this->dashboardData['kpis']['average_collection_days'],
                    'eyebrow' => 'Historique',
                    'tone' => 'amber',
                    'icon' => 'clients',
                    'suffix' => 'j',
                ],
                [
                    'title' => 'Montant à risque',
                    'value' => $this->dashboardData['kpis']['at_risk_amount'],
                    'eyebrow' => 'Lignes risquées',
                    'tone' => 'rose',
                    'icon' => 'bell',
                ],
            ];
        @endphp

        @foreach ($kpis as $kpi)
            <article class="app-shell-stat-card" wire:key="treasury-kpi-{{ $kpi['title'] }}">
                <div class="flex items-start justify-between">
                    <div @class([
                        'flex size-10 items-center justify-center rounded-xl',
                        'bg-emerald-50 text-emerald-600' => $kpi['tone'] === 'emerald',
                        'bg-teal-50 text-teal-700' => $kpi['tone'] === 'teal',
                        'bg-amber-50 text-amber-700' => $kpi['tone'] === 'amber',
                        'bg-rose-50 text-rose-700' => $kpi['tone'] === 'rose',
                    ])>
                        <x-app.icon :name="$kpi['icon']" class="size-5" />
                    </div>
                    <span @class([
                        'inline-flex whitespace-nowrap items-center rounded-full px-2.5 py-1 text-sm font-semibold',
                        'bg-emerald-50 text-emerald-700' => $kpi['tone'] === 'emerald',
                        'bg-teal-50 text-teal-700' => $kpi['tone'] === 'teal',
                        'bg-amber-50 text-amber-700' => $kpi['tone'] === 'amber',
                        'bg-rose-50 text-rose-700' => $kpi['tone'] === 'rose',
                    ])>
                        {{ $kpi['eyebrow'] }}
                    </span>
                </div>
                <p class="mt-4 text-sm font-medium text-slate-500">{{ $kpi['title'] }}</p>
                <p @class([
                    'mt-1 text-3xl font-semibold tracking-tight',
                    'text-emerald-700' => $kpi['title'] === 'Entrées prévues',
                    'text-rose-600' => $kpi['title'] === 'Montant à risque',
                    'text-ink' => ! in_array($kpi['title'], ['Entrées prévues', 'Montant à risque'], true),
                ])>
                    @if (($kpi['suffix'] ?? null) === 'j')
                        {{ $kpi['value'] > 0 ? $kpi['value'].'j' : '—' }}
                    @else
                        {{ format_money((int) $kpi['value']) }}
                    @endif
                </p>
            </article>
        @endforeach
    </section>

    <section class="space-y-3">
        <div>
            <h3 class="text-lg font-semibold tracking-tight text-ink">{{ __('Prévision d\'encaissement') }}</h3>
        </div>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
            @foreach ($this->dashboardData['forecast_cards'] as $card)
                <article class="app-shell-panel p-6" wire:key="forecast-card-{{ $card['title'] }}">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ $card['title'] }}</p>
                            <h3 class="mt-1 text-xl font-semibold tracking-tight text-ink">{{ $card['caption'] }}</h3>
                        </div>
                        <span class="rounded-full bg-mist px-3 py-1 text-sm font-semibold text-primary">{{ $card['progress'] }}%</span>
                    </div>
                    <p class="mt-5 text-3xl font-semibold tracking-tight text-ink">{{ format_money($card['amount']) }}</p>
                    <p class="mt-2 text-sm text-slate-500">{{ $card['basis'] }}</p>
                    <div class="mt-5 h-2 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-primary transition-all" style="width: {{ $card['progress'] }}%"></div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="app-shell-panel overflow-hidden">
        <div class="border-b border-slate-100 px-6 py-5">
            <div class="flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h3 class="text-xl font-semibold tracking-tight text-ink">{{ __('Entrées attendues') }}</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ __('Prévision basée sur vos factures ouvertes, les retards observés et l’historique des relances.') }}
                    </p>
                </div>
                <p class="text-sm font-medium text-slate-500">
                    {{ count($this->dashboardData['rows']) }} {{ __('ligne(s) sur l’horizon sélectionné') }}
                </p>
            </div>
        </div>

        @if (count($this->dashboardData['rows']) > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50/80 text-slate-500">
                        <tr>
                            <th class="px-6 py-4 font-semibold">{{ __('Document') }}</th>
                            <th class="px-6 py-4 font-semibold">{{ __('Client') }}</th>
                            <th class="px-6 py-4 font-semibold">{{ __('Montant TTC') }}</th>
                            <th class="px-6 py-4 font-semibold">{{ __('Échéance') }}</th>
                            <th class="px-6 py-4 font-semibold">{{ __('Retard actuel') }}</th>
                            <th class="px-6 py-4 font-semibold">{{ __('Niveau de confiance') }}</th>
                            <th class="px-6 py-4 font-semibold">{{ __('Entrée estimée') }}</th>
                            <th class="px-6 py-4 font-semibold">{{ __('Statut') }}</th>
                            <th class="px-6 py-4 font-semibold">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($this->dashboardData['rows'] as $row)
                            <tr wire:key="treasury-row-{{ $row['invoice_id'] }}" class="align-top">
                                <td class="px-6 py-4">
                                    <button
                                        type="button"
                                        wire:click="viewInvoice('{{ $row['invoice_id'] }}')"
                                        class="text-left font-semibold text-ink transition hover:text-primary"
                                    >
                                        {{ $row['document'] }}
                                    </button>
                                    <p class="mt-1 text-sm text-slate-500">{{ $row['base_label'] }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    @if ($row['client_id'])
                                        <a href="{{ route('pme.clients.show', $row['client_id']) }}" wire:navigate class="font-medium text-ink transition hover:text-primary">
                                            {{ $row['client_name'] }}
                                        </a>
                                    @else
                                        <span class="font-medium text-ink">{{ $row['client_name'] }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-ink">{{ format_money($row['total'], compact: true) }}</p>
                                    @if ($row['amount_paid'] > 0)
                                        <p class="mt-1 text-sm text-slate-500">
                                            {{ __('Reste à encaisser') }} {{ format_money($row['remaining'], compact: true) }}
                                        </p>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <p class="font-medium text-ink">{{ $row['due_at_label'] }}</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ __('Entrée estimée') }} {{ $row['estimated_date_label'] }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    <span @class([
                                        'inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-sm font-semibold',
                                        'bg-rose-50 text-rose-700' => $row['days_overdue'] > 0,
                                        'bg-slate-100 text-slate-600' => $row['days_overdue'] === 0,
                                    ])>
                                        {{ $row['delay_label'] }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span @class([
                                        'inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-sm font-semibold',
                                        'bg-emerald-50 text-emerald-700' => $row['confidence_tone'] === 'emerald',
                                        'bg-amber-50 text-amber-700' => $row['confidence_tone'] === 'amber',
                                        'bg-rose-50 text-rose-700' => $row['confidence_tone'] === 'rose',
                                    ])>
                                        {{ $row['confidence_label'] }}
                                    </span>
                                    <p class="mt-2 font-semibold text-ink">{{ $row['confidence_score'] }}%</p>
                                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                        <div
                                            @class([
                                                'h-full rounded-full transition-all',
                                                'bg-emerald-500' => $row['confidence_tone'] === 'emerald',
                                                'bg-amber-500' => $row['confidence_tone'] === 'amber',
                                                'bg-rose-500' => $row['confidence_tone'] === 'rose',
                                            ])
                                            style="width: {{ $row['confidence_score'] }}%"
                                        ></div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-ink">{{ format_money($row['estimated_amount'], compact: true) }}</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ $row['estimated_date_label'] }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    <span @class([
                                        'inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-sm font-semibold',
                                        'bg-teal-50 text-teal-700' => $row['status_tone'] === 'teal',
                                        'bg-amber-50 text-amber-700' => $row['status_tone'] === 'amber',
                                        'bg-rose-50 text-rose-700' => $row['status_tone'] === 'rose',
                                    ])>
                                        {{ $row['status_label'] }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex min-w-[180px] flex-col items-start gap-2">
                                        <button
                                            type="button"
                                            wire:click="sendReminder('{{ $row['invoice_id'] }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="sendReminder"
                                            class="inline-flex items-center rounded-xl bg-primary px-3 py-2 text-sm font-semibold text-white transition hover:bg-primary-strong disabled:cursor-not-allowed disabled:opacity-60"
                                        >
                                            {{ __('Relancer') }}
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="viewInvoice('{{ $row['invoice_id'] }}')"
                                            class="text-sm font-semibold text-slate-600 transition hover:text-primary"
                                        >
                                            {{ __('Ouvrir facture') }}
                                        </button>
                                        @if ($row['client_id'])
                                            <a
                                                href="{{ route('pme.clients.show', $row['client_id']) }}"
                                                wire:navigate
                                                class="text-sm font-semibold text-slate-600 transition hover:text-primary"
                                            >
                                                {{ __('Voir client') }}
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="flex flex-col items-center justify-center p-16 text-center">
                <div class="flex size-14 items-center justify-center rounded-2xl bg-mist text-primary">
                    <x-app.icon name="commissions" class="size-6" />
                </div>
                <h3 class="mt-4 text-lg font-semibold text-ink">{{ __('Aucune entrée attendue sur cet horizon') }}</h3>
                <p class="mt-2 max-w-md text-sm text-slate-500">
                    {{ __('Créez ou envoyez des factures pour commencer à projeter vos encaissements sur 30 ou 90 jours.') }}
                </p>
            </div>
        @endif
    </section>

    <section class="space-y-4">
        <div>
            <h3 class="text-sm font-semibold uppercase tracking-[0.24em] text-slate-500">{{ __('Alertes et recommandations') }}</h3>
        </div>

        @if (count($this->dashboardData['recommendations']) > 0)
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                @foreach ($this->dashboardData['recommendations'] as $card)
                    <article class="app-shell-panel p-8" wire:key="treasury-reco-{{ $card['type'] }}">
                        <div class="flex items-start gap-4">
                            <div
                                @class([
                                    'flex size-14 shrink-0 items-center justify-center rounded-2xl',
                                    'bg-rose-50 text-rose-500' => $card['tone'] === 'rose',
                                    'bg-amber-50 text-amber-500' => $card['tone'] === 'amber',
                                    'bg-emerald-50 text-emerald-500' => $card['tone'] === 'teal',
                                ])
                            >
                                @if ($card['tone'] === 'rose')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-7" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                                    </svg>
                                @elseif ($card['tone'] === 'amber')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-7" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 18h6m-5 3h4m-.54-3a7 7 0 1 0-2.92 0M12 3v1" />
                                    </svg>
                                @else
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-7" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                @endif
                            </div>

                            <div class="min-w-0">
                                <p
                                    @class([
                                        'text-lg font-semibold tracking-tight',
                                        'text-rose-600' => $card['tone'] === 'rose',
                                        'text-amber-600' => $card['tone'] === 'amber',
                                        'text-emerald-600' => $card['tone'] === 'teal',
                                    ])
                                >
                                    {{ $card['title'] }}
                                </p>
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $card['body'] }}</p>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <article class="app-shell-panel p-8">
                <p class="text-base font-semibold text-ink">{{ __('Aucun signal particulier pour le moment') }}</p>
                <p class="mt-2 text-sm text-slate-500">{{ __('Les recommandations apparaîtront dès que vos factures ouvertes et vos historiques de paiement créeront assez de signal.') }}</p>
            </article>
        @endif
    </section>

    @if ($this->selectedInvoice)
        <x-invoices.detail-modal :invoice="$this->selectedInvoice" close-action="closeInvoice" />
    @endif
</div>
