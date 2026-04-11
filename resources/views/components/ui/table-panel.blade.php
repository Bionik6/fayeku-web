@props([
    'title' => null,
    'description' => null,
    'filterLabel' => null,
])

<section {{ $attributes->merge(['class' => 'app-shell-panel overflow-hidden']) }}>

    {{-- Header : titre + description + action optionnelle --}}
    @if ($title !== null || (isset($action) && $action->isNotEmpty()))
        <div @class([
            'px-6 pt-6 pb-2',
            'flex items-start justify-between' => isset($action) && $action->isNotEmpty(),
        ])>
            @if ($title !== null)
                <x-section-header :title="$title" :subtitle="$description" />
            @endif
            @if (isset($action) && $action->isNotEmpty())
                <div class="shrink-0">{{ $action }}</div>
            @endif
        </div>
    @endif

    {{-- Filtres + Recherche --}}
    @if (isset($filters) || isset($search))
        <div @class([
            'px-6 py-5',
            'border-t border-slate-100' => $title !== null || (isset($action) && $action->isNotEmpty()),
        ])>
            @if ($filterLabel)
                <p class="mb-3 text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ $filterLabel }}</p>
            @endif

            @if (isset($filters))
                <div class="flex flex-wrap items-center gap-2">
                    {{ $filters }}
                </div>
            @endif

            @if (isset($search))
                <div class="mt-4">
                    {{ $search }}
                </div>
            @endif
        </div>
    @endif

    {{-- Contenu (table, liste…) --}}
    {{ $slot }}

</section>
