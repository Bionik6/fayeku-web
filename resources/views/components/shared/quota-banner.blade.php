@props([
    'company',
    'type' => 'reminders',
    'warnThreshold' => 80, // affiche la bannière "warning" quand usage >= 80%
])

@php
    if (! $company) {
        return;
    }

    $usage = app(\App\Services\Shared\QuotaService::class)->usage($company, $type);

    // Plan illimité : rien à afficher
    if ($usage['unlimited']) {
        return;
    }

    $exhausted = $usage['available'] <= 0;
    $warning = ! $exhausted && $usage['percent'] !== null && $usage['percent'] >= $warnThreshold;

    // Rien à afficher tant qu'on est confortablement sous le seuil
    if (! $exhausted && ! $warning) {
        return;
    }

    $label = match ($type) {
        'reminders' => __('relances & notifications WhatsApp'),
        default => __('quota'),
    };
@endphp

<div @class([
    'mb-4 rounded-2xl border px-5 py-4',
    'border-rose-200 bg-rose-50' => $exhausted,
    'border-amber-200 bg-amber-50' => $warning,
])>
    <div class="flex items-start gap-3">
        <div @class([
            'mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full',
            'bg-rose-100 text-rose-600' => $exhausted,
            'bg-amber-100 text-amber-600' => $warning,
        ])>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            </svg>
        </div>
        <div class="flex-1">
            <p @class([
                'text-sm font-semibold',
                'text-rose-900' => $exhausted,
                'text-amber-900' => $warning,
            ])>
                @if ($exhausted)
                    {{ __('Quota :type épuisé pour ce mois', ['type' => $label]) }}
                @else
                    {{ __('Quota :type bientôt atteint', ['type' => $label]) }}
                @endif
            </p>
            <p @class([
                'mt-1 text-xs',
                'text-rose-800' => $exhausted,
                'text-amber-800' => $warning,
            ])>
                @if ($exhausted)
                    {{ __(':used/:limit messages consommés ce mois-ci. Les prochaines relances et notifications ne seront pas envoyées. Passez à un plan supérieur pour continuer à relancer vos clients.', [
                        'used' => number_format($usage['used'], 0, ',', ' '),
                        'limit' => number_format($usage['limit'] + $usage['addons'], 0, ',', ' '),
                    ]) }}
                @else
                    {{ __('Il vous reste :available messages ce mois-ci sur :limit. Pensez à upgrader votre plan avant la fin du mois.', [
                        'available' => number_format($usage['available'], 0, ',', ' '),
                        'limit' => number_format($usage['limit'] + $usage['addons'], 0, ',', ' '),
                    ]) }}
                @endif
            </p>

            {{-- Barre de progression --}}
            <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-white/60">
                <div @class([
                    'h-full rounded-full transition-all',
                    'bg-rose-500' => $exhausted,
                    'bg-amber-500' => $warning,
                ]) style="width: {{ $usage['percent'] ?? 100 }}%"></div>
            </div>

            <div class="mt-3">
                <a href="{{ route('pme.settings.index') }}?section=plan" wire:navigate @class([
                    'inline-flex items-center gap-1 text-xs font-semibold',
                    'text-rose-700 hover:text-rose-800' => $exhausted,
                    'text-amber-700 hover:text-amber-800' => $warning,
                ])>
                    {{ __('Voir les plans disponibles') }}
                    <svg viewBox="0 0 20 20" fill="currentColor" class="size-3.5">
                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" />
                    </svg>
                </a>
            </div>
        </div>
    </div>
</div>
