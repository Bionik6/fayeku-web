<x-layouts::auth :title="__('Vérification du téléphone')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Vérification du téléphone')"
            :description="__('Entrez le code à 6 chiffres envoyé au') . ' ' . $maskedPhone"
        />

        @if (! app()->environment('production') && config('fayeku.otp_bypass_code'))
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-center text-sm text-amber-800">
                {{ __('Mode développement') }} — {{ __('Code de bypass') }} : <strong>{{ config('fayeku.otp_bypass_code') }}</strong>
            </div>
        @endif

        <x-auth-session-status :status="session('status')" />

        <form method="POST" action="{{ route('auth.otp.verify') }}" class="flex flex-col gap-5">
            @csrf

            <label class="auth-label">
                <span>{{ __('Code de vérification') }} *</span>
                <input
                    name="code"
                    type="text"
                    inputmode="numeric"
                    pattern="[0-9]{6}"
                    maxlength="6"
                    required
                    autofocus
                    autocomplete="one-time-code"
                    placeholder="000000"
                    class="auth-input text-center text-lg tracking-[0.35em]"
                />
                <x-auth-field-error name="code" />
            </label>

            <button type="submit" class="auth-button">
                {{ __('Vérifier') }}
            </button>
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
                <button type="submit" class="text-sm auth-link">{{ __('Renvoyer le code') }}</button>
            </form>

            <p x-show="!canResend" class="text-sm text-slate-500">
                {{ __('Renvoyer dans') }} <span x-text="countdown"></span>s
            </p>
        </div>
    </div>
</x-layouts::auth>
