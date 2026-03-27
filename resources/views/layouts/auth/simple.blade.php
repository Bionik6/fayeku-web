<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => $title ?? null])
    </head>
    <body class="marketing-site">
        <div class="min-h-screen overflow-x-hidden bg-[#024D4D]">
            <div class="grid min-h-screen lg:grid-cols-[0.94fr_1.06fr]">
                <div class="bg-[#D9EEE6] px-6 py-12 sm:px-10 lg:px-14 lg:py-16">
                    <div class="max-w-xl space-y-8 lg:ml-auto lg:mr-10 lg:mt-10">
                        <a href="{{ route('home') }}" class="inline-flex items-center gap-3 text-[#024D4D]" wire:navigate aria-label="Accueil Fayeku">
                            <img src="/logo-mark.svg" alt="Fayeku" class="h-14 w-14" />
                            <div>
                                <p class="text-2xl font-semibold">Fayeku</p>
                                <p class="text-sm text-[#1D5D5D]">Facturation & trésorerie</p>
                            </div>
                        </a>

                        <div class="space-y-4">
                            <span class="inline-flex rounded-full border border-[#024D4D]/10 bg-white/70 px-4 py-2 text-sm font-semibold uppercase tracking-[0.2em] text-teal">
                                AUTHENTIFICATION
                            </span>
                            <h1 class="max-w-xl text-balance text-4xl font-semibold leading-[1.08] text-[#024D4D] sm:text-5xl lg:text-[52px] lg:leading-[60px]">
                                Entrez dans votre espace Fayeku.
                            </h1>
                            <p class="max-w-xl text-xl leading-9 text-[#1D5D5D]">
                                Accédez à un espace sécurisé pour gérer la facturation, suivre les paiements et collaborer efficacement entre entreprise et cabinet comptable.
                            </p>
                        </div>

                        <div class="space-y-6 pt-2 text-lg leading-8 text-[#1D5D5D]">
                            <p class="max-w-2xl"><span class="font-semibold text-accent">✓</span> Accès sécurisé par téléphone</p>
                            <p class="max-w-2xl"><span class="font-semibold text-accent">✓</span> Suivi clair de la facturation et des paiements</p>
                            <p class="max-w-2xl"><span class="font-semibold text-accent">✓</span> Pensé pour les PME et les cabinets comptables</p>
                        </div>
                    </div>
                </div>

                <div class="relative overflow-hidden px-5 py-12 sm:px-8 lg:px-14 lg:py-16">
                    <div class="absolute left-1/2 top-0 hidden h-full w-px bg-white/18 lg:block"></div>
                    <div class="absolute -right-24 top-24 hidden h-56 w-56 rounded-full border-[12px] border-white/40 lg:block"></div>
                    <div class="absolute -bottom-16 right-24 hidden h-64 w-64 rounded-full border-[12px] border-white/40 lg:block"></div>

                    <div class="mx-auto max-w-xl lg:ml-10 lg:mr-auto lg:mt-4">
                        <div class="relative">
                            <div class="absolute inset-0 translate-x-3 translate-y-3 rounded-[2rem] bg-accent" aria-hidden="true"></div>
                            <div class="relative rounded-[2rem] border border-[#024D4D]/10 bg-white p-6 shadow-soft sm:p-8">
                                <div class="flex flex-col gap-6">
                                    {{ $slot }}
                                </div>
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
