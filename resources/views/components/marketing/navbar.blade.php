@props(['navigation'])

@php
    $currentPath = '/' . ltrim(request()->path(), '/');
@endphp

<header
    x-data="{ scrolled: false, open: false }"
    x-init="window.addEventListener('scroll', () => scrolled = window.scrollY > 80)"
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
            @foreach ($navigation as $item)
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
        class="fixed inset-0 z-50 lg:hidden"
    >
        <div class="absolute inset-0" style="background: rgba(10, 31, 26, 0.4);" @click="open=false"></div>
        <div class="absolute right-0 top-0 h-full w-[85%] max-w-sm bg-white p-6 flex flex-col">
            <div class="flex items-center justify-between mb-8">
                <a href="{{ route('home') }}" class="flex items-center gap-3">
                    <img src="/logo.png" alt="Fayeku" class="w-10 h-10 rounded-lg" width="40" height="40" />
                    <span class="font-bold">Fayeku</span>
                </a>
                <button @click="open=false" class="p-2 rounded-lg hover:bg-mint-100" aria-label="Fermer le menu" type="button">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <nav class="flex flex-col gap-1 text-lg" aria-label="Navigation mobile">
                @foreach ($navigation as $item)
                    <a href="{{ $item['href'] }}" class="py-3 border-b border-gray-100">{{ $item['label'] }}</a>
                @endforeach
                <a href="{{ route('login') }}" class="py-3 border-b border-gray-100">Se connecter</a>
            </nav>
            <a href="{{ route('register') }}" class="btn-primary justify-center mt-auto">Essayer 30 jours</a>
        </div>
    </div>
</header>
