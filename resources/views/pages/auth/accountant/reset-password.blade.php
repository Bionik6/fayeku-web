<x-layouts::auth :title="__('Nouveau mot de passe')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Choisir un nouveau mot de passe')"
            :description="__('Définissez un nouveau mot de passe pour votre compte cabinet.')"
        />

        <x-auth-session-status :status="session('status')" />

        <form method="POST" action="{{ route('accountant.auth.reset-password.submit') }}" class="flex flex-col gap-5">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}" />

            <label class="auth-label">
                <span>{{ __('Email') }} *</span>
                <input
                    name="email"
                    type="email"
                    value="{{ old('email', $email) }}"
                    required
                    autocomplete="username"
                    placeholder="cabinet@example.com"
                    class="auth-input"
                />
                <x-auth-field-error name="email" />
            </label>

            <label class="auth-label">
                <span>{{ __('Nouveau mot de passe') }} *</span>
                <input
                    name="password"
                    type="password"
                    required
                    autofocus
                    autocomplete="new-password"
                    minlength="8"
                    placeholder="{{ __('Minimum 8 caractères') }}"
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
                    placeholder="{{ __('Confirmez votre mot de passe') }}"
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
