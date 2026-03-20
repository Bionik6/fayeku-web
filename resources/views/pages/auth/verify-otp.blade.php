<x-layouts::auth :title="__('Vérification du téléphone')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Vérification du téléphone')"
            :description="__('Entrez le code à 6 chiffres envoyé au') . ' ' . $maskedPhone"
        />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('auth.otp.verify') }}" class="flex flex-col gap-6">
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

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full">
                    {{ __('Vérifier') }}
                </flux:button>
            </div>
        </form>

        <div class="text-center" x-data="{ countdown: 0, canResend: true }" x-init="
            let last = localStorage.getItem('otp_resend_at');
            if (last) {
                let diff = 60 - Math.floor((Date.now() - parseInt(last)) / 1000);
                if (diff > 0) { countdown = diff; canResend = false; }
            }
            $watch('countdown', (val) => { if (val <= 0) canResend = true; });
            setInterval(() => { if (countdown > 0) countdown--; }, 1000);
        ">
            <form method="POST" action="{{ route('auth.otp.resend') }}" x-show="canResend" @submit="
                canResend = false;
                countdown = 60;
                localStorage.setItem('otp_resend_at', Date.now().toString());
            ">
                @csrf
                <flux:button variant="ghost" type="submit" size="sm">
                    {{ __('Renvoyer le code') }}
                </flux:button>
            </form>

            <p x-show="!canResend" class="text-sm text-zinc-500">
                {{ __('Renvoyer dans') }} <span x-text="countdown"></span>s
            </p>
        </div>
    </div>
</x-layouts::auth>
