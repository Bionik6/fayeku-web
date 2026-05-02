<x-layouts::auth :title="__('Connexion')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Connexion')"
            :description="__('Saisissez votre adresse email et votre mot de passe pour accéder à Fayeku.')"
        />

        <x-auth-session-status :status="session('status')" />

        <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-5">
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
                    placeholder="vous@example.com"
                    class="auth-input"
                />
                <x-auth-field-error name="email" />
            </label>

            <div class="auth-field-stack">
                <div class="flex items-center justify-between gap-1">
                    <span class="auth-field-label">{{ __('Mot de passe') }} *</span>
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
