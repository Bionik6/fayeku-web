<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        <script>
            if (localStorage.getItem('sidebar-collapsed') === 'true') {
                document.documentElement.classList.add('sidebar-collapsed');
            }
        </script>
    </head>
    <body class="min-h-screen bg-page text-ink antialiased">
        @php
            $user = auth()->user();
            $smeCompany = $user->smeCompany();

            $primaryNavigation = [
                [
                    'label' => __('Tableau de bord'),
                    'icon' => 'dashboard',
                    'href' => route('pme.dashboard'),
                    'current' => request()->routeIs('pme.dashboard'),
                    'navigate' => true,
                ],
                [
                    'label' => __('Devis'),
                    'icon' => 'quote',
                    'href' => route('pme.quotes.index'),
                    'current' => request()->routeIs('pme.quotes.*'),
                    'navigate' => true,
                ],
                [
                    'label' => __('Factures'),
                    'icon' => 'invoice-bill',
                    'href' => route('pme.invoices.index'),
                    'current' => request()->routeIs('pme.invoices.*'),
                    'navigate' => true,
                ],
                [
                    'label' => __('Clients'),
                    'icon' => 'clients',
                    'href' => route('pme.clients.index'),
                    'current' => request()->routeIs('pme.clients.*'),
                    'navigate' => true,
                ],
                [
                    'label' => __('Recouvrement'),
                    'icon' => 'bell',
                    'href' => route('pme.collection.index'),
                    'current' => request()->routeIs('pme.collection.*'),
                    'navigate' => true,
                ],
                [
                    'label' => __('Trésorerie'),
                    'icon' => 'treasury',
                    'href' => route('pme.treasury.index'),
                    'current' => request()->routeIs('pme.treasury.*'),
                    'navigate' => true,
                ],
            ];

            $secondaryNavigation = [
                [
                    'label' => __('Paramètres'),
                    'icon' => 'settings',
                    'href' => route('pme.settings.index'),
                    'current' => request()->routeIs('pme.settings.*'),
                    'navigate' => true,
                ],
                [
                    'label' => __('Aide & Support'),
                    'icon' => 'support',
                    'href' => route('pme.support.index'),
                    'current' => request()->routeIs('pme.support.*'),
                    'navigate' => true,
                ],
            ];

            $headerBreadcrumbs = match (true) {
                request()->routeIs('pme.quotes.*') => [
                    'segments' => [
                        __('Tableau de bord'),
                        __('Devis'),
                    ],
                    'title' => __('Devis'),
                ],
                request()->routeIs('pme.invoices.*') => [
                    'segments' => [
                        __('Tableau de bord'),
                        __('Factures'),
                    ],
                    'title' => __('Factures'),
                ],
                request()->routeIs('pme.clients.*') => [
                    'segments' => [
                        __('Tableau de bord'),
                        __('Clients'),
                    ],
                    'title' => __('Clients'),
                ],
                request()->routeIs('pme.collection.*') => [
                    'segments' => [
                        __('Tableau de bord'),
                        __('Recouvrement'),
                    ],
                    'title' => __('Recouvrement'),
                ],
                request()->routeIs('pme.treasury.*') => [
                    'segments' => [
                        __('Tableau de bord'),
                        __('Trésorerie'),
                    ],
                    'title' => __('Trésorerie'),
                ],
                request()->routeIs('pme.settings.*') => [
                    'segments' => [
                        __('Compte'),
                        __('Paramètres'),
                    ],
                    'title' => __('Paramètres'),
                ],
                request()->routeIs('pme.support.*') => [
                    'segments' => [
                        __('Compte'),
                        __('Aide & Support'),
                    ],
                    'title' => __('Aide & Support'),
                ],
                default => [
                    'segments' => [
                        __('Dashboard'),
                        __('Overview'),
                    ],
                    'title' => $title ?? __('Tableau de bord'),
                ],
            };
        @endphp

        <div class="min-h-screen lg:flex" data-app-shell data-sidebar-open="false">
            <button
                type="button"
                class="fixed inset-0 z-30 hidden bg-slate-950/45 lg:hidden"
                data-app-shell-overlay
                data-app-shell-close
                aria-label="{{ __('Fermer le menu') }}"
            ></button>

            <aside
                class="fixed inset-y-0 left-0 z-40 flex w-[18.5rem] -translate-x-full flex-col border-e border-slate-200/80 bg-white px-5 py-5 transition-all duration-300 ease-out lg:translate-x-0"
                data-app-shell-sidebar
            >
                <div class="sidebar-header flex items-center justify-between gap-3">
                    <a href="{{ route('pme.dashboard') }}" class="flex items-center gap-3" wire:navigate>
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-primary text-accent shadow-[0_16px_35px_rgba(2,77,77,0.18)]">
                            <x-app-logo-icon class="size-6" />
                        </span>
                        <div class="min-w-0 sidebar-collapsible">
                            <p class="text-lg font-bold tracking-tight text-ink">Fayeku</p>
                            <p class="text-xs font-medium uppercase tracking-[0.24em] text-slate-400">PME</p>
                        </div>
                    </a>

                    <button
                        type="button"
                        class="app-shell-icon-button lg:hidden"
                        data-app-shell-close
                        aria-label="{{ __('Fermer le menu') }}"
                    >
                        <x-app.icon name="close" class="size-5" />
                    </button>

                </div>

                <div class="mt-4 flex flex-1 flex-col">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400 sidebar-collapsible">{{ __('Navigation') }}</p>
                        <nav class="mt-3 grid gap-2">
                            @foreach ($primaryNavigation as $item)
                                <div class="sidebar-nav-item relative">
                                    <a
                                        href="{{ $item['href'] }}"
                                        class="app-shell-nav-link"
                                        title="{{ $item['label'] }}"
                                        @if (($item['current'] ?? false)) aria-current="page" @elseif (($item['href'] ?? '#') === '#') aria-disabled="true" @endif
                                        @if ($item['navigate'] ?? false) wire:navigate @endif
                                    >
                                        <x-app.icon :name="$item['icon']" class="app-shell-nav-icon" />
                                        <span class="app-shell-nav-label sidebar-collapsible">{{ $item['label'] }}</span>
                                    </a>
                                    <div class="sidebar-nav-tooltip">{{ $item['label'] }}</div>
                                </div>
                            @endforeach
                        </nav>
                    </div>

                    <div class="sidebar-section-divider mt-auto border-t border-slate-200/80 pt-6">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400 sidebar-collapsible">{{ __('Compte') }}</p>
                        <nav class="mt-3 grid gap-2">
                            @foreach ($secondaryNavigation as $item)
                                <div class="sidebar-nav-item relative">
                                    <a
                                        href="{{ $item['href'] }}"
                                        class="app-shell-nav-link"
                                        title="{{ $item['label'] }}"
                                        @if (($item['current'] ?? false)) aria-current="page" @elseif (($item['href'] ?? '#') === '#') aria-disabled="true" @endif
                                        @if ($item['navigate'] ?? false) wire:navigate @endif
                                    >
                                        <x-app.icon :name="$item['icon']" class="app-shell-nav-icon" />
                                        <span class="app-shell-nav-label sidebar-collapsible">{{ $item['label'] }}</span>
                                    </a>
                                    <div class="sidebar-nav-tooltip">{{ $item['label'] }}</div>
                                </div>
                            @endforeach

                            <div class="sidebar-nav-item relative" x-data="{ open: false }">
                                <button
                                    type="button"
                                    class="app-shell-nav-link w-full"
                                    title="{{ __('Déconnexion') }}"
                                    data-test="logout-button"
                                    @click="open = true"
                                >
                                    <x-app.icon name="logout" class="app-shell-nav-icon" />
                                    <span class="app-shell-nav-label sidebar-collapsible">{{ __('Déconnexion') }}</span>
                                </button>
                                <div class="sidebar-nav-tooltip">{{ __('Déconnexion') }}</div>
                                <x-ui.confirm-modal
                                    :title="__('Déconnexion')"
                                    :description="__('Êtes-vous sûr de vouloir vous déconnecter de Fayeku PME ?')"
                                    form-action="{{ route('auth.logout') }}"
                                    :confirm-label="__('Se déconnecter')"
                                />
                            </div>
                        </nav>
                    </div>
                </div>
            </aside>

            <div class="app-shell-main flex min-h-screen min-w-0 flex-1 flex-col lg:pl-[18.5rem] transition-[padding] duration-300 ease-out">
                <header class="sticky top-0 z-50 border-b border-slate-200/80 bg-page/90 px-4 py-4 backdrop-blur sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex min-w-0 items-center gap-3">
                            <button
                                type="button"
                                class="app-shell-icon-button lg:hidden"
                                data-app-shell-toggle
                                aria-label="{{ __('Ouvrir le menu') }}"
                            >
                                <x-app.icon name="menu" class="size-5" />
                            </button>

                            <div class="group relative hidden lg:block">
                                <button
                                    type="button"
                                    class="inline-flex size-10 items-center justify-center rounded-xl bg-white text-slate-500 transition hover:text-primary"
                                    data-app-shell-collapse
                                    aria-label="{{ __('Afficher/Masquer la barre de navigation') }}"
                                >
                                    <x-app.icon name="layout-left" class="size-4" />
                                </button>
                                <div class="pointer-events-none absolute left-1/2 top-full z-[9999] mt-2 -translate-x-1/2 whitespace-nowrap rounded-lg bg-ink px-2.5 py-1.5 text-xs font-medium text-white opacity-0 transition-opacity group-hover:opacity-100">
                                    <span class="sidebar-tooltip-expand">{{ __('Afficher la barre de navigation') }}</span>
                                    <span class="sidebar-tooltip-collapse">{{ __('Masquer la barre de navigation') }}</span>
                                </div>
                            </div>

                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-500">
                                    @foreach ($headerBreadcrumbs['segments'] as $index => $segment)
                                        @if ($index > 0)
                                            <span class="px-2 text-slate-300">/</span>
                                        @endif

                                        <span @class([
                                            'text-slate-500' => $index < count($headerBreadcrumbs['segments']) - 1,
                                            'text-slate-700' => $index === count($headerBreadcrumbs['segments']) - 1,
                                        ])>{{ $segment }}</span>
                                    @endforeach
                                </p>
                                <h1 class="truncate text-xl font-semibold tracking-tight text-ink">{{ $headerBreadcrumbs['title'] ?? $title ?? __('Tableau de bord') }}</h1>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-3">
                                <div class="flex size-9 items-center justify-center rounded-xl bg-mist text-xs font-bold text-primary">
                                    {{ $user->initials() }}
                                </div>
                                <div class="hidden min-w-0 sm:block">
                                    <p class="truncate text-sm font-semibold text-ink">{{ $user->full_name }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ $smeCompany?->name ?? __('Mon entreprise') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </header>

                <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                    {{ $slot }}
                </main>
            </div>
        </div>


        <x-toast-notifications />

        @livewireScripts
        @fluxScripts
    </body>
</html>
