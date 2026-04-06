@props(['title', 'subtitle' => null])

<div>
    <h3 class="text-lg font-bold text-ink">{{ $title }}</h3>
    @if ($subtitle)
        <p class="mt-0.5 text-sm text-slate-500">{{ $subtitle }}</p>
    @endif
</div>
