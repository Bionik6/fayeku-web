@props([
    'invoice',
    'closeAction' => 'closeInvoice',
])

@php
    $inv = $invoice;
    $statusConfig = $inv->status->display();
@endphp

<div
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    wire:click.self="{{ $closeAction }}"
>
    <div class="relative w-full max-w-[1200px] overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="flex items-start justify-between border-b border-slate-100 px-10 py-7">
            <div>
                <p class="text-sm font-semibold tracking-[0.24em] text-slate-400">{{ __('Facture') }}</p>
                <h2 class="mt-1 text-xl font-bold text-ink">{{ $inv->reference }}</h2>
                <div class="mt-1 flex items-center gap-3">
                    <p class="text-sm text-slate-500">
                        {{ __('Émise le') }} {{ format_date($inv->issued_at) }}
                        &nbsp;·&nbsp;
                        {{ __('Échéance le') }} {{ format_date($inv->due_at) }}
                    </p>
                    <span class="inline-flex whitespace-nowrap items-center rounded-full px-3 py-1 text-sm font-semibold {{ $statusConfig['class'] }}">
                        {{ __($statusConfig['label']) }}
                    </span>
                </div>
            </div>
            <button
                wire:click="{{ $closeAction }}"
                class="rounded-full border border-slate-200 p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
            >
                <flux:icon name="x-mark" class="size-5" />
            </button>
        </div>

        <div class="max-h-[80vh] overflow-y-auto">
            <div class="grid grid-cols-1 gap-0 lg:grid-cols-3">
                <div class="col-span-2 px-10 py-8">
                    <x-invoices.preview-card :invoice="$inv" />
                </div>

                <div class="border-t border-slate-100 bg-slate-50/60 px-8 py-8 lg:border-t-0 lg:border-l">
                    <p class="mb-4 text-sm font-semibold text-slate-500">{{ __('Récapitulatif') }}</p>

                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-slate-500">{{ __('Montant HT') }}</dt>
                            <dd class="tabular-nums font-medium text-ink">{{ format_money($inv->subtotal, $inv->currency) }}</dd>
                        </div>
                        @if ($inv->discount > 0)
                            @php
                                $discountAmount = ($inv->discount_type ?? 'percent') === 'fixed'
                                    ? $inv->discount
                                    : (int) round($inv->subtotal * $inv->discount / 100);
                                $discountLabel = ($inv->discount_type ?? 'percent') === 'fixed'
                                    ? __('Réduction (montant fixe)')
                                    : __('Réduction (:rate%)', ['rate' => $inv->discount]);
                            @endphp
                            <div class="flex justify-between text-emerald-600">
                                <dt>{{ $discountLabel }}</dt>
                                <dd class="tabular-nums font-medium">− {{ format_money($discountAmount, $inv->currency) }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between">
                            <dt class="text-slate-500">{{ __('TVA') }}</dt>
                            <dd class="tabular-nums font-medium text-ink">{{ format_money($inv->tax_amount, $inv->currency) }}</dd>
                        </div>
                        <div class="flex justify-between border-t border-slate-200 pt-3">
                            <dt class="font-semibold text-ink">{{ __('Total TTC') }}</dt>
                            <dd class="tabular-nums text-lg font-bold text-ink">{{ format_money($inv->total, $inv->currency) }}</dd>
                        </div>

                        @if ($inv->status === \App\Enums\PME\InvoiceStatus::PartiallyPaid)
                            <div class="flex justify-between text-amber-600">
                                <dt>{{ __('Encaissé') }}</dt>
                                <dd class="tabular-nums font-medium">{{ format_money($inv->amount_paid, $inv->currency) }}</dd>
                            </div>
                            <div class="flex justify-between text-rose-600">
                                <dt class="font-semibold">{{ __('Reste dû') }}</dt>
                                <dd class="tabular-nums font-bold">{{ format_money($inv->total - $inv->amount_paid, $inv->currency) }}</dd>
                            </div>
                        @endif
                    </dl>

                    @if ($inv->paid_at)
                        <div class="mt-6 border-t border-slate-200 pt-4 text-sm">
                            <div class="flex justify-between">
                                <span class="text-slate-500">{{ __('Payée le') }}</span>
                                <span class="text-teal-600">{{ format_date($inv->paid_at) }}</span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between gap-3 border-t border-slate-100 px-10 py-5">
            <flux:button variant="ghost" wire:click="{{ $closeAction }}">
                {{ __('Fermer') }}
            </flux:button>
            <a
                href="{{ route('pme.invoices.show', $inv) }}"
                wire:navigate
                class="inline-flex items-center gap-2 rounded-2xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-strong"
            >
                {{ __('Ouvrir la fiche') }}
                <flux:icon name="arrow-right" class="size-4" />
            </a>
        </div>
    </div>
</div>
