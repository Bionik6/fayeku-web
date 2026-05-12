@php
    $inviteeFirstName = '';
    $inviteeLastName = '';
    $inviteeEmail = $invitation?->invitee_email ?? '';

    if (isset($invitation) && $invitation?->invitee_name) {
        $parts = explode(' ', $invitation->invitee_name, 2);
        $inviteeFirstName = $parts[0] ?? '';
        $inviteeLastName = $parts[1] ?? '';
    }

    $isReferral = (isset($invitation) && $invitation) || (isset($joiningFirm) && $joiningFirm);
    $referringFirmName = $invitation?->accountantFirm?->name ?? $joiningFirm?->name ?? null;
@endphp

<x-layouts::auth :title="__('Inscription')">

    {{-- ─── Section gauche : pitch produit ─── --}}
    <x-slot:aside>
        <div class="space-y-4">
            <span class="inline-flex items-center gap-2 rounded-full border border-[#024D4D]/10 bg-white/70 px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-[0.18em] text-teal">
                <span class="h-1.5 w-1.5 rounded-full bg-accent"></span>
                @if ($referringFirmName)
                    {{ __('Recommandé par votre cabinet') }}
                @else
                    {{ __('30 jours d\'essai gratuit') }}
                @endif
            </span>

            <h1 class="text-balance text-3xl font-semibold leading-[1.05] text-[#024D4D] sm:text-4xl lg:text-[44px] lg:leading-[52px]">
                @if ($referringFirmName)
                    {{ $referringFirmName }} {{ __('vous recommande Fayeku.') }}
                @else
                    {{ __('Reprenez le contrôle de votre trésorerie.') }}
                @endif
            </h1>

            <p class="text-base leading-7 text-[#1D5D5D]">
                {{ __('Facturation pro, relances WhatsApp automatisées et vision claire de votre cash. Sans engagement.') }}
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

    {{-- ─── Section droite : formulaire d'inscription ─── --}}

    <header class="space-y-1.5">
        <h2 class="text-3xl font-semibold leading-tight text-ink sm:text-[34px]">{{ __('Créer votre compte') }}</h2>
        <p class="text-base text-slate-500">{{ __('30 jours gratuits. Sans engagement.') }}</p>
    </header>

    @if ($isReferral)
        <div class="rounded-xl border border-teal-200 bg-teal-50 p-4">
            <p class="text-sm font-semibold text-teal-800">
                {{ $referringFirmName }} {{ __('vous recommande Fayeku') }}
            </p>
            @if ($invitation?->invitee_company_name)
                <p class="mt-1 text-sm text-teal-700">{{ $invitation->invitee_company_name }}</p>
            @endif
            @if ($invitation?->recommended_plan === 'essentiel')
                <p class="mt-1 text-sm text-teal-700">{{ __('Profitez de 30 jours offerts sur le plan Essentiel.') }}</p>
            @endif
        </div>
    @else
        <a
            href="{{ route('marketing.accountants.join') }}"
            class="flex items-start gap-3 rounded-xl bg-mist p-4 transition hover:bg-mist/70"
        >
            <flux:icon name="information-circle" class="mt-0.5 h-5 w-5 shrink-0 text-primary" />
            <p class="text-sm text-[#024D4D]">
                {{ __('Vous êtes expert-comptable ?') }}
                <strong class="font-semibold underline underline-offset-4">{{ __('Inscription dédiée ici →') }}</strong>
            </p>
        </a>
    @endif

    <x-auth-session-status :status="session('status')" />

    <form method="POST" action="{{ route('register.submit') }}" class="flex flex-col gap-5">
        @csrf

        @if (isset($invitation) && $invitation)
            <input type="hidden" name="invitation_token" value="{{ $invitation->token }}" />
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="auth-label">
                <span>{{ __('Prénom') }} <span class="text-rose-500">*</span></span>
                <input
                    name="first_name"
                    type="text"
                    value="{{ old('first_name', $inviteeFirstName) }}"
                    required
                    autofocus
                    autocomplete="given-name"
                    placeholder="Ibrahima"
                    class="auth-input"
                />
                <x-auth-field-error name="first_name" />
            </label>

            <label class="auth-label">
                <span>{{ __('Nom') }} <span class="text-rose-500">*</span></span>
                <input
                    name="last_name"
                    type="text"
                    value="{{ old('last_name', $inviteeLastName) }}"
                    required
                    autocomplete="family-name"
                    placeholder="Ciss"
                    class="auth-input"
                />
                <x-auth-field-error name="last_name" />
            </label>
        </div>

        <label class="auth-label">
            <span>{{ __('Email professionnel') }} <span class="text-rose-500">*</span></span>
            <input
                name="email"
                type="email"
                value="{{ old('email', $inviteeEmail) }}"
                required
                autocomplete="email"
                placeholder="vous@entreprise.sn"
                class="auth-input"
            />
            <x-auth-field-error name="email" />
        </label>

        <div class="grid gap-1">
            <x-phone-input
                :label="__('Téléphone')"
                country-name="country_code"
                :country-value="old('country_code', $inviteePhone['country_code'] ?? 'SN')"
                phone-name="phone"
                :phone-value="old('phone', $inviteePhone['local_number'] ?? '')"
                :required="true"
                phone-placeholder="77 XXX XX XX"
                :countries="['SN' => config('fayeku.countries.SN.label', 'SEN (+221)')]"
            />
            <x-auth-field-error name="country_code" />
            <x-auth-field-error name="phone" />
        </div>

        {{-- Mot de passe avec œil de révélation --}}
        <div class="auth-label" x-data="{ shown: false }">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-slate-700">{{ __('Mot de passe') }} <span class="text-rose-500">*</span></span>
                <span class="text-xs text-slate-400">{{ __('8 caractères minimum') }}</span>
            </div>
            <div class="relative">
                <input
                    name="password"
                    :type="shown ? 'text' : 'password'"
                    required
                    minlength="8"
                    autocomplete="new-password"
                    placeholder="••••••••"
                    class="auth-input pr-12"
                />
                <button
                    type="button"
                    @click="shown = !shown"
                    class="absolute inset-y-0 right-0 flex w-10 cursor-pointer items-center justify-center text-slate-400 hover:text-primary"
                    :aria-label="shown ? '{{ __('Masquer le mot de passe') }}' : '{{ __('Afficher le mot de passe') }}'"
                >
                    <flux:icon name="eye" class="h-5 w-5" x-show="!shown" />
                    <flux:icon name="eye-slash" class="h-5 w-5" x-show="shown" x-cloak />
                </button>
            </div>
            <x-auth-field-error name="password" />
        </div>

        <button type="submit" class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-primary px-5 py-4 text-base font-semibold text-white shadow-soft transition hover:bg-primary/90">
            {{ __('Démarrer mes 30 jours gratuits') }}
            <flux:icon name="arrow-right" class="h-4 w-4" />
        </button>

        <p class="text-center text-xs leading-relaxed text-slate-500">
            {{ __('En créant un compte, vous acceptez nos') }}
            <a href="{{ route('marketing.legal') }}" class="font-medium text-primary underline">{{ __('CGU') }}</a>
            {{ __('et notre') }}
            <a href="{{ route('marketing.privacy') }}" class="font-medium text-primary underline">{{ __('politique de confidentialité') }}</a>.
        </p>
    </form>

    <hr class="border-slate-100" />

    <p class="text-center text-sm leading-6 text-slate-600">
        {{ __('Vous avez déjà un compte ?') }}
        <a href="{{ route('login') }}" wire:navigate class="font-semibold text-primary hover:underline">{{ __('Se connecter') }}</a>
    </p>
</x-layouts::auth>
