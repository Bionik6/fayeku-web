<x-layouts::auth :title="__('Mot de passe oublié')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Mot de passe oublié')"
            :description="__('Saisissez votre adresse email. Nous vous enverrons un lien pour réinitialiser votre mot de passe.')"
        />

        <x-auth-session-status :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-5">
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

            <button type="submit" class="auth-button">
                {{ __('Envoyer le lien') }}
            </button>
        </form>

        <p class="text-center text-sm leading-6 text-slate-600">
            <a href="{{ route('login') }}" wire:navigate class="auth-link">{{ __('Retour à la connexion') }}</a>
        </p>
    </div>
</x-layouts::auth>
