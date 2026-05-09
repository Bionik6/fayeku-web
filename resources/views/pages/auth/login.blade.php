<x-layouts::auth :title="__('Connexion')">

    {{-- ─── Section gauche : pitch produit ─── --}}
    <x-slot:aside>
        <div class="space-y-4">
            <span class="inline-flex items-center gap-2 rounded-full border border-[#024D4D]/10 bg-white/70 px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-[0.18em] text-teal">
                <span class="h-1.5 w-1.5 rounded-full bg-accent"></span>
                {{ __('Authentification') }}
            </span>

            <h1 class="text-balance text-3xl font-semibold leading-[1.05] text-[#024D4D] sm:text-4xl lg:text-[44px] lg:leading-[52px]">
                {{ __('Entrez dans votre espace Fayeku.') }}
            </h1>

            <p class="text-base leading-7 text-[#1D5D5D]">
                {{ __('Accédez à un espace sécurisé pour gérer la facturation, suivre les paiements et collaborer efficacement entre entreprise et cabinet comptable.') }}
            </p>
        </div>

        {{-- 3 bénéfices avec icônes --}}
        <ul class="space-y-5">
            <li class="flex items-start gap-4">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white text-primary shadow-sm">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <p class="text-base font-semibold text-[#024D4D]">{{ __('Faites-vous payer plus vite') }}</p>
                    <p class="mt-0.5 text-sm leading-snug text-[#1D5D5D]">{{ __('Relances WhatsApp automatisées, sans avoir à courir après vos clients.') }}</p>
                </div>
            </li>

            <li class="flex items-start gap-4">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white text-primary shadow-sm">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                        <line x1="12" y1="20" x2="12" y2="10" />
                        <line x1="18" y1="20" x2="18" y2="4" />
                        <line x1="6" y1="20" x2="6" y2="16" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <p class="text-base font-semibold text-[#024D4D]">{{ __('Pilotez votre trésorerie') }}</p>
                    <p class="mt-0.5 text-sm leading-snug text-[#1D5D5D]">{{ __('Prévisions à 90 jours, vision claire de l\'argent à encaisser.') }}</p>
                </div>
            </li>

            <li class="flex items-start gap-4">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white text-primary shadow-sm">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <p class="text-base font-semibold text-[#024D4D]">{{ __('Collaborez avec votre comptable') }}</p>
                    <p class="mt-0.5 text-sm leading-snug text-[#1D5D5D]">{{ __('Accès partagé, exports Sage et EBP, fin du mois sans stress.') }}</p>
                </div>
            </li>
        </ul>

        {{-- Social proof : avatars + texte --}}
        <div class="flex items-center gap-4 rounded-2xl bg-white/80 p-4 shadow-sm">
            <div class="flex -space-x-2">
                <span class="flex h-9 w-9 items-center justify-center rounded-full border-2 border-white bg-blue-100 text-xs font-bold text-blue-700">MD</span>
                <span class="flex h-9 w-9 items-center justify-center rounded-full border-2 border-white bg-amber-100 text-xs font-bold text-amber-700">AS</span>
                <span class="flex h-9 w-9 items-center justify-center rounded-full border-2 border-white bg-rose-100 text-xs font-bold text-rose-700">FK</span>
            </div>
            <p class="text-sm leading-snug text-[#1D5D5D]">
                <strong class="text-[#024D4D]">{{ __('Plus de 100 PME sénégalaises') }}</strong>
                {{ __('utilisent déjà Fayeku au quotidien.') }}
            </p>
        </div>
    </x-slot:aside>

    {{-- ─── Section droite : formulaire de connexion ─── --}}
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Connexion')"
            :description="__('Saisissez votre adresse email et votre mot de passe pour accéder à Fayeku.')"
        />

        <x-auth-session-status :status="session('status')" />

        <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-5">
            @csrf

            <label class="auth-label">
                <span>{{ __('Email') }} <span class="text-rose-500">*</span></span>
                <input
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="vous@example.com"
                    class="auth-input"
                />
                <x-auth-field-error name="email" />
            </label>

            <div class="auth-field-stack">
                <div class="flex items-center justify-between gap-1">
                    <span class="auth-field-label">{{ __('Mot de passe') }} <span class="text-rose-500">*</span></span>
                    <a href="{{ route('password.request') }}" wire:navigate class="text-sm auth-link">{{ __('Mot de passe oublié ?') }}</a>
                </div>
                <input
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                    placeholder="{{ __('Entrez votre mot de passe...') }}"
                    class="auth-input"
                />
                <x-auth-field-error name="password" />
            </div>

            <label class="auth-checkbox-row">
                <span class="auth-checkbox-wrap">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="auth-checkbox" />
                    <svg viewBox="0 0 14 14" fill="none" class="auth-checkbox-icon" aria-hidden="true">
                        <path d="M3 8L6 11L11 3.5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </span>
                <span>{{ __('Se souvenir de moi') }}</span>
            </label>

            <button type="submit" class="auth-button">
                {{ __('Se connecter') }}
            </button>
        </form>

        <p class="text-center text-sm leading-6 text-slate-600">
            <a href="{{ route('auth.magic-link.request') }}" wire:navigate class="auth-link">
                {{ __('Recevoir un lien de connexion par email') }}
            </a>
        </p>

        <hr class="border-slate-200" />

        <p class="text-center text-sm leading-6 text-slate-600">
            <span>{{ __('Pas encore de compte PME ?') }}</span>
            <a href="{{ route('register') }}" wire:navigate class="auth-link">{{ __('Créer un compte') }}</a>
            <br>
            <span class="text-xs text-slate-500">{{ __('Vous êtes expert-comptable ?') }}</span>
            <a href="{{ route('marketing.accountants.join') }}" class="auth-link text-xs">{{ __("Inscrire mon cabinet") }}</a>
        </p>
    </div>
</x-layouts::auth>
