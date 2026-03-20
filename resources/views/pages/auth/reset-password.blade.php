<x-layouts::auth :title="__('Réinitialiser le mot de passe')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Réinitialiser le mot de passe')"
            :description="__('Entrez le code reçu par SMS et votre nouveau mot de passe')"
        />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('auth.reset-password.submit') }}" class="flex flex-col gap-6">
            @csrf

            <flux:input
                name="code"
                :label="__('Code de vérification')"
                type="text"
                inputmode="numeric"
                pattern="[0-9]{6}"
                maxlength="6"
                required
                autofocus
                autocomplete="one-time-code"
                placeholder="000000"
                class="text-center tracking-widest text-lg"
            />

            <flux:input
                name="password"
                :label="__('Nouveau mot de passe')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Nouveau mot de passe')"
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
                <flux:button variant="primary" type="submit" class="w-full">
                    {{ __('Réinitialiser le mot de passe') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <flux:link :href="route('login')" wire:navigate>{{ __('Retour à la connexion') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
