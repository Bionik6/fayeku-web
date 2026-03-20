<x-layouts::auth :title="__('Inscription')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Créer un compte')" :description="__('Remplissez les informations ci-dessous pour créer votre compte')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('auth.register.submit') }}" class="flex flex-col gap-6">
            @csrf

            <flux:select name="country_code" :label="__('Pays')" required>
                <flux:select.option value="SN" :selected="old('country_code') === 'SN'">Sénégal (+221)</flux:select.option>
                <flux:select.option value="CI" :selected="old('country_code') === 'CI'">Côte d'Ivoire (+225)</flux:select.option>
            </flux:select>

            <div class="grid grid-cols-2 gap-4">
                <flux:input
                    name="first_name"
                    :label="__('Prénom')"
                    :value="old('first_name')"
                    type="text"
                    required
                    autofocus
                    autocomplete="given-name"
                    placeholder="Amadou"
                />

                <flux:input
                    name="last_name"
                    :label="__('Nom')"
                    :value="old('last_name')"
                    type="text"
                    required
                    autocomplete="family-name"
                    placeholder="Diallo"
                />
            </div>

            <flux:input
                name="phone"
                :label="__('Téléphone')"
                :value="old('phone')"
                type="tel"
                required
                autocomplete="tel"
                placeholder="77 123 45 67"
            />

            <flux:input
                name="company_name"
                :label="__('Nom de l\'entreprise')"
                :value="old('company_name')"
                type="text"
                required
                placeholder="Ma Société SARL"
            />

            <flux:radio.group name="profile_type" :label="__('Type de profil')" variant="segmented">
                <flux:radio value="sme" :label="__('PME')" :checked="old('profile_type', 'sme') === 'sme'" />
                <flux:radio value="accountant_firm" :label="__('Cabinet Comptable')" :checked="old('profile_type') === 'accountant_firm'" />
            </flux:radio.group>

            <flux:input
                name="password"
                :label="__('Mot de passe')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Mot de passe')"
                viewable
            />

            <flux:input
                name="password_confirmation"
                :label="__('Confirmer le mot de passe')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Confirmer le mot de passe')"
                viewable
            />

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full">
                    {{ __('Créer un compte') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Vous avez déjà un compte ?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Se connecter') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
