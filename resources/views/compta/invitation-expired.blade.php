<x-layouts::auth :title="__('Invitation expirée')">
    <div class="flex flex-col items-center gap-6 text-center">
        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-amber-100">
            <svg class="h-8 w-8 text-amber-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
        </div>

        <div>
            <h2 class="text-xl font-bold text-slate-900">{{ __('Invitation expirée') }}</h2>
            <p class="mt-2 text-sm text-slate-600">
                {{ __('L\'invitation envoyée par') }}
                <span class="font-semibold">{{ $invitation->accountantFirm?->name }}</span>
                {{ __('a expiré.') }}
            </p>
        </div>

        <div class="flex flex-col gap-3 w-full">
            <a href="{{ route('register') }}" class="auth-button inline-block text-center">
                {{ __('Créer un compte Fayeku') }}
            </a>
            <a href="{{ route('login') }}" class="text-sm text-slate-600 hover:text-slate-900 transition">
                {{ __('Vous avez déjà un compte ? Se connecter') }}
            </a>
        </div>
    </div>
</x-layouts::auth>
