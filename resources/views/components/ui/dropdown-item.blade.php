@props(['destructive' => false, 'count' => null])

@php
    $baseClasses = 'group flex w-full items-center gap-3 px-4 py-2 text-sm whitespace-nowrap';
    $colorClasses = $destructive
        ? 'text-rose-600 hover:bg-rose-50'
        : 'text-slate-700 hover:bg-slate-50';
@endphp

@if ($attributes->has('href'))
    <a {{ $attributes->merge(['class' => "$baseClasses $colorClasses"]) }} role="menuitem" @click="$dispatch('ui-dropdown-close')">
        @if (isset($icon)){{ $icon }}@endif
        {{ $slot }}
        @if ($count !== null && $count > 0)
            <span class="ml-auto inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ $count }}</span>
        @endif
    </a>
@else
    <button {{ $attributes->merge(['class' => "$baseClasses $colorClasses", 'type' => 'button']) }} role="menuitem" @click="$dispatch('ui-dropdown-close')">
        @if (isset($icon)){{ $icon }}@endif
        {{ $slot }}
        @if ($count !== null && $count > 0)
            <span class="ml-auto inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ $count }}</span>
        @endif
    </button>
@endif
