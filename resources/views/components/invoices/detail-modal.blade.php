@props([
    'invoice',
    'closeAction' => 'closeInvoice',
])

@php
    $inv = $invoice;
    $client = $inv->client;

    $statusConfig = match ($inv->status) {
        \Modules\PME\Invoicing\Enums\InvoiceStatus::Paid => ['label' => 'Payée', 'class' => 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20'],
        \Modules\PME\Invoicing\Enums\InvoiceStatus::Overdue => ['label' => 'Impayée', 'class' => 'bg-rose-100 text-rose-700'],
        \Modules\PME\Invoicing\Enums\InvoiceStatus::PartiallyPaid => ['label' => 'Partiel', 'class' => 'bg-orange-100 text-orange-700'],
        \Modules\PME\Invoicing\Enums\InvoiceStatus::Sent,
        \Modules\PME\Invoicing\Enums\InvoiceStatus::Certified => ['label' => 'En attente', 'class' => 'bg-amber-50 text-amber-700'],
        \Modules\PME\Invoicing\Enums\InvoiceStatus::Draft => ['label' => 'Brouillon', 'class' => 'bg-slate-100 text-slate-600'],
        \Modules\PME\Invoicing\Enums\InvoiceStatus::Cancelled => ['label' => 'Annulée', 'class' => 'bg-slate-100 text-slate-500'],
        default => ['label' => ucfirst($inv->status->value), 'class' => 'bg-slate-100 text-slate-600'],
    };
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
                        {{ __('Émise le') }} {{ $inv->issued_at->locale('fr_FR')->translatedFormat('j F Y') }}
                        &nbsp;·&nbsp;
                        {{ __('Échéance le') }} {{ $inv->due_at->locale('fr_FR')->translatedFormat('j F Y') }}
                    </p>
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold {{ $statusConfig['class'] }}">
                        {{ $statusConfig['label'] }}
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
                    @if ($client)
                        <div class="mb-6">
                            <p class="mb-3 text-sm font-semibold text-slate-500">{{ __('Destinataire') }}</p>
                            <div class="rounded-xl border border-slate-100 bg-slate-50/60 px-5 py-4">
                                <p class="font-semibold text-ink">{{ $client->name }}</p>
                                @if ($client->phone)
                                    <p class="mt-1 flex items-center gap-1.5 text-sm text-slate-500">
                                        <flux:icon name="phone" class="size-3.5 shrink-0" />
                                        {{ format_phone($client->phone) }}
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
                                    <p class="mt-2 text-sm font-mono text-slate-400">{{ __('Référence fiscale') }} : {{ $client->tax_id }}</p>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="mb-6 rounded-xl border border-amber-100 bg-amber-50 px-5 py-4 text-sm text-amber-700">
                            {{ __('Aucun client final renseigné sur cette facture.') }}
                        </div>
                    @endif

                    <div>
                        <p class="mb-3 text-sm font-semibold text-slate-500">{{ __('Détail des prestations') }}</p>
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-100 text-left">
                                    <th class="pb-2 pr-4 text-sm font-semibold text-slate-500">{{ __('Description') }}</th>
                                    <th class="pb-2 px-4 text-right text-sm font-semibold text-slate-500 whitespace-nowrap">{{ __('Qté') }}</th>
                                    <th class="pb-2 px-4 text-right text-sm font-semibold text-slate-500 whitespace-nowrap">{{ __('PU HT') }}</th>
                                    <th class="pb-2 px-4 text-right text-sm font-semibold text-slate-500 whitespace-nowrap">{{ __('TVA') }}</th>
                                    <th class="pb-2 pl-4 text-right text-sm font-semibold text-slate-500 whitespace-nowrap">{{ __('Total HT') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                @forelse ($inv->lines as $line)
                                    <tr>
                                        <td class="py-3 pr-4 text-ink">{{ $line->description }}</td>
                                        <td class="py-3 px-4 text-right tabular-nums text-slate-600 whitespace-nowrap">{{ $line->quantity }}</td>
                                        <td class="py-3 px-4 text-right tabular-nums text-slate-600 whitespace-nowrap">
                                            {{ number_format($line->unit_price, 0, ',', ' ') }} FCFA
                                        </td>
                                        <td class="py-3 px-4 text-right tabular-nums text-slate-500 whitespace-nowrap">{{ $line->tax_rate }} %</td>
                                        <td class="py-3 pl-4 text-right tabular-nums font-medium text-ink whitespace-nowrap">
                                            {{ number_format($line->total, 0, ',', ' ') }} FCFA
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
                                    <td class="pt-4 pl-4 text-right tabular-nums text-sm text-ink whitespace-nowrap">
                                        {{ number_format($inv->subtotal, 0, ',', ' ') }} FCFA
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="pt-1 pr-4 text-right text-sm text-slate-500">{{ __('TVA') }}</td>
                                    <td class="pt-1 pl-4 text-right tabular-nums text-sm text-ink whitespace-nowrap">
                                        {{ number_format($inv->tax_amount, 0, ',', ' ') }} FCFA
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="pt-2 pr-4 text-right text-base font-semibold text-ink">{{ __('Total TTC') }}</td>
                                    <td class="pt-2 pl-4 text-right tabular-nums text-base font-bold text-ink whitespace-nowrap">
                                        {{ number_format($inv->total, 0, ',', ' ') }} FCFA
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div class="border-t border-slate-100 bg-slate-50/60 px-8 py-8 lg:border-t-0 lg:border-l">
                    <p class="mb-4 text-sm font-semibold text-slate-500">{{ __('Récapitulatif') }}</p>

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

                        @if ($inv->status === \Modules\PME\Invoicing\Enums\InvoiceStatus::PartiallyPaid)
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

                    @if ($inv->paid_at)
                        <div class="mt-6 border-t border-slate-200 pt-4 text-sm">
                            <div class="flex justify-between">
                                <span class="text-slate-500">{{ __('Payée le') }}</span>
                                <span class="text-teal-600">{{ $inv->paid_at->locale('fr_FR')->translatedFormat('j M Y') }}</span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 border-t border-slate-100 px-10 py-5">
            <flux:button variant="ghost" wire:click="{{ $closeAction }}">
                {{ __('Fermer') }}
            </flux:button>
        </div>
    </div>
</div>
