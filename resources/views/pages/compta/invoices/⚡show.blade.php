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

        $invoice->load(['company', 'client', 'lines', 'payments']);

        $this->invoice = $invoice;
    }

    #[Computed]
    public function statusConfig(): array
    {
        return $this->invoice->status->display();
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

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- ─── En-tête ──────────────────────────────────────────────────────── --}}
    <section class="app-shell-panel p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">

            {{-- Identité --}}
            <div>
                <nav class="mb-3 flex items-center gap-2 text-sm text-slate-500">
                    <a href="{{ route('clients.index') }}" wire:navigate class="hover:text-primary">{{ __('Clients') }}</a>
                    <span>/</span>
                    <a href="{{ route('clients.show', $invoice->company_id) }}" wire:navigate class="hover:text-primary">
                        {{ $invoice->company->name ?? __('Client') }}
                    </a>
                    <span>/</span>
                    <span class="font-medium text-ink">{{ $invoice->reference }}</span>
                </nav>

                <div class="flex items-center gap-3">
                    <h2 class="text-2xl font-semibold tracking-tight text-ink">{{ $invoice->reference }}</h2>
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold {{ $this->statusConfig['class'] }}">
                        {{ __($this->statusConfig['label']) }}
                    </span>
                </div>

                <p class="mt-1 text-sm text-slate-500">
                    {{ __('Émise le') }} {{ format_date($invoice->issued_at) }}
                    @if ($invoice->due_at)
                        &nbsp;·&nbsp; {{ __('Échéance le') }} {{ format_date($invoice->due_at) }}
                    @endif
                    @if ($this->delayDays > 0)
                        &nbsp;·&nbsp;
                        <span @class([
                            'font-semibold',
                            'text-rose-500'  => $this->delayDays >= 60,
                            'text-amber-500' => $this->delayDays < 60,
                        ])>
                            {{ __('Retard :days j', ['days' => $this->delayDays]) }}
                        </span>
                    @endif
                </p>
            </div>

            {{-- Actions --}}
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <a
                    href="{{ route('pme.invoices.pdf', $invoice->public_code) }}"
                    target="_blank"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-primary/30 hover:text-primary"
                >
                    <flux:icon name="eye" class="size-4" />
                    {{ __('Afficher PDF') }}
                </a>

                <a
                    href="{{ route('pme.invoices.pdf', $invoice->public_code) }}"
                    download
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-primary/30 hover:text-primary"
                >
                    <flux:icon name="arrow-down-tray" class="size-4" />
                    {{ __('Télécharger PDF') }}
                </a>

                <a
                    href="{{ route('clients.show', $invoice->company_id) }}"
                    wire:navigate
                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-strong"
                >
                    <flux:icon name="building-office-2" class="size-4" />
                    {{ __('Fiche client') }}
                </a>
            </div>
        </div>
    </section>

    {{-- ─── Contenu ──────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Détail lignes --}}
        <div class="app-shell-panel col-span-2 px-8 py-8">
            <x-invoices.preview-card :invoice="$invoice" />
        </div>

        {{-- Récapitulatif --}}
        <div class="space-y-6">
            <div class="app-shell-panel px-6 py-6">
                <p class="mb-4 text-sm font-semibold text-slate-500">{{ __('Récapitulatif') }}</p>

                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-slate-500">{{ __('Montant HT') }}</dt>
                        <dd class="tabular-nums font-medium text-ink">{{ format_money($invoice->subtotal, $invoice->currency) }}</dd>
                    </div>
                    @if ($invoice->discount > 0)
                        @php
                            $discountAmount = ($invoice->discount_type ?? 'percent') === 'fixed'
                                ? $invoice->discount
                                : (int) round($invoice->subtotal * $invoice->discount / 100);
                            $discountLabel = ($invoice->discount_type ?? 'percent') === 'fixed'
                                ? __('Réduction (montant fixe)')
                                : __('Réduction (:rate%)', ['rate' => $invoice->discount]);
                        @endphp
                        <div class="flex justify-between text-emerald-600">
                            <dt>{{ $discountLabel }}</dt>
                            <dd class="tabular-nums font-medium">− {{ format_money($discountAmount, $invoice->currency) }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <dt class="text-slate-500">{{ __('TVA') }}</dt>
                        <dd class="tabular-nums font-medium text-ink">{{ format_money($invoice->tax_amount, $invoice->currency) }}</dd>
                    </div>
                    <div class="flex justify-between border-t border-slate-200 pt-3">
                        <dt class="font-semibold text-ink">{{ __('Total TTC') }}</dt>
                        <dd class="tabular-nums text-lg font-bold text-ink">{{ format_money($invoice->total, $invoice->currency) }}</dd>
                    </div>

                    @if ($invoice->status === InvoiceStatus::PartiallyPaid)
                        <div class="flex justify-between text-amber-600">
                            <dt>{{ __('Encaissé') }}</dt>
                            <dd class="tabular-nums font-medium">{{ format_money($invoice->amount_paid, $invoice->currency) }}</dd>
                        </div>
                        <div class="flex justify-between text-rose-600">
                            <dt class="font-semibold">{{ __('Reste dû') }}</dt>
                            <dd class="tabular-nums font-bold">{{ format_money($invoice->total - $invoice->amount_paid, $invoice->currency) }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($invoice->paid_at)
                    <div class="mt-5 border-t border-slate-200 pt-4 text-sm">
                        <div class="flex justify-between">
                            <span class="text-slate-500">{{ __('Payée le') }}</span>
                            <span class="font-medium text-teal-600">{{ format_date($invoice->paid_at) }}</span>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Client final --}}
            @if ($invoice->client)
                <div class="app-shell-panel px-6 py-6">
                    <p class="mb-3 text-sm font-semibold text-slate-500">{{ __('Client final') }}</p>
                    <p class="font-semibold text-ink">{{ $invoice->client->name }}</p>
                    @if ($invoice->client->phone)
                        <p class="mt-1 text-sm text-slate-500">{{ $invoice->client->phone }}</p>
                    @endif
                    @if ($invoice->client->email)
                        <p class="mt-0.5 text-sm text-slate-500">{{ $invoice->client->email }}</p>
                    @endif
                    @if ($invoice->client->address)
                        <p class="mt-0.5 text-sm text-slate-500">{{ $invoice->client->address }}</p>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
