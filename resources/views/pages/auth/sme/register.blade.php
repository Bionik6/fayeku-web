@php
    $inviteeFirstName = '';
    $inviteeLastName = '';
    $inviteeEmail = $invitation?->invitee_email ?? '';

    if (isset($invitation) && $invitation?->invitee_name) {
        $parts = explode(' ', $invitation->invitee_name, 2);
        $inviteeFirstName = $parts[0] ?? '';
        $inviteeLastName = $parts[1] ?? '';
    }
@endphp

<x-layouts::auth :title="__('Inscription')">
    <div class="flex flex-col gap-6">
        @if (isset($invitation) && $invitation)
            <div class="rounded-xl border border-teal-200 bg-teal-50 p-4">
                <p class="text-sm font-semibold text-teal-800">
                    {{ $invitation->accountantFirm?->name }} {{ __('vous recommande Fayeku') }}
                </p>
                <p class="mt-1 text-sm text-teal-700">
                    @if ($invitation->invitee_company_name)
                        {{ $invitation->invitee_company_name }} &mdash;
                    @endif
                    {{ __('Simplifiez votre facturation et gestion commerciale.') }}
                    @if ($invitation->recommended_plan === 'essentiel')
                        {{ __('Profitez de 2 mois offerts sur le plan Essentiel.') }}
                    @endif
                </p>
            </div>

            <x-auth-header
                :title="__('Créer votre compte')"
                :description="__('Complétez votre inscription pour rejoindre Fayeku.')"
            />
        @elseif (isset($joiningFirm) && $joiningFirm)
            <div class="rounded-xl border border-teal-200 bg-teal-50 p-4">
                <p class="text-sm font-semibold text-teal-800">
                    {{ $joiningFirm->name }} {{ __('vous recommande Fayeku') }}
                </p>
                <p class="mt-1 text-sm text-teal-700">
                    {{ __('Simplifiez votre facturation et gestion commerciale. Profitez de 2 mois offerts sur le plan Essentiel.') }}
                </p>
            </div>

            <x-auth-header
                :title="__('Créer votre compte')"
                :description="__('Complétez votre inscription pour rejoindre Fayeku.')"
            />
        @else
            <x-auth-header :title="__('Créer un compte PME')" :description="__('Remplissez les informations ci-dessous pour créer votre compte')" />

            <div class="rounded-xl border border-teal-200 bg-teal-50 p-4">
                <p class="text-sm font-semibold text-teal-800">
                    {{ __('Cette page est réservée aux PME.') }}
                </p>
                <p class="mt-1 text-sm text-teal-700">
                    {{ __('Vous êtes expert-comptable ?') }}
                    <a href="{{ route('marketing.accountants.join') }}" class="font-medium underline">
                        {{ __('Inscrivez-vous ici →') }}
                    </a>
                </p>
            </div>
        @endif

        <x-auth-session-status :status="session('status')" />

        <form method="POST" action="{{ route('register.submit') }}" class="flex flex-col gap-5">
            @csrf

            @if (isset($invitation) && $invitation)
                <input type="hidden" name="invitation_token" value="{{ $invitation->token }}" />
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="auth-label">
                    <span>{{ __('Prénom') }} *</span>
                    <input
                        name="first_name"
                        type="text"
                        value="{{ old('first_name', $inviteeFirstName) }}"
                        required
                        autofocus
                        autocomplete="given-name"
                        placeholder="{{ __('Entrez votre prénom...') }}"
                        class="auth-input"
                    />
                    <x-auth-field-error name="first_name" />
                </label>

                <label class="auth-label">
                    <span>{{ __('Nom') }} *</span>
                    <input
                        name="last_name"
                        type="text"
                        value="{{ old('last_name', $inviteeLastName) }}"
                        required
                        autocomplete="family-name"
                        placeholder="{{ __('Entrez votre nom') }}"
                        class="auth-input"
                    />
                    <x-auth-field-error name="last_name" />
                </label>
            </div>

            <label class="auth-label">
                <span>{{ __('Email') }} *</span>
                <input
                    name="email"
                    type="email"
                    value="{{ old('email', $inviteeEmail) }}"
                    required
                    autocomplete="email"
                    placeholder="vous@example.com"
                    class="auth-input"
                />
                <x-auth-field-error name="email" />
            </label>

            <x-phone-input
                :label="__('Téléphone')"
                country-name="country_code"
                :country-value="old('country_code', $inviteePhone['country_code'] ?? 'SN')"
                phone-name="phone"
                :phone-value="old('phone', $inviteePhone['local_number'] ?? '')"
                :required="true"
                phone-placeholder="XX XXX XX XX"
                :countries="['SN' => config('fayeku.countries.SN.label', 'SEN (+221)')]"
            />
            <div class="-mt-0.5 space-y-1">
                <x-auth-field-error name="country_code" />
                <x-auth-field-error name="phone" />
            </div>

            <label class="auth-label">
                <span>{{ __('Mot de passe') }} *</span>
                <input
                    name="password"
                    type="password"
                    required
                    autocomplete="new-password"
                    placeholder="{{ __('Entrez votre mot de passe...') }}"
                    class="auth-input"
                />
                <x-auth-field-error name="password" />
            </label>

            <label class="auth-label">
                <span>{{ __('Confirmer le mot de passe') }} *</span>
                <input
                    name="password_confirmation"
                    type="password"
                    required
                    autocomplete="new-password"
                    placeholder="{{ __('Confirmez votre mot de passe...') }}"
                    class="auth-input"
                />
            </label>

            <button type="submit" class="auth-button">
                {{ __('Créer un compte') }}
            </button>
        </form>

        <p class="text-center text-sm leading-6 text-slate-600">
            <span>{{ __('Vous avez déjà un compte ?') }}</span>
            <a href="{{ route('login') }}" wire:navigate class="auth-link">{{ __('Se connecter') }}</a>
        </p>
    </div>
</x-layouts::auth>
