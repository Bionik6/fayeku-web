@props([
    'label',
    'dot' => null,
    'active' => false,
    'activeClass' => 'bg-primary text-white',
    'badgeInactive' => 'bg-slate-100 text-slate-500',
    'count' => null,
])

<button
    {{ $attributes->merge(['type' => 'button']) }}
    @class([
        'inline-flex items-center gap-1.5 rounded-full px-3.5 py-1 text-sm font-semibold transition focus:outline-none',
        $activeClass => $active,
        'bg-white border border-slate-200 text-slate-600 hover:border-primary/30 hover:text-primary' => ! $active,
    ])
>
    @if ($dot)
        <span @class(['size-1.5 rounded-full', 'bg-white' => $active, $dot => ! $active])></span>
    @endif
    {{ $label }}
    @if ($count !== null)
        <span @class([
            'rounded-full px-1.5 py-px text-sm font-bold',
            'bg-white/20 text-white' => $active,
            $badgeInactive => ! $active,
        ])>{{ $count }}</span>
    @endif
</button>
