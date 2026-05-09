@php
    $totalSteps = 5;
@endphp

<div class="flex items-center justify-between gap-4">
    {{-- 5 dashes horizontaux : actifs en primary, inactifs en gris clair --}}
    <ol role="list" class="flex flex-1 items-center gap-2">
        @for ($i = 0; $i < $totalSteps; $i++)
            <li
                aria-hidden="true"
                class="h-1.5 flex-1 rounded-full transition-colors {{ $currentStep >= $i ? 'bg-primary' : 'bg-slate-200' }}"
            ></li>
        @endfor
    </ol>

    <p class="shrink-0 text-sm font-semibold tabular-nums text-slate-500">
        {{ $currentStep + 1 }} <span class="text-slate-300">/</span> {{ $totalSteps }}
    </p>
</div>
