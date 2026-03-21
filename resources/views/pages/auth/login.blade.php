<x-layouts::auth :title="__('Connexion')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Se connecter')" :description="__('Entrez votre numéro de téléphone et votre mot de passe')" />

        <x-auth-session-status :status="session('status')" />

        <form method="POST" action="{{ route('auth.login.submit') }}" class="flex flex-col gap-5">
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

            <div class="auth-field-stack">
                <div class="flex items-center justify-between gap-1">
                    <span class="auth-field-label">{{ __('Mot de passe') }} *</span>
                    <a href="{{ route('auth.forgot-password') }}" wire:navigate class="text-sm auth-link">{{ __('Mot de passe oublié ?') }}</a>
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
            <span>{{ __('Pas encore de compte ?') }}</span>
            <a href="{{ route('auth.register') }}" wire:navigate class="auth-link">{{ __('Créer un compte') }}</a>
        </p>
    </div>
</x-layouts::auth>
