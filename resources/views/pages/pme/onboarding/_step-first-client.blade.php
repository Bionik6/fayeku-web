<form wire:submit="saveFirstClient" class="space-y-6">
    <header class="space-y-3">
        <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
            {{ __('onboarding.first_client.badge') }}
        </span>
        <h1 class="text-2xl font-semibold leading-tight text-ink sm:text-[28px]">{{ __('onboarding.first_client.title') }}</h1>
        <p class="text-sm text-slate-500">{{ __('onboarding.first_client.subtitle') }}</p>
    </header>

    <div class="space-y-5">
        <label class="auth-label">
            <span>{{ __('onboarding.first_client.name') }} <span class="text-rose-500">*</span></span>
            <input wire:model="clientName" type="text" required autofocus maxlength="255" placeholder="{{ __('onboarding.first_client.name_placeholder') }}" class="auth-input" data-test="first-client-name" />
            @error('clientName') <p class="auth-error">{{ $message }}</p> @enderror
        </label>

        <div>
            <span class="mb-1.5 block text-sm font-medium text-slate-700">
                {{ __('onboarding.first_client.phone') }} <span class="text-rose-500">*</span>
            </span>
            <x-phone-input
                :showLabel="false"
                phoneName="clientPhone"
                countryName="clientPhoneCountry"
                :phoneValue="$clientPhone"
                :countryValue="$clientPhoneCountry"
                phoneModel="clientPhone"
                countryModel="clientPhoneCountry"
                :countries="array_keys(config('fayeku.phone_countries'))"
                :required="true"
            />
            @error('clientPhone')
                <p class="auth-error">{{ $message }}</p>
            @else
                <p class="mt-1 text-xs text-slate-500">{{ __('onboarding.first_client.phone_help') }}</p>
            @enderror
        </div>

        <label class="auth-label">
            <span>
                {{ __('onboarding.first_client.email') }}
                <span class="ml-1 text-xs font-normal text-slate-400">{{ __('onboarding.identity.optional') }}</span>
            </span>
            <input wire:model="clientEmail" type="email" maxlength="255" placeholder="{{ __('onboarding.first_client.email_placeholder') }}" class="auth-input" data-test="first-client-email" />
            @error('clientEmail') <p class="auth-error">{{ $message }}</p> @enderror
        </label>
    </div>

    <div class="flex items-center justify-between border-t border-slate-100 pt-5">
        <button type="button" wire:click="previousStep" class="inline-flex cursor-pointer items-center gap-1 text-sm font-medium text-slate-600 transition hover:text-primary">
            <flux:icon name="arrow-left" class="h-4 w-4" />
            {{ __('onboarding.wizard.back') }}
        </button>

        <div class="flex items-center gap-3">
            <button type="button" wire:click="skipFirstClient" class="inline-flex cursor-pointer items-center rounded-xl border border-primary bg-white px-5 py-3 text-sm font-semibold text-primary transition hover:bg-mist" data-test="step-first-client-skip">
                {{ __('onboarding.wizard.skip') }}
            </button>
            <button type="submit" class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-semibold text-white shadow-soft transition hover:bg-primary/90" data-test="step-first-client-next">
                {{ __('onboarding.first_client.add_and_continue') }}
                <flux:icon name="arrow-right" class="h-4 w-4" />
            </button>
        </div>
    </div>
</form>
