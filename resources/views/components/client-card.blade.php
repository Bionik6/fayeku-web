@props([
    'client',
    'noClientMessage' => 'Aucun client renseigné sur ce document.',
])

<?php
/** @var \App\Models\PME\Client|null $client */
?>

<section class="app-shell-panel p-6">
    <div class="mb-4 flex items-center justify-between gap-3">
        <h3 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-700">{{ __('Client') }}</h3>
        @if ($client)
            <a href="{{ route('pme.clients.show', $client->id) }}" wire:navigate class="inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-primary transition hover:text-primary-strong">
                {{ __('Voir la fiche') }} <flux:icon name="arrow-right" class="size-4" />
            </a>
        @endif
    </div>

    @if (! $client)
        <div class="rounded-xl border border-amber-100 bg-amber-50 px-5 py-4 text-sm text-amber-700">
            {{ __($noClientMessage) }}
        </div>
    @elseif ($client->lacksContact())
        <div class="flex items-center gap-3">
            <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-blue-50 text-base font-semibold text-blue-600">
                {{ $client->initials() }}
            </span>
            <div class="min-w-0">
                <p class="truncate font-semibold text-ink">{{ $client->name }}</p>
                <p class="text-sm text-slate-500">{{ __('Nouveau client') }}</p>
            </div>
        </div>
        <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50/80 px-5 py-4 text-sm text-slate-600">
            {{ __('Coordonnées non renseignées. Pensez à compléter la fiche client pour faciliter les relances.') }}
        </div>
    @else
        <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
            <p class="font-semibold text-ink">{{ $client->name }}</p>

            <div class="mt-1 flex flex-wrap items-center gap-x-1.5 text-sm text-slate-700">
                @if ($client->email)
                    <span class="break-all">{{ $client->email }}</span>
                @endif
                @if ($client->email && $client->phone)
                    <span class="text-slate-500">⋅</span>
                @endif
                @if ($client->phone)
                    <span>{{ format_phone($client->phone) }}</span>
                @endif
            </div>

            {{-- Slot: optional context-specific meta lines (e.g. invoice payment KPIs). --}}
            {{ $slot }}

            @if ($client->address || $client->tax_id || $client->rccm)
                <div class="mt-3 space-y-1 border-t border-slate-200/70 pt-3 text-sm text-slate-600">
                    @if ($client->address)
                        <p class="flex items-start gap-1.5">
                            <flux:icon name="map-pin" class="mt-0.5 size-3.5 shrink-0 text-slate-400" />
                            <span>{{ $client->address }}</span>
                        </p>
                    @endif
                    @if ($client->tax_id)
                        <p class="text-slate-600"><span class="font-medium text-slate-700">{{ __('NINEA') }}</span> : {{ $client->tax_id }}</p>
                    @endif
                    @if ($client->rccm)
                        <p class="text-slate-600"><span class="font-medium text-slate-700">{{ __('RCCM') }}</span> : {{ $client->rccm }}</p>
                    @endif
                </div>
            @endif
        </div>
    @endif
</section>
