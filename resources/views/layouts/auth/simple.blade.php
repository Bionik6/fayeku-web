<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-page antialiased">
        <div class="grid min-h-svh lg:grid-cols-[0.95fr_1.05fr]">
            <div class="hidden bg-primary lg:flex">
                <div class="mx-auto flex w-full max-w-2xl flex-col justify-between px-10 py-12 text-white">
                    <a href="{{ route('home') }}" class="inline-flex items-center gap-3" wire:navigate>
                        <img src="/logo-mark.svg" alt="Fayeku" class="h-12 w-12" />
                        <div>
                            <p class="text-2xl font-semibold">Fayeku</p>
                            <p class="text-sm text-white/70">Facturation & tresorerie</p>
                        </div>
                    </a>

                    <div class="space-y-6">
                        <span class="inline-flex rounded-full bg-white/10 px-4 py-2 text-sm font-semibold uppercase tracking-[0.2em] text-accent">Authentification</span>
                        <h1 class="max-w-xl text-balance text-4xl font-semibold leading-[1.05]">Une entree produit alignee avec la nouvelle vitrine Fayeku.</h1>
                        <p class="max-w-xl text-lg leading-8 text-white/80">Connexion par telephone, verification OTP a l inscription et parcours clairs pour PME comme cabinets.</p>
                    </div>

                    <div class="rounded-[2rem] bg-white/8 p-6">
                        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-accent">Pourquoi ca change</p>
                        <p class="mt-3 text-base leading-7 text-white/80">Les ecrans d authentification reprennent maintenant les codes de marque du site marketing pour offrir une experience plus coherente entre acquisition et activation.</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-center px-6 py-10 md:px-10">
                <div class="w-full max-w-xl">
                    <a href="{{ route('home') }}" class="mb-8 inline-flex items-center gap-3 lg:hidden" wire:navigate>
                        <img src="/logo-mark.svg" alt="Fayeku" class="h-10 w-10" />
                        <div>
                            <p class="text-lg font-semibold text-primary">Fayeku</p>
                            <p class="text-xs text-slate-500">Facturation & tresorerie</p>
                        </div>
                    </a>

                    <div class="rounded-[2rem] border border-primary/10 bg-white p-6 shadow-soft sm:p-8">
                        <div class="flex flex-col gap-6">
                            {{ $slot }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
