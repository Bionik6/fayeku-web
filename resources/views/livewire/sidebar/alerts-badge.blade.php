<span
    @class([
        'ml-auto rounded-full bg-rose-500 px-1.5 py-0.5 text-xs font-bold leading-none text-white' => $count > 0,
        'hidden' => $count === 0,
    ])
>
    @if ($count > 0)
        {{ $count }}
    @endif
</span>
