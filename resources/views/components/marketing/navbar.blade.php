@props(['navigation'])

<header class="sticky top-0 z-50 border-b border-primary/10 bg-white/85 backdrop-blur-xl">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
        <a href="{{ route('home') }}" class="flex items-center gap-3" aria-label="Accueil Fayeku">
            <img src="/logo-mark.svg" alt="" width="36" height="36" />
            <div>
                <p class="text-lg font-semibold text-primary">Fayeku</p>
                <p class="text-xs text-slate-500">Facturation & trésorerie</p>
            </div>
        </a>

        <nav class="hidden items-center gap-8 lg:flex" aria-label="Navigation principale">
            @foreach ($navigation as $item)
                @php
                    $activePath = rtrim($item['active'] ?? $item['href'], '/');
                    $currentPath = '/' . ltrim(request()->path(), '/');
                    $isActive = $activePath !== '' && (
                        $currentPath === $activePath ||
                        str_starts_with($currentPath, $activePath . '/')
                    );
                @endphp
                <a
                    href="{{ $item['href'] }}"
                    class="text-sm transition {{ $isActive ? 'font-bold text-primary' : 'font-medium text-slate-600 hover:text-primary' }}"
                    @if ($isActive) aria-current="page" @endif
                >
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="hidden items-center gap-4 lg:flex">
            <a href="{{ route('login') }}" class="text-sm font-medium text-slate-600 transition hover:text-primary">Se connecter</a>
            <a href="{{ route('auth.register') }}" class="rounded-full bg-primary px-5 py-2.5 text-sm font-semibold text-accent">Essayer 2 mois</a>
        </div>

        <button
            type="button"
            data-nav-toggle
            class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-primary/10 text-primary lg:hidden"
            aria-expanded="false"
            aria-controls="mobile-menu"
            aria-label="Ouvrir le menu"
        >
            ☰
        </button>
    </div>

    <div id="mobile-menu" data-nav-menu class="max-h-0 overflow-hidden border-t border-primary/10 transition-all lg:hidden">
        <nav class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-4 sm:px-6" aria-label="Navigation mobile">
            @foreach ($navigation as $item)
                @php
                    $activePath = rtrim($item['active'] ?? $item['href'], '/');
                    $currentPath = '/' . ltrim(request()->path(), '/');
                    $isActive = $activePath !== '' && (
                        $currentPath === $activePath ||
                        str_starts_with($currentPath, $activePath . '/')
                    );
                @endphp
                <a
                    href="{{ $item['href'] }}"
                    class="rounded-2xl px-3 py-3 text-sm transition {{ $isActive ? 'bg-primary/5 font-bold text-primary' : 'font-medium text-slate-700 hover:bg-mist' }}"
                    @if ($isActive) aria-current="page" @endif
                >
                    {{ $item['label'] }}
                </a>
            @endforeach

            <div class="mt-2 grid gap-2">
                <a href="{{ route('login') }}" class="rounded-full border border-primary/20 px-4 py-3 text-center text-sm font-medium text-primary">Se connecter</a>
                <a href="{{ route('auth.register') }}" class="rounded-full bg-primary px-4 py-3 text-center text-sm font-semibold text-accent">Essayer 2 mois</a>
            </div>
        </nav>
    </div>
</header>
