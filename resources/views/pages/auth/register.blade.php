<x-layouts::auth :title="__('Inscription')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Créer un compte')" :description="__('Remplissez les informations ci-dessous pour créer votre compte')" />

        <x-auth-session-status :status="session('status')" />

        <form method="POST" action="{{ route('auth.register.submit') }}" class="flex flex-col gap-5">
            @csrf

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="auth-label">
                    <span>{{ __('Prénom') }} *</span>
                    <input
                        name="first_name"
                        type="text"
                        value="{{ old('first_name') }}"
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
                        value="{{ old('last_name') }}"
                        required
                        autocomplete="family-name"
                        placeholder="{{ __('Entrez votre nom') }}"
                        class="auth-input"
                    />
                    <x-auth-field-error name="last_name" />
                </label>
            </div>

            <x-phone-input
                :label="__('Téléphone')"
                country-name="country_code"
                :country-value="old('country_code', 'SN')"
                phone-name="phone"
                :phone-value="old('phone')"
                :required="true"
                phone-placeholder="XX XXX XX XX"
            />
            <div class="-mt-0.5 space-y-1">
                <x-auth-field-error name="country_code" />
                <x-auth-field-error name="phone" />
            </div>

            <label class="auth-label">
                <span>{{ __('Nom de l\'entreprise ou du cabinet') }} *</span>
                <input
                    name="company_name"
                    type="text"
                    value="{{ old('company_name') }}"
                    required
                    placeholder="{{ __('Nom commercial ou raison sociale') }}"
                    class="auth-input"
                />
                <x-auth-field-error name="company_name" />
            </label>

            <label class="auth-label">
                <span>{{ __('Type de profil') }} *</span>
                <x-select-native>
                    <select name="profile_type" class="auth-select" required>
                        <option value="sme" @selected(old('profile_type', 'sme') === 'sme')>{{ __('PME') }}</option>
                        <option value="accountant_firm" @selected(old('profile_type') === 'accountant_firm')>{{ __('Cabinet d\'expertise comptable') }}</option>
                    </select>
                </x-select-native>
                <x-auth-field-error name="profile_type" />
            </label>

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
