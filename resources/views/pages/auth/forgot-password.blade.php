<x-layouts::auth :title="__('Mot de passe oublié')">
    <div class="flex flex-col gap-6" x-data="{ profile: @js(old('profile', 'sme')) }">
        <x-auth-header
            :title="__('Mot de passe oublié')"
            :description="__('Choisissez votre profil pour recevoir un code (PME) ou un lien (Cabinet) de réinitialisation.')"
        />

        <x-auth-session-status :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-5">
            @csrf

            <x-auth.profile-toggle :value="old('profile', 'sme')" />

            {{-- Champs PME : téléphone (required dynamique via Alpine, suit le profil sélectionné) --}}
            <div x-show="profile === 'sme'" x-cloak class="flex flex-col gap-1">
                <x-phone-input
                    :label="__('Téléphone')"
                    country-name="country_code"
                    :country-value="old('country_code', 'SN')"
                    phone-name="phone"
                    :phone-value="old('phone')"
                    required-when="profile === 'sme'"
                    phone-placeholder="XX XXX XX XX"
                    :countries="['SN' => config('fayeku.countries.SN.label', 'SEN (+221)')]"
                />
                <div class="-mt-0.5 space-y-1">
                    <x-auth-field-error name="country_code" />
                    <x-auth-field-error name="phone" />
                </div>
            </div>

            {{-- Champs Cabinet : email (required dynamique via Alpine) --}}
            <div x-show="profile === 'accountant'" x-cloak>
                <label class="auth-label">
                    <span>{{ __('Email') }} *</span>
                    <input
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        x-bind:required="profile === 'accountant'"
                        autocomplete="username"
                        placeholder="cabinet@example.com"
                        class="auth-input"
                    />
                    <x-auth-field-error name="email" />
                </label>
            </div>

            <button type="submit" class="auth-button">
                <span x-show="profile === 'sme'">{{ __('Envoyer le code') }}</span>
                <span x-show="profile === 'accountant'" x-cloak>{{ __('Envoyer le lien') }}</span>
            </button>
        </form>

        <p class="text-center text-sm leading-6 text-slate-600">
            <a href="{{ route('login') }}" wire:navigate class="auth-link">{{ __('Retour à la connexion') }}</a>
        </p>
    </div>
</x-layouts::auth>
