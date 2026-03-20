<x-layouts::auth :title="__('Mot de passe oublié')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Mot de passe oublié')"
            :description="__('Entrez votre numéro de téléphone pour recevoir un code de réinitialisation')"
        />

        <x-auth-session-status :status="session('status')" />

        <form method="POST" action="{{ route('auth.forgot-password.submit') }}" class="flex flex-col gap-5">
            @csrf

            <x-phone-input
                :label="__('Téléphone')"
                country-name="country_code"
                :country-value="old('country_code', 'SN')"
                phone-name="phone"
                :phone-value="old('phone')"
                :required="true"
                :autofocus="true"
                phone-placeholder="XX XXX XX XX"
            />
            <div class="-mt-0.5 space-y-1">
                <x-auth-field-error name="country_code" />
                <x-auth-field-error name="phone" />
            </div>

            <button type="submit" class="auth-button">
                {{ __('Envoyer le code') }}
            </button>
        </form>

        <p class="text-center text-sm leading-6 text-slate-600">
            <a href="{{ route('login') }}" wire:navigate class="auth-link">{{ __('Retour à la connexion') }}</a>
        </p>
    </div>
</x-layouts::auth>
