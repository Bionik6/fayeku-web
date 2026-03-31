@php
$sectors = [
    'Agriculture / Maraîchage',
    'Élevage / Aviculture',
    'Pêche / Aquaculture',
    'Sylviculture / Exploitation forestière',
    'Agroalimentaire / Transformation alimentaire',
    'Commerce général / Négoce',
    'Commerce de détail',
    'Grande distribution / Supermarchés',
    'Import / Export',
    'Artisanat / Métiers d\'art',
    'Textile / Confection / Mode',
    'Industrie manufacturière',
    'Mines / Extraction / Carrières',
    'Énergie / Électricité / Pétrole / Gaz',
    'BTP / Bâtiment et Travaux Publics',
    'Immobilier / Promotion immobilière',
    'Architecture / Urbanisme / Design',
    'Transport / Logistique / Transit',
    'Tourisme / Hôtellerie / Restauration',
    'Technologies / Informatique / Digital',
    'Télécommunications',
    'Banque / Finance / Microfinance',
    'Assurance',
    'Comptabilité / Audit / Expertise comptable',
    'Santé / Pharmacie / Médecine',
    'Services à la personne / Aide sociale',
    'Éducation / Formation / Enseignement',
    'Conseil / Management',
    'Juridique / Droit / Notariat',
    'Marketing / Communication / Publicité',
    'Médias / Presse / Audiovisuel',
    'Culture / Arts / Divertissement / Sport',
    'Sécurité / Surveillance',
    'Nettoyage / Entretien / Facility Management',
    'ONG / Association / Organisation humanitaire',
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
                <span>{{ __('Nom de l\'entreprise ou du cabinet') }} *</span>
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

                <select
                    x-model="choice"
                    class="auth-input"
                    required
                >
                    <option value="" disabled>{{ __('Sélectionnez un secteur...') }}</option>
                    @foreach ($sectors as $sector)
                        <option value="{{ $sector }}">{{ $sector }}</option>
                    @endforeach
                    <option value="__other__">{{ __('Autre') }}</option>
                </select>

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
                <span>{{ __('NINEA') }}</span>
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
                <span>{{ __('RCCM') }}</span>
                <input
                    name="rccm"
                    type="text"
                    value="{{ old('rccm') }}"
                    placeholder="{{ __('Registre du Commerce et du Crédit Mobilier') }}"
                    class="auth-input"
                />
                <x-auth-field-error name="rccm" />
            </label>

            <button type="submit" class="auth-button">
                {{ __('Finaliser mon inscription') }}
            </button>
        </form>
    </div>
</x-layouts::auth>
