@props([
    'eyebrow' => null,
    'title',
    'description' => null,
    'align' => 'left',
])

<div @class([
    'space-y-4',
    'mx-auto max-w-3xl text-center' => $align === 'center',
])>
    @if ($eyebrow)
        <p class="text-sm font-semibold uppercase tracking-[0.24em] text-teal">{{ $eyebrow }}</p>
    @endif

    <div class="space-y-3">
        <h2 class="text-balance text-3xl font-semibold text-ink sm:text-4xl">{{ $title }}</h2>
        @if ($description)
            <p class="text-pretty text-base leading-7 text-slate-600 sm:text-lg">{{ $description }}</p>
        @endif
    </div>
</div>
