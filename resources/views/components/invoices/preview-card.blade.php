@props([
    'invoice',
    'showClient' => true,
])

@php
    $inv = $invoice;
    $client = $inv->client;
    $discountAmount = 0;
    $discountLabel = null;
    if ($inv->discount > 0) {
        $discountAmount = ($inv->discount_type ?? 'percent') === 'fixed'
            ? $inv->discount
            : (int) round($inv->subtotal * $inv->discount / 100);
        $discountLabel = ($inv->discount_type ?? 'percent') === 'fixed'
            ? __('Réduction (montant fixe)')
            : __('Réduction (:rate%)', ['rate' => $inv->discount]);
    }
    $depositResolved = (int) $inv->deposit_amount > 0
        ? min((int) $inv->deposit_amount, (int) $inv->total)
        : 0;
    $remaining = max(0, (int) $inv->total - $depositResolved);
@endphp

<div class="space-y-6">
    @if ($showClient && $client)
        <div>
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
    @elseif ($showClient)
        <div class="rounded-xl border border-amber-100 bg-amber-50 px-5 py-4 text-sm text-amber-700">
            {{ __('Aucun client final renseigné sur cette facture.') }}
        </div>
    @endif

    <div>
        <p class="mb-3 text-sm font-semibold text-slate-500">{{ __('Détail des prestations') }}</p>

        {{-- Mobile: cartes empilées --}}
        <div class="space-y-3 md:hidden">
            @forelse ($inv->lines as $line)
                <div class="rounded-xl border border-slate-100 bg-slate-50/60 p-4">
                    <p class="text-sm text-ink">{!! nl2br(e($line->description)) !!}</p>
                    <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1.5 text-xs">
                        <div class="flex items-baseline justify-between gap-2">
                            <dt class="text-slate-500">{{ __('Qté') }}</dt>
                            <dd class="font-medium text-ink tabular-nums">{{ $line->quantity }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-2">
                            <dt class="text-slate-500">{{ __('TVA') }}</dt>
                            <dd class="font-medium text-ink tabular-nums">{{ $line->tax_rate }} %</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-2">
                            <dt class="text-slate-500">{{ __('PU HT') }}</dt>
                            <dd class="font-medium text-ink tabular-nums whitespace-nowrap">{{ format_money($line->unit_price, $inv->currency) }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-2">
                            <dt class="text-slate-500">{{ __('Total HT') }}</dt>
                            <dd class="font-semibold text-ink tabular-nums whitespace-nowrap">{{ format_money($line->total, $inv->currency) }}</dd>
                        </div>
                    </dl>
                </div>
            @empty
                <p class="rounded-xl border border-dashed border-slate-200 px-5 py-6 text-center text-sm text-slate-400">{{ __('Aucune ligne.') }}</p>
            @endforelse
        </div>

        {{-- Desktop: tableau --}}
        <div class="hidden md:block">
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
                            <td class="py-3 pr-4 text-ink">{!! nl2br(e($line->description)) !!}</td>
                            <td class="py-3 px-4 text-right tabular-nums text-slate-600 whitespace-nowrap">{{ $line->quantity }}</td>
                            <td class="py-3 px-4 text-right tabular-nums text-slate-600 whitespace-nowrap">
                                {{ format_money($line->unit_price, $inv->currency) }}
                            </td>
                            <td class="py-3 px-4 text-right tabular-nums text-slate-500 whitespace-nowrap">{{ $line->tax_rate }} %</td>
                            <td class="py-3 pl-4 text-right tabular-nums font-medium text-ink whitespace-nowrap">
                                {{ format_money($line->total, $inv->currency) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-4 text-center text-slate-400">{{ __('Aucune ligne.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Totaux (partagés mobile + desktop) --}}
        <dl class="mt-4 space-y-1.5 border-t border-slate-200 pt-4 text-sm">
            <div class="flex items-baseline justify-between gap-3">
                <dt class="text-slate-500">{{ __('Sous-total HT') }}</dt>
                <dd class="tabular-nums text-ink whitespace-nowrap">{{ format_money($inv->subtotal, $inv->currency) }}</dd>
            </div>
            @if ($discountLabel)
                <div class="flex items-baseline justify-between gap-3">
                    <dt class="text-emerald-600">{{ $discountLabel }}</dt>
                    <dd class="tabular-nums text-emerald-600 whitespace-nowrap">− {{ format_money($discountAmount, $inv->currency) }}</dd>
                </div>
            @endif
            <div class="flex items-baseline justify-between gap-3">
                <dt class="text-slate-500">{{ __('TVA') }}</dt>
                <dd class="tabular-nums text-ink whitespace-nowrap">{{ format_money($inv->tax_amount, $inv->currency) }}</dd>
            </div>
            <div class="mt-2 flex items-baseline justify-between gap-3 border-t border-slate-100 pt-2">
                <dt class="text-base font-semibold text-ink">{{ __('Total TTC') }}</dt>
                <dd class="text-base font-bold tabular-nums text-ink whitespace-nowrap">{{ format_money($inv->total, $inv->currency) }}</dd>
            </div>
            @if ($depositResolved > 0)
                <div class="flex items-baseline justify-between gap-3 pt-2">
                    <dt class="text-emerald-600">{{ __('Acompte versé') }}</dt>
                    <dd class="tabular-nums text-emerald-600 whitespace-nowrap">− {{ format_money($depositResolved, $inv->currency) }}</dd>
                </div>
                <div class="mt-1 flex items-baseline justify-between gap-3 border-t border-slate-100 pt-2">
                    <dt class="text-base font-semibold text-ink">{{ __('Reste à payer') }}</dt>
                    <dd class="text-base font-bold tabular-nums text-primary whitespace-nowrap">{{ format_money($remaining, $inv->currency) }}</dd>
                </div>
            @endif
        </dl>
    </div>

    @if ($inv->notes)
        <div class="rounded-xl border border-slate-100 bg-slate-50/60 px-5 py-4 text-sm text-slate-600">
            <p class="mb-1 text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ __('Notes') }}</p>
            <p class="whitespace-pre-line">{{ $inv->notes }}</p>
        </div>
    @endif

    @if ($inv->payment_terms)
        <div class="rounded-xl border border-slate-100 bg-slate-50/60 px-5 py-4 text-sm text-slate-600">
            <p class="mb-1 text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ __('Conditions de paiement') }}</p>
            <p class="whitespace-pre-line">{{ $inv->payment_terms }}</p>
        </div>
    @endif
</div>
