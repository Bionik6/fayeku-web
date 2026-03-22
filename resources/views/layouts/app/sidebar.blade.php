<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-page text-ink antialiased">
        @php
            use Modules\Compta\Portfolio\Services\AlertService;

            $user = auth()->user();
            $firm = $user->companies()->where('type', 'accountant_firm')->first();
            $alertsCount = $firm ? app(AlertService::class)->count($firm, $user) : 0;

            $primaryNavigation = [
                [
                    'label' => __('Dashboard'),
                    'icon' => 'dashboard',
                    'href' => route('dashboard'),
                    'current' => request()->routeIs('dashboard'),
                    'navigate' => true,
                ],
                [
                    'label' => __('Clients'),
                    'icon' => 'clients',
                    'href' => route('clients.index'),
                    'current' => request()->routeIs('clients.*'),
                    'navigate' => true,
                ],
                [
                    'label' => __('Alertes'),
                    'icon' => 'bell',
                    'href' => route('alerts.index'),
                    'current' => request()->routeIs('alerts.*'),
                    'navigate' => true,
                    'badge' => $alertsCount,
                ],
                [
                    'label' => __('Export Groupé'),
                    'icon' => 'export',
                    'href' => route('export.index'),
                    'current' => request()->routeIs('export.*'),
                    'navigate' => true,
                ],
                [
                    'label' => __('Commissions'),
                    'icon' => 'commissions',
                    'href' => '#',
                ],
                [
                    'label' => __('Invitations'),
                    'icon' => 'invitations',
                    'href' => '#',
                ],
            ];

            $secondaryNavigation = [
                [
                    'label' => __('Paramètres'),
                    'icon' => 'settings',
                    'href' => route('profile.edit'),
                    'current' => request()->routeIs('profile.*'),
                    'navigate' => true,
                ],
                [
                    'label' => __('Aide & Support'),
                    'icon' => 'support',
                    'href' => '#',
                ],
            ];
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
                class="fixed inset-y-0 left-0 z-40 flex w-[18.5rem] -translate-x-full flex-col border-e border-slate-200/80 bg-white px-5 py-5 transition duration-300 ease-out lg:translate-x-0"
                data-app-shell-sidebar
            >
                <div class="flex items-center justify-between gap-3">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-3" wire:navigate>
                        <span class="flex size-11 items-center justify-center rounded-2xl bg-primary text-accent shadow-[0_16px_35px_rgba(2,77,77,0.18)]">
                            <x-app-logo-icon class="size-6" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-lg font-bold tracking-tight text-ink">Fayeku</p>
                            <p class="text-xs font-medium uppercase tracking-[0.24em] text-slate-400">Compta</p>
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

                <div class="mt-8 flex flex-1 flex-col">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">{{ __('Navigation') }}</p>
                        <nav class="mt-3 grid gap-2">
                            @foreach ($primaryNavigation as $item)
                                <a
                                    href="{{ $item['href'] }}"
                                    class="app-shell-nav-link"
                                    @if (($item['current'] ?? false)) aria-current="page" @elseif (($item['href'] ?? '#') === '#') aria-disabled="true" @endif
                                    @if ($item['navigate'] ?? false) wire:navigate @endif
                                >
                                    <x-app.icon :name="$item['icon']" class="app-shell-nav-icon" />
                                    <span class="app-shell-nav-label">{{ $item['label'] }}</span>
                                    @if (($item['badge'] ?? 0) > 0)
                                        <span class="ml-auto rounded-full bg-rose-500 px-1.5 py-0.5 text-xs font-bold leading-none text-white">
                                            {{ $item['badge'] }}
                                        </span>
                                    @endif
                                </a>
                            @endforeach
                        </nav>
                    </div>

                    <div class="mt-auto border-t border-slate-200/80 pt-6">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">{{ __('Compte') }}</p>
                        <nav class="mt-3 grid gap-2">
                            @foreach ($secondaryNavigation as $item)
                                <a
                                    href="{{ $item['href'] }}"
                                    class="app-shell-nav-link"
                                    @if (($item['current'] ?? false)) aria-current="page" @elseif (($item['href'] ?? '#') === '#') aria-disabled="true" @endif
                                    @if ($item['navigate'] ?? false) wire:navigate @endif
                                >
                                    <x-app.icon :name="$item['icon']" class="app-shell-nav-icon" />
                                    <span class="app-shell-nav-label">{{ $item['label'] }}</span>
                                </a>
                            @endforeach

                            <flux:modal.trigger name="confirm-logout" class="w-full">
                                <button
                                    type="button"
                                    class="app-shell-nav-link w-full"
                                    data-test="logout-button"
                                >
                                    <x-app.icon name="logout" class="app-shell-nav-icon" />
                                    <span class="app-shell-nav-label">{{ __('Déconnexion') }}</span>
                                </button>
                            </flux:modal.trigger>
                        </nav>
                    </div>
                </div>
            </aside>

            <div class="flex min-h-screen min-w-0 flex-1 flex-col lg:pl-[18.5rem]">
                <header class="sticky top-0 z-20 border-b border-slate-200/80 bg-page/90 px-4 py-4 backdrop-blur sm:px-6 lg:px-8">
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

                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-500">
                                    <span>{{ __('Dashboard') }}</span>
                                    <span class="px-2 text-slate-300">/</span>
                                    <span class="text-slate-700">{{ __('Overview') }}</span>
                                </p>
                                <h1 class="truncate text-xl font-semibold tracking-tight text-ink">{{ $title ?? __('Dashboard') }}</h1>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <button type="button" class="app-shell-icon-button hidden sm:inline-flex" aria-label="{{ __('Notifications') }}">
                                <x-app.icon name="bell" class="size-5" />
                            </button>

                            <div class="flex items-center gap-3">
                                <div class="flex size-9 items-center justify-center rounded-xl bg-mist text-xs font-bold text-primary">
                                    {{ $user->initials() }}
                                </div>
                                <div class="hidden min-w-0 sm:block">
                                    <p class="truncate text-sm font-semibold text-ink">{{ $user->full_name }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ __('Cabinet Fayeku') }}</p>
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

        <flux:modal name="confirm-logout" variant="bare" closable class="!bg-transparent !p-0 !shadow-none !ring-0">
            <div class="flex h-[315px] w-[450px] max-w-[450px] flex-col rounded-[2rem] bg-white px-8 pt-7 pb-8 text-center shadow-[0_28px_70px_rgba(15,23,42,0.18)]">
                <div class="flex justify-center">
                    <div class="flex size-24 items-center justify-center rounded-full bg-rose-100/60">
                        <div class="flex size-16 items-center justify-center rounded-full bg-white shadow-sm">
                            <x-app.icon name="logout-modal" class="size-8 text-ink" />
                        </div>
                    </div>
                </div>

                <div class="mt-5 px-3">
                    <flux:heading size="xl" class="!font-bold !text-black">{{ __('Déconnexion') }}</flux:heading>
                    <flux:subheading class="mt-3 !text-lg !leading-8 !text-black">{{ __('Êtes-vous sûr de vouloir vous déconnecter de Fayeku Compta?') }}</flux:subheading>
                </div>

                <div class="mt-8 flex w-full gap-4 px-1">
                    <flux:modal.close class="flex-1">
                        <flux:button class="w-full !rounded-[1.75rem] !border-0 !px-6 !py-4 !text-lg !font-semibold !text-white hover:!bg-zinc-800 !bg-zinc-700">
                            {{ __('Annuler') }}
                        </flux:button>
                    </flux:modal.close>

                    <form method="POST" action="{{ route('auth.logout') }}" class="flex-1">
                        @csrf
                        <flux:button type="submit" variant="danger" class="w-full !rounded-[1.75rem] !border-0 !px-6 !py-4 !text-lg !font-semibold">
                            {{ __('Se déconnecter') }}
                        </flux:button>
                    </form>
                </div>
            </div>
        </flux:modal>

        @fluxScripts
    </body>
</html>
