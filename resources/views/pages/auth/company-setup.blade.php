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

$oldSector = old('sector', '');
$isOther = $oldSector !== '' && ! in_array($oldSector, $sectors);
@endphp

<x-layouts::auth :title="__('Votre entreprise')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Votre entreprise')"
            :description="__('Renseignez les informations de votre entreprise pour finaliser votre inscription.')"
        />

        <x-auth-session-status :status="session('status')" />

        <form method="POST" action="{{ route('auth.company-setup.submit') }}" class="flex flex-col gap-5">
            @csrf

            <label class="auth-label">
                <span>{{ __('Nom de l\'entreprise') }} *</span>
                <input
                    name="company_name"
                    type="text"
                    value="{{ old('company_name', $prefillCompanyName ?? '') }}"
                    required
                    autofocus
                    placeholder="{{ __('Nom commercial ou raison sociale') }}"
                    class="auth-input"
                />
                <x-auth-field-error name="company_name" />
            </label>

            <div
                class="auth-label"
                x-data="{
                    choice: @js($isOther ? '__other__' : $oldSector),
                    customSector: @js($isOther ? $oldSector : ''),
                }"
            >
                <span>{{ __('Secteur d\'activité') }} *</span>

                <input
                    type="hidden"
                    name="sector"
                    :value="choice === '__other__' ? customSector : choice"
                />

                <x-select-native>
                    <select
                        x-model="choice"
                        class="auth-select"
                        required
                    >
                        <option value="" disabled>{{ __('Sélectionnez un secteur...') }}</option>
                        @foreach ($sectors as $sector)
                            <option value="{{ $sector }}">{{ $sector }}</option>
                        @endforeach
                        <option value="__other__">{{ __('Autre') }}</option>
                    </select>
                </x-select-native>

                <input
                    x-show="choice === '__other__'"
                    x-model="customSector"
                    x-cloak
                    type="text"
                    placeholder="{{ __('Précisez votre secteur d\'activité...') }}"
                    class="auth-input mt-2"
                />

                <x-auth-field-error name="sector" />
            </div>

            <label class="auth-label">
                <span>{{ __('NINEA') }} <span class="text-sm font-normal text-slate-500">({{ __('facultatif') }})</span></span>
                <input
                    name="ninea"
                    type="text"
                    value="{{ old('ninea') }}"
                    placeholder="{{ __('Numéro d\'Identification National des Entreprises') }}"
                    class="auth-input"
                />
                <x-auth-field-error name="ninea" />
            </label>

            <label class="auth-label">
                <span>{{ __('RCCM') }} <span class="text-sm font-normal text-slate-500">({{ __('facultatif') }})</span></span>
                <input
                    name="rccm"
                    type="text"
                    value="{{ old('rccm') }}"
                    placeholder="{{ __('Registre du Commerce et du Crédit Mobilier') }}"
                    class="auth-input"
                />
                <x-auth-field-error name="rccm" />
            </label>

            <div x-data="{ open: false }" class="-mt-1">
                <button
                    type="button"
                    @click="open = ! open"
                    :aria-expanded="open"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline"
                >
                    <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-7-4a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM9 9a.75.75 0 0 0 0 1.5h.253a.25.25 0 0 1 .244.304l-.459 2.066A1.75 1.75 0 0 0 10.747 15H11a.75.75 0 0 0 0-1.5h-.253a.25.25 0 0 1-.244-.304l.459-2.066A1.75 1.75 0 0 0 9.253 9H9Z" clip-rule="evenodd" />
                    </svg>
                    {{ __('Pourquoi ces informations ?') }}
                </button>

                <div
                    x-show="open"
                    x-transition.opacity.duration.150ms
                    style="display: none"
                    class="mt-3 rounded-xl border border-primary/15 bg-primary/5 p-4 text-sm leading-6 text-slate-700"
                >
                    {{ __('Fayeku fonctionne aussi sans NINEA ni RCCM. Ces informations permettent simplement à votre comptable de générer des factures conformes à la réglementation sénégalaise quand c\'est nécessaire.') }}
                </div>
            </div>

            <button type="submit" class="auth-button">
                {{ __('Finaliser mon inscription') }}
            </button>
        </form>
    </div>
</x-layouts::auth>
