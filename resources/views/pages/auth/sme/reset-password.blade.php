<x-layouts::auth :title="__('Réinitialiser le mot de passe')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Réinitialiser le mot de passe')"
            :description="__('Entrez le code reçu par SMS et votre nouveau mot de passe')"
        />

        <x-auth-session-status :status="session('status')" />

        <form method="POST" action="{{ route('sme.auth.reset-password.submit') }}" class="flex flex-col gap-5">
            @csrf

            <label class="auth-label">
                <span>{{ __('Code de vérification') }} *</span>
                <input
                    name="code"
                    type="text"
                    inputmode="numeric"
                    pattern="[0-9]{6}"
                    maxlength="6"
                    required
                    autofocus
                    autocomplete="one-time-code"
                    placeholder="000000"
                    class="auth-input text-center text-lg tracking-[0.35em]"
                />
                <x-auth-field-error name="code" />
            </label>

            <label class="auth-label">
                <span>{{ __('Nouveau mot de passe') }} *</span>
                <input
                    name="password"
                    type="password"
                    required
                    autocomplete="new-password"
                    placeholder="{{ __('Entrez votre nouveau mot de passe...') }}"
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
                {{ __('Réinitialiser le mot de passe') }}
            </button>
        </form>

        <p class="text-center text-sm leading-6 text-slate-600">
            <a href="{{ route('login') }}" wire:navigate class="auth-link">{{ __('Retour à la connexion') }}</a>
        </p>
    </div>
</x-layouts::auth>
