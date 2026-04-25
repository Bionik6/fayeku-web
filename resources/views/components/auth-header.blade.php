@props([
    'title',
    'description',
])

<div class="w-full space-y-3">
    <p class="text-sm font-semibold uppercase tracking-[0.24em] text-teal">Espace securise</p>
    <h1 class="text-balance text-3xl font-semibold text-ink sm:text-[2rem]">{{ $title }}</h1>
    <p class="text-base leading-7 text-slate-600">{!! $description !!}</p>
</div>
