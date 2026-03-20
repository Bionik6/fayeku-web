@props([
    'eyebrow' => null,
    'title',
    'description',
    'actions' => [],
    'aside' => null,
    'dark' => false,
])

<section @class([
    'relative overflow-hidden',
    'bg-primary' => $dark,
])>
    @if (! $dark)
        <div class="absolute inset-x-0 top-0 -z-10 h-[32rem] bg-[radial-gradient(circle_at_top,rgba(34,197,94,0.18),transparent_48%),linear-gradient(180deg,#EAF7F2_0%,rgba(234,247,242,0)_100%)]"></div>
    @endif

    <div class="mx-auto grid max-w-7xl gap-12 px-4 pb-16 pt-16 sm:px-6 lg:grid-cols-[1.1fr_0.9fr] lg:px-8 lg:pb-24 lg:pt-24">
        <div class="space-y-8">
            <div class="space-y-4">
                @if ($eyebrow)
                    <p @class([
                        'text-sm font-semibold uppercase tracking-[0.24em]',
                        'text-teal' => ! $dark,
                        'text-accent/80' => $dark,
                    ])>{{ $eyebrow }}</p>
                @endif

                <h1 @class([
                    'max-w-3xl text-balance text-4xl font-semibold leading-[1.08] sm:text-5xl lg:text-[52px] lg:leading-[60px]',
                    'text-ink' => ! $dark,
                    'text-white' => $dark,
                ])>{{ $title }}</h1>
                <p @class([
                    'max-w-2xl text-pretty text-lg leading-8',
                    'text-slate-600' => ! $dark,
                    'text-white/80' => $dark,
                ])>{{ $description }}</p>
            </div>

            @if ($actions)
                <div class="flex flex-col gap-3 sm:flex-row">
                    @foreach ($actions as $action)
                        <a
                            href="{{ $action['href'] }}"
                            @class([
                                'inline-flex items-center justify-center rounded-full px-6 py-3 text-sm font-semibold transition-transform hover:-translate-y-0.5',
                                'border border-primary/10 bg-white text-primary' => ($action['variant'] ?? 'primary') === 'secondary',
                                'bg-primary text-accent' => ($action['variant'] ?? 'primary') === 'primary' && ! $dark,
                                'bg-accent text-primary' => ($action['variant'] ?? 'primary') === 'primary' && $dark,
                            ])
                        >
                            {{ $action['label'] }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($aside)
            <div>{!! $aside !!}</div>
        @endif
    </div>
</section>
