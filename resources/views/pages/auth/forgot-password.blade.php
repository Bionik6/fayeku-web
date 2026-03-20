<x-layouts::auth :title="__('Mot de passe oublié')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Mot de passe oublié')"
            :description="__('Entrez votre numéro de téléphone pour recevoir un code de réinitialisation')"
        />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('auth.forgot-password.submit') }}" class="flex flex-col gap-6">
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

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full">
                    {{ __('Envoyer le code') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <flux:link :href="route('login')" wire:navigate>{{ __('Retour à la connexion') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
