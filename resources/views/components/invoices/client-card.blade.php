@props([
    'invoice',
])

@php
    $client = $invoice->client;
@endphp

<article class="app-shell-panel p-6">
    @if ($client)
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-teal">{{ __('Destinataire') }}</p>
                <h3 class="mt-2 text-xl font-semibold tracking-tight text-ink">{{ $client->name }}</h3>
            </div>
            <a
                href="{{ route('pme.clients.show', $client->id) }}"
                wire:navigate
                class="inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-primary transition hover:text-primary-strong"
            >
                {{ __('Voir la fiche client') }}
                <flux:icon name="arrow-right" class="size-4" />
            </a>
        </div>

        <dl class="mt-4 space-y-2 text-sm">
            @if ($client->phone)
                <div class="flex items-center gap-2 text-slate-600">
                    <flux:icon name="phone" class="size-4 shrink-0 text-slate-400" />
                    <dd>{{ format_phone($client->phone) }}</dd>
                </div>
            @endif
            @if ($client->email)
                <div class="flex items-center gap-2 text-slate-600">
                    <flux:icon name="envelope" class="size-4 shrink-0 text-slate-400" />
                    <dd class="break-all">{{ $client->email }}</dd>
                </div>
            @endif
            @if ($client->address)
                <div class="flex items-center gap-2 text-slate-600">
                    <flux:icon name="map-pin" class="size-4 shrink-0 text-slate-400" />
                    <dd>{{ $client->address }}</dd>
                </div>
            @endif
            @if ($client->tax_id)
                <div class="mt-3 border-t border-slate-100 pt-3 text-sm font-mono text-slate-500">
                    {{ __('NINEA') }} : {{ $client->tax_id }}
                </div>
            @endif
        </dl>
    @else
        <div class="rounded-xl border border-amber-100 bg-amber-50 px-5 py-4 text-sm text-amber-700">
            {{ __('Aucun client final renseigné sur cette facture.') }}
        </div>
    @endif
</article>
