@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => $title])
    </head>
    <body class="marketing-site">
        <div class="min-h-screen overflow-x-hidden bg-[#024D4D]">
            <div class="grid min-h-screen lg:grid-cols-[0.94fr_1.06fr]">

                {{-- Section gauche : mint avec contenu personnalisable par page --}}
                <div class="relative flex overflow-hidden bg-[#D9EEE6] px-6 py-10 sm:px-10 lg:px-14 lg:py-12">
                    {{-- Cercles décoratifs en arrière-plan --}}
                    <div class="pointer-events-none absolute -left-20 top-10 hidden h-72 w-72 rounded-full bg-white/40 blur-2xl lg:block" aria-hidden="true"></div>
                    <div class="pointer-events-none absolute -bottom-20 left-32 hidden h-80 w-80 rounded-full bg-white/30 blur-3xl lg:block" aria-hidden="true"></div>

                    <div class="relative flex w-full max-w-xl flex-col gap-7 lg:ml-auto lg:mr-10">
                        {{-- Header logo en haut --}}
                        <a href="{{ route('home') }}" class="inline-flex items-center gap-3 text-[#024D4D]" wire:navigate aria-label="Accueil Fayeku">
                            <img src="/logo-mark.svg" alt="Fayeku" class="h-12 w-12" />
                            <div>
                                <p class="text-lg font-semibold leading-tight">Fayeku</p>
                                <p class="text-xs text-[#1D5D5D]">Facturation & trésorerie</p>
                            </div>
                        </a>

                        @isset($aside)
                            {{ $aside }}
                        @else
                            {{-- Fallback : contenu par défaut pour les pages auth qui ne fournissent pas d'aside --}}
                            <div class="space-y-4">
                                <span class="inline-flex items-center gap-2 rounded-full border border-[#024D4D]/10 bg-white/70 px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-[0.18em] text-teal">
                                    <span class="h-1.5 w-1.5 rounded-full bg-accent"></span>
                                    {{ __('Authentification') }}
                                </span>
                                <h1 class="text-balance text-3xl font-semibold leading-[1.08] text-[#024D4D] sm:text-4xl lg:text-[44px] lg:leading-[52px]">
                                    {{ __('Entrez dans votre espace Fayeku.') }}
                                </h1>
                                <p class="text-base leading-7 text-[#1D5D5D]">
                                    {{ __('Accédez à un espace sécurisé pour gérer la facturation, suivre les paiements et collaborer efficacement entre entreprise et cabinet comptable.') }}
                                </p>
                            </div>
                        @endisset
                    </div>
                </div>

                {{-- Section droite : carte du formulaire --}}
                <div class="relative overflow-hidden px-5 py-12 sm:px-8 lg:px-14 lg:py-16">
                    <div class="absolute -right-24 top-24 hidden h-56 w-56 rounded-full border-[14px] border-white/15 lg:block" aria-hidden="true"></div>
                    <div class="absolute -bottom-16 right-24 hidden h-72 w-72 rounded-full border-[14px] border-white/15 lg:block" aria-hidden="true"></div>

                    <div class="relative mx-auto max-w-xl lg:ml-10 lg:mr-auto lg:mt-4">
                        <div class="relative rounded-3xl border border-[#024D4D]/10 bg-white p-6 shadow-[0_30px_60px_-15px_rgba(2,77,77,0.45)] sm:p-8">
                            <div class="flex flex-col gap-6">
                                {{ $slot }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @livewireScripts
        @fluxScripts
    </body>
</html>
