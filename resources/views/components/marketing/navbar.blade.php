@props(['navigation'])

@php
    $currentPath = '/' . ltrim(request()->path(), '/');
    $headerNavigation = array_values(array_filter($navigation, fn ($item) => ($item['in_header'] ?? true) !== false));
@endphp

<header
    x-data="{ scrolled: false, open: false }"
    x-init="window.addEventListener('scroll', () => scrolled = window.scrollY > 80)"
    x-effect="document.body.style.overflow = open ? 'hidden' : ''"
    :class="scrolled ? 'bg-white/80 backdrop-blur-md border-b border-gray-100' : 'bg-transparent'"
    class="fixed top-0 inset-x-0 z-50 transition-all"
>
    <div class="max-w-7xl mx-auto px-5 lg:px-8 h-[68px] flex items-center justify-between">
        <a href="{{ route('home') }}" class="flex items-center gap-3 ringfx rounded-lg" aria-label="Accueil Fayeku">
            <img src="/logo.png" alt="Fayeku" class="w-10 h-10 rounded-lg" width="40" height="40" />
            <div class="leading-tight hidden sm:block">
                <div class="font-bold text-[1.05rem] tracking-tight" style="color: var(--color-marketing-ink);">Fayeku</div>
                <div class="text-[0.72rem] -mt-0.5" style="color: var(--color-marketing-slate);">Facturation &amp; trésorerie</div>
            </div>
        </a>

        <nav class="hidden lg:flex items-center gap-1 absolute left-1/2 -translate-x-1/2" aria-label="Navigation principale">
            @foreach ($headerNavigation as $item)
                @php
                    $activePath = rtrim($item['active'] ?? $item['href'], '/');
                    $isActive = $activePath !== '' && (
                        $currentPath === $activePath ||
                        str_starts_with($currentPath, $activePath . '/')
                    );
                @endphp
                <a
                    href="{{ $item['href'] }}"
                    class="nav-link {{ $isActive ? 'nav-link-active' : '' }}"
                    @if ($isActive) aria-current="page" @endif
                >
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="hidden lg:flex items-center gap-2">
            <a href="{{ route('login') }}" class="nav-link">Se connecter</a>
            <a href="{{ route('register') }}" class="btn-primary text-sm py-2.5 px-5">Essayer 30 jours</a>
        </div>

        <button @click="open = true" class="lg:hidden p-2 rounded-lg hover:bg-mint-100 ringfx" aria-label="Ouvrir le menu" type="button">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
    </div>

    <div
        x-show="open"
        x-cloak
        @keydown.escape.window="open=false"
        class="fixed inset-0 z-[100] lg:hidden"
    >
        <div class="absolute inset-0 backdrop-blur-sm" style="background: rgba(10, 31, 26, 0.5);" @click="open=false"></div>
        <div class="absolute inset-y-0 right-0 w-[85%] max-w-sm bg-white flex flex-col shadow-2xl overflow-y-auto">
            <div class="flex items-center justify-between gap-4 px-6 py-5 border-b border-gray-100">
                <a href="{{ route('home') }}" class="flex items-center gap-3">
                    <img src="/logo.png" alt="Fayeku" class="w-10 h-10 rounded-lg" width="40" height="40" />
                    <div class="leading-tight">
                        <div class="font-bold text-[1.05rem] tracking-tight" style="color: var(--color-marketing-ink);">Fayeku</div>
                        <div class="text-[0.78rem] -mt-0.5" style="color: var(--color-marketing-slate);">Facturation &amp; trésorerie</div>
                    </div>
                </a>
                <button @click="open=false" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 transition hover:bg-mint-100" aria-label="Fermer le menu" type="button" style="color: var(--color-marketing-ink);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <nav class="flex flex-col gap-1 px-4 pt-6" aria-label="Navigation mobile">
                @foreach ($headerNavigation as $item)
                    @php
                        $activePath = rtrim($item['active'] ?? $item['href'], '/');
                        $isActive = $activePath !== '' && (
                            $currentPath === $activePath ||
                            str_starts_with($currentPath, $activePath . '/')
                        );
                    @endphp
                    <a
                        href="{{ $item['href'] }}"
                        class="rounded-2xl px-5 py-4 text-lg font-semibold transition {{ $isActive ? '' : 'hover:bg-mint-100' }}"
                        @if ($isActive) aria-current="page" style="background: var(--color-mint-100); color: var(--color-marketing-ink);" @else style="color: var(--color-marketing-ink);" @endif
                    >
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
            <div class="mt-auto px-6 pb-6 pt-8 grid gap-3">
                <a href="{{ route('login') }}" class="rounded-full border border-gray-200 px-5 py-3.5 text-center text-base font-semibold transition hover:bg-mint-100" style="color: var(--color-marketing-ink);">Se connecter</a>
                <a href="{{ route('register') }}" class="btn-primary justify-center text-base py-3.5">Essayer 30 jours</a>
            </div>
        </div>
    </div>
</header>
