<x-layouts::auth :title="__('Connexion')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Se connecter')" :description="__('Entrez votre numéro de téléphone et votre mot de passe')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('auth.login.submit') }}" class="flex flex-col gap-6">
            @csrf

            <flux:select name="country_code" :label="__('Pays')" required>
                <flux:select.option value="SN" :selected="old('country_code') === 'SN'">Sénégal (+221)</flux:select.option>
                <flux:select.option value="CI" :selected="old('country_code') === 'CI'">Côte d'Ivoire (+225)</flux:select.option>
            </flux:select>

            <flux:input
                name="phone"
                :label="__('Téléphone')"
                :value="old('phone')"
                type="tel"
                required
                autofocus
                autocomplete="tel"
                placeholder="77 123 45 67"
            />

            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Mot de passe')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Mot de passe')"
                    viewable
                />

                <flux:link class="absolute top-0 text-sm end-0" :href="route('auth.forgot-password')" wire:navigate>
                    {{ __('Mot de passe oublié ?') }}
                </flux:link>
            </div>

            <flux:checkbox name="remember" :label="__('Se souvenir de moi')" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full">
                    {{ __('Se connecter') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Pas encore de compte ?') }}</span>
            <flux:link :href="route('auth.register')" wire:navigate>{{ __('Créer un compte') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
