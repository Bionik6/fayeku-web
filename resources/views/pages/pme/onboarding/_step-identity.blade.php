@php
    $sectors = [
        'Agriculture / Élevage',
        'Agroalimentaire & Transformation',
        'Artisanat & Arts',
        'Bâtiment / Construction',
        'Commerce (boutique, magasin, supermarché)',
        'Commerce de gros',
        'Éducation (école, formation)',
        'Énergie, Mines & Pétrole',
        'Environnement & Eau',
        'Finance, Banque & Assurance',
        'Hôtellerie',
        'Immobilier & Foncier',
        'Industrie manufacturière',
        'Médias & Communication',
        'Pêche',
        'Restauration (restaurant, fast-food, traiteur)',
        'Salon de coiffure / Esthétique',
        'Santé (clinique, pharmacie)',
        'Services automobiles',
        'Services aux entreprises & Conseil',
        'Technologie / Informatique',
        'Télécommunications',
        'Textile, Habillement & Cuir',
        'Tourisme & Loisirs',
        'Transport / Logistique',
    ];
    $legalForms = \App\Enums\Auth\LegalForm::options();
@endphp

<form wire:submit="saveIdentity" class="space-y-7">
    <header class="space-y-2">
        <h1 class="text-2xl font-semibold leading-tight text-ink sm:text-[28px]">{{ __('onboarding.identity.title') }}</h1>
        <p class="text-sm text-slate-500">{{ __('onboarding.identity.subtitle') }}</p>
    </header>

    <div class="space-y-5">
        {{-- Raison sociale --}}
        <label class="auth-label">
            <span>{{ __('onboarding.identity.company_name') }} <span class="text-rose-500">*</span></span>
            <input wire:model="companyName" type="text" required autofocus placeholder="{{ __('onboarding.identity.company_name_placeholder') }}" class="auth-input" data-test="identity-company-name" />
            @error('companyName') <p class="auth-error">{{ $message }}</p> @enderror
        </label>

        {{-- Forme juridique + Secteur --}}
        <div class="grid gap-5 sm:grid-cols-2">
            <label class="auth-label">
                <span>{{ __('onboarding.identity.legal_form') }} <span class="text-rose-500">*</span></span>
                <x-select-native>
                    <select wire:model="legalForm" class="auth-select" required data-test="identity-legal-form">
                        <option value="">{{ __('onboarding.identity.legal_form_placeholder') }}</option>
                        @foreach ($legalForms as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </x-select-native>
                @error('legalForm') <p class="auth-error">{{ $message }}</p> @enderror
            </label>

            <div
                class="auth-label"
                x-data="{
                    choice: @js($sector),
                    customSector: @js($sectorOther),
                    isOther() { return this.choice === '__other__'; },
                }"
                x-init="$watch('choice', v => $wire.set('sector', v === '__other__' ? customSector : v));
                        $watch('customSector', v => { if (isOther()) $wire.set('sector', v); })"
            >
                <span>{{ __('onboarding.identity.sector') }} <span class="text-rose-500">*</span></span>
                <x-select-native>
                    <select x-model="choice" class="auth-select" required data-test="identity-sector">
                        <option value="">{{ __('onboarding.identity.sector_placeholder') }}</option>
                        @foreach ($sectors as $sectorOption)
                            <option value="{{ $sectorOption }}">{{ $sectorOption }}</option>
                        @endforeach
                        <option value="__other__">{{ __('onboarding.identity.sector_other') }}</option>
                    </select>
                </x-select-native>
                <input
                    x-show="isOther()"
                    x-model="customSector"
                    x-cloak
                    type="text"
                    placeholder="{{ __('onboarding.identity.sector_other_placeholder') }}"
                    class="auth-input mt-2"
                />
                @error('sector') <p class="auth-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <hr class="border-slate-100" />

        {{-- NINEA + RCCM --}}
        <div class="grid gap-5 sm:grid-cols-2">
            <label class="auth-label">
                <span>
                    {{ __('onboarding.identity.ninea') }}
                    <span class="ml-1 text-xs font-normal text-slate-400">{{ __('onboarding.identity.optional') }}</span>
                </span>
                <input wire:model="ninea" type="text" class="auth-input" placeholder="0051234562A2" data-test="identity-ninea" />
                @error('ninea') <p class="auth-error">{{ $message }}</p> @enderror
            </label>

            <label class="auth-label">
                <span>
                    {{ __('onboarding.identity.rccm') }}
                    <span class="ml-1 text-xs font-normal text-slate-400">{{ __('onboarding.identity.optional') }}</span>
                </span>
                <input wire:model="rccm" type="text" class="auth-input" placeholder="SN-DKR-2024-B-1234" data-test="identity-rccm" />
                @error('rccm') <p class="auth-error">{{ $message }}</p> @enderror
            </label>
        </div>

        {{-- Adresse --}}
        <label class="auth-label">
            <span>
                {{ __('onboarding.identity.address') }}
                <span class="ml-1 text-xs font-normal text-slate-400">{{ __('onboarding.identity.optional') }}</span>
            </span>
            <input wire:model="address" type="text" placeholder="{{ __('onboarding.identity.address_placeholder') }}" class="auth-input" data-test="identity-address" />
            @error('address') <p class="auth-error">{{ $message }}</p> @enderror
        </label>

        {{-- Email facturation --}}
        <label class="auth-label">
            <span>
                {{ __('onboarding.identity.billing_email') }}
                <span class="ml-1 text-xs font-normal text-slate-400">{{ __('onboarding.identity.optional') }}</span>
            </span>
            <input wire:model="billingEmail" type="email" class="auth-input" placeholder="contact@entreprise.sn" data-test="identity-billing-email" />
            @error('billingEmail')
                <p class="auth-error">{{ $message }}</p>
            @else
                <p class="text-xs text-slate-500">{{ __('onboarding.identity.billing_email_help') }}</p>
            @enderror
        </label>

        {{-- Logo --}}
        <div class="space-y-2">
            <p class="text-sm font-medium text-slate-700">
                {{ __('onboarding.identity.logo') }}
                <span class="ml-1 text-xs font-normal text-slate-400">{{ __('onboarding.identity.optional') }}</span>
            </p>

            <div
                x-data="{ dragging: false }"
                @dragover.prevent="dragging = true"
                @dragleave.prevent="dragging = false"
                @drop.prevent="dragging = false"
                class="flex items-center gap-4 rounded-xl border-2 border-dashed bg-slate-50/40 p-4 transition"
                :class="dragging ? 'border-primary bg-mist' : 'border-slate-300'"
            >
                @if ($this->existingLogoUrl)
                    <img src="{{ $this->existingLogoUrl }}" alt="Logo actuel" class="h-12 w-12 shrink-0 rounded-lg border border-slate-200 bg-white object-contain p-1" />
                @else
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-mist text-primary">
                        <flux:icon name="photo" class="h-5 w-5" />
                    </div>
                @endif

                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-ink">
                        {{ __('onboarding.identity.logo_dropzone_title') }}
                    </p>
                    <p class="text-xs text-slate-500">{{ __('onboarding.identity.logo_dropzone_help') }}</p>

                    @if ($logoUpload)
                        <p class="mt-1 truncate text-xs font-medium text-primary">
                            <flux:icon name="paper-clip" class="-mt-0.5 inline h-3 w-3" />
                            {{ $logoUpload->getClientOriginalName() }}
                        </p>
                    @endif
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <label class="cursor-pointer rounded-lg border border-primary bg-white px-4 py-2 text-sm font-semibold text-primary transition hover:bg-mist" data-test="identity-logo-trigger">
                        {{ __('onboarding.identity.logo_browse') }}
                        <input wire:model.live="logoUpload" type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" data-test="identity-logo" />
                    </label>
                    @if ($this->existingLogoUrl)
                        <button type="button" wire:click="removeLogo" class="cursor-pointer rounded-lg border border-rose-200 bg-white px-3 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50">
                            {{ __('onboarding.identity.logo_remove') }}
                        </button>
                    @endif
                </div>
            </div>
            @error('logoUpload') <p class="auth-error">{{ $message }}</p> @enderror
        </div>
    </div>

    {{-- Footer actions --}}
    <div class="flex items-center justify-between border-t border-slate-100 pt-5">
        <button type="button" wire:click="previousStep" class="inline-flex cursor-pointer items-center gap-1 text-sm font-medium text-slate-600 transition hover:text-primary">
            <flux:icon name="arrow-left" class="h-4 w-4" />
            {{ __('onboarding.wizard.back') }}
        </button>

        <button type="submit" class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-semibold text-white shadow-soft transition hover:bg-primary/90" data-test="step-identity-next">
            {{ __('onboarding.wizard.next') }}
            <flux:icon name="arrow-right" class="h-4 w-4" />
        </button>
    </div>
</form>
