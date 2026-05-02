@php
    $initialSecondsLeft = $otpExpiresAt ? max(0, $otpExpiresAt - time()) : 0;
@endphp

<x-layouts::auth :title="__('Vérification de l\'email')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Vérification de l\'email')"
            :description="__('Entrez le code à 6 chiffres envoyé à :email', ['email' => '<strong class=\'font-semibold text-ink\'>'.e($maskedEmail).'</strong>'])"
        />

        @if (! app()->environment('production') && config('fayeku.otp_bypass_code'))
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-center text-sm text-amber-800">
                {{ __('Mode développement') }} — {{ __('Code de bypass') }} : <strong>{{ config('fayeku.otp_bypass_code') }}</strong>
            </div>
        @endif

        <x-auth-session-status :status="session('status')" />

        <form method="POST" action="{{ route('auth.verify-email.verify') }}" class="flex flex-col gap-5">
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

        <div
            class="text-center"
            x-data="{
                expiresAt: {{ $otpExpiresAt ? $otpExpiresAt * 1000 : 'null' }},
                secondsLeft: {{ $initialSecondsLeft }},
                tick() {
                    this.secondsLeft = this.expiresAt
                        ? Math.max(0, Math.floor((this.expiresAt - Date.now()) / 1000))
                        : 0;
                },
                get formatted() {
                    let m = Math.floor(this.secondsLeft / 60);
                    let s = this.secondsLeft % 60;
                    return m + ':' + s.toString().padStart(2, '0');
                },
            }"
            x-init="setInterval(() => tick(), 1000);"
        >
            <p x-show="secondsLeft > 0" class="text-sm text-slate-500" @if ($initialSecondsLeft <= 0) style="display: none" @endif>
                {{ __('Code valable encore') }} <span class="font-semibold text-ink" x-text="formatted">{{ sprintf('%d:%02d', intdiv($initialSecondsLeft, 60), $initialSecondsLeft % 60) }}</span>
            </p>

            <form method="POST" action="{{ route('auth.verify-email.resend') }}" x-show="secondsLeft <= 0" @if ($initialSecondsLeft > 0) style="display: none" @endif>
                @csrf
                <button type="submit" class="text-sm auth-link">{{ __('Renvoyer le code') }}</button>
            </form>
        </div>
    </div>
</x-layouts::auth>
