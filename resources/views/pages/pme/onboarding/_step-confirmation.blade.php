@php
    $firstClient = $firstClientCreated ? $company?->clients()->latest()->first() : null;
    $signaturePreview = trim($senderName);
    if ($signaturePreview && trim($senderRole)) {
        $signaturePreview .= ', '.trim($senderRole);
    }
@endphp

<div class="space-y-6 text-center">
    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-mist">
        <span class="flex h-12 w-12 items-center justify-center rounded-full bg-primary">
            <svg viewBox="0 0 20 20" fill="currentColor" class="h-6 w-6 text-white">
                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
            </svg>
        </span>
    </div>

    <header class="space-y-1">
        <h1 class="text-2xl font-semibold leading-tight text-ink sm:text-[28px]">
            {{ __('onboarding.confirmation.title') }}
        </h1>
        <p class="text-2xl">🎉</p>
        <p class="mx-auto max-w-sm text-sm text-slate-500">{{ __('onboarding.confirmation.subtitle') }}</p>
    </header>

    {{-- Mint card avec récap --}}
    <ul class="space-y-2.5 rounded-2xl bg-mist p-5 text-left">
        <li class="flex items-center gap-3 text-sm text-[#024D4D]">
            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary text-white">
                <svg viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3">
                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                </svg>
            </span>
            <span>{{ __('onboarding.confirmation.recap_company') }} — <strong class="text-ink">{{ $companyName }}</strong></span>
        </li>
        <li class="flex items-center gap-3 text-sm text-[#024D4D]">
            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary text-white">
                <svg viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3">
                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                </svg>
            </span>
            <span>{{ __('onboarding.confirmation.recap_signature') }} — <strong class="text-ink">{{ $signaturePreview ?: ($company?->name ?? '—') }}</strong></span>
        </li>
        @if ($firstClientCreated)
            <li class="flex items-center gap-3 text-sm text-[#024D4D]">
                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary text-white">
                    <svg viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                    </svg>
                </span>
                <span>{{ __('onboarding.confirmation.recap_first_client') }} — <strong class="text-ink">{{ $firstClient?->name ?? '—' }}</strong></span>
            </li>
        @endif
    </ul>

    {{-- Mint card "Prochaine étape" --}}
    <div class="flex items-start gap-3 rounded-2xl bg-mist p-4 text-left">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary text-white">
            <flux:icon name="bolt" class="h-4 w-4" />
        </span>
        <div class="min-w-0">
            <p class="text-sm font-semibold text-[#024D4D]">{{ __('onboarding.confirmation.next_step_title') }}</p>
            <p class="mt-0.5 text-sm leading-snug text-[#1D5D5D]">{{ __('onboarding.confirmation.next_step_body') }}</p>
        </div>
    </div>

    {{-- Boutons : créer facture + dashboard --}}
    <div class="space-y-3">
        <button
            type="button"
            wire:click="completeAndCreateInvoice"
            class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3 text-sm font-semibold text-white shadow-soft transition hover:bg-primary/90"
            data-test="onboarding-create-invoice"
        >
            {{ __('onboarding.confirmation.create_invoice') }}
            <flux:icon name="arrow-right" class="h-4 w-4" />
        </button>

        <button
            type="button"
            wire:click="complete"
            class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-6 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
            data-test="onboarding-complete"
        >
            {{ __('onboarding.confirmation.go_to_dashboard') }}
        </button>
    </div>
</div>
