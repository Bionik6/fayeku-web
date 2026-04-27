<x-layouts::auth :title="__('Mot de passe oublié')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Mot de passe oublié')"
            :description="__('Entrez votre adresse email pour recevoir un lien de réinitialisation')"
        />

        <x-auth-session-status :status="session('status')" />

        <form method="POST" action="{{ route('accountant.auth.forgot-password.submit') }}" class="flex flex-col gap-5">
            @csrf

            <label class="auth-label">
                <span>{{ __('Email') }} *</span>
                <input
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="cabinet@example.com"
                    class="auth-input"
                />
                <x-auth-field-error name="email" />
            </label>

            <button type="submit" class="auth-button">
                {{ __('Envoyer le lien') }}
            </button>
        </form>

        <p class="text-center text-sm leading-6 text-slate-600">
            <a href="{{ route('accountant.auth.login') }}" wire:navigate class="auth-link">{{ __('Retour à la connexion') }}</a>
        </p>
    </div>
</x-layouts::auth>
