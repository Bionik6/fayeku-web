<?php

use App\Enums\PME\InvoiceStatus;
use App\Models\Auth\AccountantCompany;
use App\Models\PME\Invoice;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Facture')] class extends Component {
    public Invoice $invoice;

    public function mount(Invoice $invoice): void
    {
        $firm = auth()->user()->accountantFirm();

        $relation = $firm
            ? AccountantCompany::query()
                ->where('accountant_firm_id', $firm->id)
                ->where('sme_company_id', $invoice->company_id)
                ->whereNull('ended_at')
                ->exists()
            : false;

        abort_unless($relation, 403);

        $invoice->load(['company', 'client', 'lines', 'payments', 'reminders']);

        $this->invoice = $invoice;
    }

    #[Computed]
    public function statusDisplay(): array
    {
        return $this->invoice->status->display();
    }

    #[Computed]
    public function remainingAmount(): int
    {
        return max(0, (int) $this->invoice->total - (int) $this->invoice->amount_paid);
    }

    #[Computed]
    public function sentRemindersCount(): int
    {
        return $this->invoice->reminders->count();
    }

    #[Computed]
    public function delayDays(): int
    {
        $isPaid    = $this->invoice->status === InvoiceStatus::Paid;
        $isOverdue = $this->invoice->status === InvoiceStatus::Overdue;

        if ($isPaid && $this->invoice->paid_at) {
            return $this->invoice->paid_at->gt($this->invoice->due_at)
                ? (int) $this->invoice->due_at->diffInDays($this->invoice->paid_at)
                : 0;
        }

        return $isOverdue ? (int) $this->invoice->due_at->diffInDays(now()) : 0;
    }
}; ?>

@php
    $inv      = $this->invoice;
    $status   = $this->statusDisplay;
    $remaining = $this->remainingAmount;
@endphp

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- ─── En-tête ──────────────────────────────────────────────────────── --}}
    <section class="app-shell-panel overflow-hidden">
        <div class="flex flex-col gap-5 p-6 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <a href="{{ route('clients.show', $inv->company_id) }}" wire:navigate class="text-sm font-semibold text-slate-500 transition hover:text-primary">
                    {{ __('← Retour à la fiche client') }}
                </a>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <h2 class="text-3xl font-semibold tracking-tight text-ink">
                        {{ __('Facture') }} {{ $inv->reference ?? '—' }}
                    </h2>
                    <span class="inline-flex whitespace-nowrap items-center rounded-full px-3 py-1 text-sm font-semibold {{ $status['class'] }}">
                        {{ __($status['label']) }}
                    </span>
                </div>
                <p class="mt-2 text-sm text-slate-500">
                    @if ($inv->client)
                        <span class="font-medium text-ink">{{ $inv->client->name }}</span>
                        ·
                    @endif
                    {{ __('Émise le') }} {{ $inv->issued_at ? format_date($inv->issued_at) : '—' }}
                    @if ($inv->due_at)
                        · {{ __('échéance') }} {{ format_date($inv->due_at) }}
                        @if ($this->delayDays > 0)
                            <span @class([
                                'ml-1 font-medium',
                                'text-rose-600'  => $this->delayDays >= 60,
                                'text-amber-600' => $this->delayDays < 60,
                            ])>({{ __('retard :days j', ['days' => $this->delayDays]) }})</span>
                        @endif
                    @endif
                </p>
            </div>
        </div>
    </section>

    {{-- ─── KPIs ─────────────────────────────────────────────────────────── --}}
    @php
        $hasRemaining = $remaining > 0 && $inv->status !== InvoiceStatus::Draft;
    @endphp
    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="app-shell-stat-card">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-teal">{{ __('Montant TTC') }}</p>
            <p class="mt-2 text-3xl font-semibold tracking-tight text-ink">
                {{ format_money($inv->total, $inv->currency) }}
            </p>
        </article>

        <article class="app-shell-stat-card">
            <p @class([
                'text-sm font-semibold uppercase tracking-[0.2em]',
                'text-rose-600'   => $hasRemaining,
                'text-slate-500'  => ! $hasRemaining,
            ])>{{ __('Reste dû') }}</p>
            <p @class([
                'mt-2 text-3xl font-semibold tracking-tight',
                'text-rose-600' => $hasRemaining,
                'text-ink'      => ! $hasRemaining,
            ])>
                {{ format_money($remaining, $inv->currency) }}
            </p>
        </article>

        <article class="app-shell-stat-card">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-teal">{{ __('Relances') }}</p>
            <p class="mt-2 flex items-baseline gap-2">
                <span class="text-3xl font-semibold tracking-tight text-ink">{{ $this->sentRemindersCount }}</span>
                @if ($this->sentRemindersCount > 0)
                    <span class="text-sm text-slate-500">{{ __('envoyée(s)') }}</span>
                @endif
            </p>
            @if ($this->sentRemindersCount === 0)
                <p class="mt-1 text-sm text-slate-500">{{ __('Aucune pour le moment') }}</p>
            @endif
        </article>

        <article class="app-shell-stat-card">
            @if ($inv->status === InvoiceStatus::Paid && $inv->paid_at)
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-teal">{{ __('Payée le') }}</p>
                <p class="mt-2 text-xl font-semibold tracking-tight text-ink">{{ format_date($inv->paid_at) }}</p>
                @if ($this->delayDays > 0)
                    <p class="mt-1 text-sm text-amber-600">{{ __('Retard :days j', ['days' => $this->delayDays]) }}</p>
                @else
                    <p class="mt-1 text-sm text-teal-600">{{ __('Dans les délais') }}</p>
                @endif
            @elseif ($inv->due_at)
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-teal">{{ __('Échéance') }}</p>
                <p class="mt-2 text-xl font-semibold tracking-tight text-ink">{{ format_date($inv->due_at) }}</p>
                @if ($this->delayDays > 0)
                    <p @class(['mt-1 text-sm font-medium', 'text-rose-600' => $this->delayDays >= 60, 'text-amber-600' => $this->delayDays < 60])>
                        {{ __('Retard :days j', ['days' => $this->delayDays]) }}
                    </p>
                @endif
            @else
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-400">{{ __('Échéance') }}</p>
                <p class="mt-2 text-xl font-semibold text-slate-400">—</p>
            @endif
        </article>
    </section>

    {{-- ─── Corps 2 colonnes ─────────────────────────────────────────────── --}}
    <section class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Colonne gauche : Aperçu / Paiements / Activité --}}
        <div class="flex flex-col gap-6 lg:col-span-2">

            {{-- Aperçu facture --}}
            <article class="app-shell-panel p-6">
                <div>
                    <h3 class="text-lg font-semibold text-ink">{{ __('Aperçu de la facture') }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ __('Les informations envoyées au client.') }}</p>
                </div>
                <div class="mt-6">
                    <x-invoices.preview-card :invoice="$inv" :show-client="false" />
                </div>
            </article>

            {{-- Paiements enregistrés --}}
            @php $allPayments = $inv->payments->sortByDesc('paid_at'); @endphp
            @if ($inv->status !== InvoiceStatus::Draft || $allPayments->isNotEmpty())
                <article class="app-shell-panel p-6">
                    <div>
                        <h3 class="text-lg font-semibold text-ink">{{ __('Paiements enregistrés') }}</h3>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ __('Cumulé') }} : {{ format_money($inv->amount_paid, $inv->currency) }} / {{ format_money($inv->total, $inv->currency) }}
                        </p>
                    </div>

                    @if ($allPayments->isNotEmpty())
                        <div class="mt-5 overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-slate-100 text-left">
                                        <th class="pb-2 pr-4 text-sm font-semibold text-slate-500">{{ __('Date') }}</th>
                                        <th class="pb-2 px-4 text-sm font-semibold text-slate-500">{{ __('Méthode') }}</th>
                                        <th class="pb-2 px-4 text-sm font-semibold text-slate-500">{{ __('Référence') }}</th>
                                        <th class="pb-2 px-4 text-sm font-semibold text-slate-500">{{ __('Type') }}</th>
                                        <th class="pb-2 pl-4 text-right text-sm font-semibold text-slate-500">{{ __('Montant') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    @foreach ($allPayments as $payment)
                                        <tr wire:key="payment-{{ $payment->id }}">
                                            <td class="py-3 pr-4 text-slate-600 whitespace-nowrap">{{ format_date($payment->paid_at) }}</td>
                                            <td class="py-3 px-4 text-slate-600">{{ __($payment->method->label()) }}</td>
                                            <td class="py-3 px-4 text-slate-500">{{ $payment->reference ?? '—' }}</td>
                                            <td class="py-3 px-4">
                                                @if ($payment->is_deposit)
                                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-200">{{ __('Acompte') }}</span>
                                                @else
                                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-200">{{ __('Paiement') }}</span>
                                                @endif
                                            </td>
                                            <td class="py-3 pl-4 text-right font-semibold text-ink whitespace-nowrap">
                                                {{ format_money($payment->amount, $inv->currency) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="mt-5 rounded-xl border border-dashed border-slate-200 bg-slate-50/60 px-5 py-6 text-center text-sm text-slate-500">
                            {{ __('Aucun paiement enregistré pour cette facture.') }}
                        </div>
                    @endif
                </article>
            @endif

            {{-- Activité --}}
            <article class="app-shell-panel p-6">
                <div>
                    <h3 class="text-lg font-semibold text-ink">{{ __('Activité') }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ __('Toute la vie de cette facture : création, échéance, relances et paiements.') }}</p>
                </div>
                <div class="mt-5">
                    <x-invoices.activity-feed :invoice="$inv" />
                </div>
            </article>

        </div>

        {{-- Colonne droite : Client + Actions (sticky) --}}
        <div class="flex w-full flex-col gap-6">

            {{-- Carte client --}}
            <x-invoices.client-card :invoice="$inv" />

            {{-- Actions --}}
            <article class="app-shell-panel p-6 lg:sticky lg:top-6">
                <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ __('Actions') }}</h3>

                <div class="mt-4 space-y-2">
                    <a
                        href="{{ route('pme.invoices.pdf', $inv) }}"
                        target="_blank"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-strong"
                    >
                        <flux:icon name="eye" class="size-4" />
                        {{ __('Afficher PDF') }}
                    </a>

                    <a
                        href="{{ route('pme.invoices.pdf', $inv) }}"
                        download
                        target="_blank"
                        class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary"
                    >
                        <flux:icon name="arrow-down-tray" class="size-4" />
                        {{ __('Télécharger PDF') }}
                    </a>
                </div>

                <div class="mt-5 border-t border-slate-100 pt-4">
                    <a
                        href="{{ route('clients.show', $inv->company_id) }}"
                        wire:navigate
                        class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary"
                    >
                        <flux:icon name="building-office-2" class="size-4" />
                        {{ __('Voir le client') }}
                    </a>
                </div>
            </article>

        </div>

    </section>

</div>
