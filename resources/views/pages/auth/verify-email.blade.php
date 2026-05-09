@php
    $initialSecondsLeft = $otpExpiresAt ? max(0, $otpExpiresAt - time()) : 0;
@endphp

<x-layouts::auth :title="__('Vérification de l\'email')">

    {{-- ─── Section gauche ─── --}}
    <x-slot:aside>
        <div class="space-y-4">
            <span class="inline-flex items-center gap-2 rounded-full border border-[#024D4D]/10 bg-white/70 px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-[0.18em] text-teal">
                <span class="h-1.5 w-1.5 rounded-full bg-accent"></span>
                {{ __('Vérification') }}
            </span>

            <h1 class="text-balance text-3xl font-semibold leading-[1.05] text-[#024D4D] sm:text-[34px] lg:text-[40px] lg:leading-[48px]">
                {{ __('Plus qu\'une étape avant de commencer.') }}
            </h1>

            <p class="text-base leading-7 text-[#1D5D5D]">
                {{ __('Nous avons envoyé un code à 6 chiffres à votre email. Cette vérification protège votre espace.') }}
            </p>
        </div>

        {{-- Carte MISE EN ROUTE --}}
        <div class="rounded-2xl border border-[#024D4D]/10 bg-white/85 p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#024D4D]/70">{{ __('Mise en route') }}</p>
                <p class="text-xs font-semibold tabular-nums text-[#024D4D]/60">{{ __('Étape 2 sur 3') }}</p>
            </div>
            <div class="mt-3 h-1 w-full overflow-hidden rounded-full bg-[#024D4D]/10">
                <div class="h-full w-2/3 rounded-full bg-primary"></div>
            </div>

            {{-- Liste verticale des steps (style "circles with text") --}}
            <nav aria-label="Progress" class="mt-5">
                <ol role="list" class="overflow-hidden">
                    <li class="relative pb-6">
                        <div aria-hidden="true" class="absolute left-4 top-4 -ml-px mt-0.5 h-full w-0.5 bg-primary"></div>
                        <div class="relative flex items-start">
                            <span class="flex h-8 items-center">
                                <span class="relative z-10 flex size-8 items-center justify-center rounded-full bg-primary">
                                    <svg viewBox="0 0 20 20" fill="currentColor" class="size-4 text-white">
                                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            </span>
                            <span class="ml-4 flex min-w-0 flex-1 items-center">
                                <span class="text-sm font-medium text-[#024D4D]">{{ __('Compte créé') }}</span>
                            </span>
                        </div>
                    </li>

                    <li class="relative pb-6">
                        <div aria-hidden="true" class="absolute left-4 top-4 -ml-px mt-0.5 h-full w-0.5 bg-[#024D4D]/15"></div>
                        <div aria-current="step" class="relative flex items-start">
                            <span class="flex h-8 items-center">
                                <span class="relative z-10 flex size-8 items-center justify-center rounded-full border-2 border-primary bg-white">
                                    <span class="size-2.5 rounded-full bg-primary"></span>
                                </span>
                            </span>
                            <span class="ml-4 flex min-w-0 flex-1 items-center justify-between gap-2">
                                <span class="text-sm font-medium text-primary">{{ __('Vérifier votre email') }}</span>
                                <span class="inline-flex shrink-0 items-center rounded-full bg-green-50 px-1.5 py-0.5 text-[13px] font-medium text-green-700 inset-ring inset-ring-green-600/20">{{ __('En cours') }}</span>
                            </span>
                        </div>
                    </li>

                    <li class="relative">
                        <div class="relative flex items-start">
                            <span class="flex h-8 items-center">
                                <span class="relative z-10 flex size-8 items-center justify-center rounded-full border-2 border-[#024D4D]/20 bg-white">
                                    <span class="text-[11px] font-medium text-[#024D4D]/45">3</span>
                                </span>
                            </span>
                            <span class="ml-4 flex min-w-0 flex-1 items-center">
                                <span class="text-sm font-medium text-[#024D4D]/55">{{ __('Configurer votre espace') }}</span>
                            </span>
                        </div>
                    </li>
                </ol>
            </nav>
        </div>

        {{-- Carte d'astuce --}}
        <div class="flex items-start gap-3 rounded-2xl bg-white/80 p-4 shadow-sm">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-mist text-primary">
                <flux:icon name="shield-check" class="h-5 w-5" />
            </span>
            <p class="text-sm leading-snug text-[#1D5D5D]">
                <strong class="font-semibold text-[#024D4D]">{{ __('Pourquoi vérifier ?') }}</strong>
                {{ __('Pour s\'assurer que vous recevez bien vos notifications et factures.') }}
            </p>
        </div>

        {{-- Footer --}}
        <div class="mt-auto flex items-center justify-between border-t border-[#024D4D]/10 pt-4 text-xs text-[#1D5D5D]">
            <form method="POST" action="{{ route('auth.logout') }}" class="inline">
                @csrf
                <button type="submit" class="cursor-pointer font-medium text-[#024D4D] hover:underline">
                    ← {{ __('Modifier mon email') }}
                </button>
            </form>
            <a href="{{ route('marketing.contact') }}" class="font-medium text-[#024D4D] hover:underline">
                {{ __('Aide') }}
            </a>
        </div>
    </x-slot:aside>

    {{-- ─── Section droite ─── --}}

    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full border-2 border-primary/15 bg-mist">
        <flux:icon name="envelope" class="h-7 w-7 text-primary" />
    </div>

    <header class="space-y-1.5 text-center">
        <h2 class="text-2xl font-semibold leading-tight text-ink sm:text-[28px]">{{ __('Vérifiez votre email') }}</h2>
        <p class="text-sm text-slate-500">
            {{ __('Entrez le code à 6 chiffres envoyé à') }}<br />
            <strong class="font-semibold text-ink">{{ $maskedEmail }}</strong>
        </p>
    </header>

    @if (config('fayeku.demo') && config('fayeku.otp_bypass_code'))
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-center text-sm text-amber-800">
            {{ __('Mode démo') }} — {{ __('Code de bypass') }} : <strong>{{ config('fayeku.otp_bypass_code') }}</strong>
        </div>
    @endif

    <x-auth-session-status :status="session('status')" />

    <form
        method="POST"
        action="{{ route('auth.verify-email.verify') }}"
        x-data="otpInput()"
        x-init="init()"
        class="flex flex-col gap-5"
    >
        @csrf
        <input type="hidden" name="code" x-model="code" />

        <div class="flex items-center justify-center gap-2 sm:gap-3" data-test="otp-boxes">
            <template x-for="(_, i) in 6" :key="i">
                <input
                    type="text"
                    inputmode="numeric"
                    pattern="[0-9]"
                    maxlength="1"
                    autocomplete="one-time-code"
                    :value="digits[i]"
                    @input="onInput($event, i)"
                    @keydown="onKeydown($event, i)"
                    @paste="onPaste($event)"
                    @focus="$el.select()"
                    x-ref="box"
                    :class="(digits[i] || focused === i) ? 'border-primary bg-white text-ink' : 'border-slate-200 bg-white text-slate-400'"
                    class="h-14 w-12 rounded-xl border-2 text-center text-xl font-semibold tracking-widest transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/15 sm:h-16 sm:w-14 sm:text-2xl"
                />
            </template>
        </div>

        @error('code')
            <p class="text-center text-sm font-medium text-rose-600">{{ $message }}</p>
        @enderror

        <div
            class="flex items-center justify-center gap-2 text-sm text-slate-500"
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
            x-show="secondsLeft > 0"
            @if ($initialSecondsLeft <= 0) style="display: none" @endif
        >
            <flux:icon name="clock" class="h-4 w-4 text-slate-400" />
            <p>
                {{ __('Code valable encore') }} <span class="font-semibold text-ink" x-text="formatted">{{ sprintf('%d:%02d', intdiv($initialSecondsLeft, 60), $initialSecondsLeft % 60) }}</span>
            </p>
        </div>

        <button
            type="submit"
            class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-primary px-5 py-4 text-base font-semibold text-white shadow-soft transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="code.length < 6"
            data-test="otp-submit"
        >
            {{ __('Vérifier') }}
            <flux:icon name="arrow-right" class="h-4 w-4" />
        </button>
    </form>

    <div class="text-center">
        <form
            method="POST"
            action="{{ route('auth.verify-email.resend') }}"
            x-data="{
                expiresAt: {{ $otpExpiresAt ? $otpExpiresAt * 1000 : 'null' }},
                canResend: {{ $initialSecondsLeft > 0 ? 'false' : 'true' }},
                tick() {
                    if (this.expiresAt) {
                        this.canResend = Date.now() >= this.expiresAt;
                    }
                },
            }"
            x-init="setInterval(() => tick(), 1000);"
        >
            @csrf
            <p class="text-sm text-slate-500">
                {{ __('Vous n\'avez pas reçu le code ?') }}
                <button
                    type="submit"
                    class="cursor-pointer font-semibold text-primary hover:underline disabled:cursor-not-allowed disabled:text-slate-400 disabled:no-underline"
                    :disabled="!canResend"
                >
                    {{ __('Renvoyer') }}
                </button>
            </p>
        </form>
    </div>
</x-layouts::auth>

<script>
    window.otpInput = function () {
        return {
            digits: ['', '', '', '', '', ''],
            focused: 0,
            get code() {
                return this.digits.join('');
            },
            init() {
                this.$nextTick(() => this.$refs.box && this.$refs.box.focus?.());
            },
            onInput(event, index) {
                const value = event.target.value.replace(/\D/g, '').slice(0, 1);
                this.digits[index] = value;
                event.target.value = value;
                if (value && index < 5) {
                    const inputs = event.target.parentElement.querySelectorAll('input');
                    inputs[index + 1]?.focus();
                }
            },
            onKeydown(event, index) {
                if (event.key === 'Backspace' && !this.digits[index] && index > 0) {
                    const inputs = event.target.parentElement.querySelectorAll('input');
                    inputs[index - 1]?.focus();
                }
                if (event.key === 'ArrowLeft' && index > 0) {
                    event.target.parentElement.querySelectorAll('input')[index - 1]?.focus();
                }
                if (event.key === 'ArrowRight' && index < 5) {
                    event.target.parentElement.querySelectorAll('input')[index + 1]?.focus();
                }
            },
            onPaste(event) {
                event.preventDefault();
                const pasted = (event.clipboardData?.getData('text') || '').replace(/\D/g, '').slice(0, 6);
                if (!pasted) return;
                for (let i = 0; i < 6; i++) {
                    this.digits[i] = pasted[i] || '';
                }
                const inputs = event.target.parentElement.querySelectorAll('input');
                inputs.forEach((el, i) => { el.value = this.digits[i] || ''; });
                inputs[Math.min(pasted.length, 5)]?.focus();
            },
        };
    };
</script>
