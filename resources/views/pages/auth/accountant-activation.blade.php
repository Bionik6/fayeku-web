<x-layouts::auth :title="__('Activer votre cabinet')">
    <div class="flex flex-col gap-6">
        <div class="rounded-xl border border-teal-200 bg-teal-50 p-4">
            <p class="text-sm font-semibold text-teal-800">
                {{ __('Bienvenue') }} {{ $lead->first_name }} !
            </p>
            <p class="mt-1 text-sm text-teal-700">
                {{ __('Le cabinet') }} <strong>{{ $lead->firm }}</strong>
                {{ __('est activé. Définissez votre mot de passe pour accéder à votre espace.') }}
            </p>
        </div>

        <x-auth-header
            :title="__('Activer votre accès')"
            :description="__('Choisissez un mot de passe sécurisé pour votre compte cabinet.')"
        />

        <x-auth-session-status :status="session('status')" />

        <form method="POST" action="{{ route('accountant.activation.process', $token) }}" class="flex flex-col gap-5">
            @csrf

            <label class="auth-label">
                <span>{{ __('Email') }}</span>
                <input type="email" value="{{ $lead->email }}" disabled class="auth-input opacity-60" />
            </label>

            <label class="auth-label">
                <span>{{ __('Mot de passe') }} *</span>
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

            <label class="auth-checkbox-row items-start">
                <span class="auth-checkbox-wrap mt-1">
                    <input
                        name="cgu_accepted"
                        type="checkbox"
                        value="1"
                        required
                        @checked(old('cgu_accepted'))
                        class="auth-checkbox"
                    />
                    <svg viewBox="0 0 14 14" fill="none" class="auth-checkbox-icon" aria-hidden="true">
                        <path d="M3 8L6 11L11 3.5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </span>
                <span class="text-sm leading-6">
                    {{ __("J'accepte les") }}
                    <a href="{{ route('marketing.legal') }}" class="auth-link" target="_blank" rel="noopener">{{ __('mentions légales') }}</a>
                    {{ __('et la') }}
                    <a href="{{ route('marketing.privacy') }}" class="auth-link" target="_blank" rel="noopener">{{ __('politique de confidentialité') }}</a>
                    {{ __('de Fayeku.') }}
                </span>
            </label>
            <x-auth-field-error name="cgu_accepted" />

            <button type="submit" class="auth-button">
                {{ __('Activer mon compte') }}
            </button>
        </form>
    </div>
</x-layouts::auth>
