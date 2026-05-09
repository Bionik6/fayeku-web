@php
    $previewCompany = trim($company?->name ?? '');
    $previewCompanyDisplay = $previewCompany !== '' ? $previewCompany : __('Votre entreprise');
    $previewSignerName = trim($senderName);
    $previewSignerRole = trim($senderRole);

    // Format selon disponibilité :
    //   {Nom}, {Fonction} · {Organisation}
    //   {Nom} · {Organisation}
    //   {Fonction} · {Organisation}
    //   {Organisation}
    if ($previewSignerName !== '' && $previewSignerRole !== '') {
        $previewLeading = $previewSignerName . ', ' . $previewSignerRole;
    } elseif ($previewSignerName !== '') {
        $previewLeading = $previewSignerName;
    } elseif ($previewSignerRole !== '') {
        $previewLeading = $previewSignerRole;
    } else {
        $previewLeading = '';
    }

    $previewSignatureLine = $previewLeading !== ''
        ? $previewLeading . ' · ' . $previewCompanyDisplay
        : $previewCompanyDisplay;
@endphp

<form wire:submit="saveSignature" class="space-y-6">
    <header class="space-y-2">
        <h1 class="text-2xl font-semibold leading-tight text-ink sm:text-[28px]">{{ __('onboarding.signature.title') }}</h1>
        <p class="text-sm text-slate-500">{{ __('onboarding.signature.subtitle') }}</p>
    </header>

    <div class="space-y-5">
        <label class="auth-label">
            <span>{{ __('onboarding.signature.sender_name') }} <span class="text-rose-500">*</span></span>
            <input wire:model.live.debounce.250ms="senderName" type="text" required maxlength="60" class="auth-input" data-test="signature-sender-name" />
            <p class="flex items-center justify-between text-xs text-slate-500">
                <span>{{ __('onboarding.signature.sender_name_help') }}</span>
                <span class="font-medium tabular-nums">{{ mb_strlen($senderName) }} / 60</span>
            </p>
            @error('senderName') <p class="auth-error">{{ $message }}</p> @enderror
        </label>

        <label class="auth-label">
            <span>
                {{ __('onboarding.signature.sender_role') }}
                <span class="ml-1 text-xs font-normal text-slate-400">{{ __('onboarding.identity.optional') }}</span>
            </span>
            <input wire:model.live.debounce.250ms="senderRole" type="text" placeholder="{{ __('onboarding.signature.sender_role_placeholder') }}" class="auth-input" data-test="signature-sender-role" />
            @error('senderRole') <p class="auth-error">{{ $message }}</p> @enderror
        </label>

        {{-- APERÇU --}}
        <div class="space-y-2">
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-primary">{{ __('onboarding.signature.preview_title') }}</p>

            <div class="rounded-2xl border border-slate-200 bg-[#F4EEDF] p-4 shadow-inner">
                <div class="space-y-3 rounded-xl bg-white p-4 text-sm leading-relaxed text-slate-700 shadow-sm">
                    <p class="text-base font-semibold text-ink">{{ __('onboarding.signature.preview_subject') }}</p>
                    <p>{{ __('onboarding.signature.preview_greeting') }}</p>
                    <p>
                        <strong class="text-ink">{{ $previewCompanyDisplay }}</strong> revient vers vous concernant la facture
                        <strong class="text-ink">FYK-FAC-F9D45L</strong> d'un montant de
                        <strong class="text-ink">600 000 FCFA</strong>, échue le
                        <strong class="text-ink">21 Avril 2026</strong>.
                    </p>
                    <p>Nous vous invitons à régulariser la situation dès que possible.</p>
                    <div>
                        <p>Cordialement,</p>
                        <p class="font-medium text-ink">{{ $previewSignatureLine }}</p>
                    </div>
                    <p class="text-right text-[11px] text-slate-400">17:13</p>
                </div>
            </div>
        </div>

        {{-- Banner ton --}}
        <div class="flex items-start gap-3 rounded-xl bg-mist p-4">
            <flux:icon name="information-circle" class="mt-0.5 h-5 w-5 shrink-0 text-primary" />
            <p class="text-sm leading-snug text-[#024D4D]">
                {{ __('onboarding.signature.tone_info') }}
            </p>
        </div>
    </div>

    <div class="flex items-center justify-between border-t border-slate-100 pt-5">
        <button type="button" wire:click="previousStep" class="inline-flex cursor-pointer items-center gap-1 text-sm font-medium text-slate-600 transition hover:text-primary">
            <flux:icon name="arrow-left" class="h-4 w-4" />
            {{ __('onboarding.wizard.back') }}
        </button>

        <button type="submit" class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-semibold text-white shadow-soft transition hover:bg-primary/90" data-test="step-signature-next">
            {{ __('onboarding.wizard.next') }}
            <flux:icon name="arrow-right" class="h-4 w-4" />
        </button>
    </div>
</form>
