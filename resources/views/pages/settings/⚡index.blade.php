<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Auth\Models\Company;
use Modules\Auth\Services\AuthService;

new #[Title('Paramètres')] class extends Component {
    public string $activeSection = 'profile';

    // ─── Profil du cabinet ──────────────────────────────────────────────
    public string $firmName = '';
    public string $firmEmail = '';
    public string $firmPhone = '';
    public string $firmAddress = '';
    public string $firmCity = '';
    public string $firmCountry = 'SN';
    public string $firmNinea = '';
    public string $firmRccm = '';

    // ─── Compte utilisateur ─────────────────────────────────────────────
    public string $firstName = '';
    public string $lastName = '';
    public string $userEmail = '';

    // ─── Sécurité ───────────────────────────────────────────────────────
    public string $currentPassword = '';
    public string $newPassword = '';
    public string $newPasswordConfirmation = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->firstName = $user->first_name;
        $this->lastName = $user->last_name;
        $this->userEmail = $user->email ?? '';

        $firm = $this->firm;
        if ($firm) {
            $this->firmName = $firm->name ?? '';
            $this->firmEmail = $firm->email ?? '';
            $country = $firm->country_code ?? 'SN';
            $this->firmPhone = $this->formatLocalPhone(
                $this->extractLocalPhone($firm->phone ?? '', $country),
                $country,
            );
            $this->firmAddress = $firm->address ?? '';
            $this->firmCity = $firm->city ?? '';
            $this->firmCountry = $country;
            $this->firmNinea = $firm->ninea ?? '';
            $this->firmRccm = $firm->rccm ?? '';
        }
    }

    #[Computed]
    public function firm(): ?Company
    {
        return Auth::user()->accountantFirm();
    }

    public function setSection(string $section): void
    {
        if (! in_array($section, ['profile', 'account'], true)) {
            return;
        }

        $this->activeSection = $section;
        $this->resetErrorBag();
    }

    public function saveFirmProfile(): void
    {
        $validated = $this->validate([
            'firmName' => ['required', 'string', 'max:255'],
            'firmEmail' => ['nullable', 'email', 'max:255'],
            'firmPhone' => ['nullable', 'string', 'max:30'],
            'firmAddress' => ['nullable', 'string', 'max:255'],
            'firmCity' => ['nullable', 'string', 'max:100'],
            'firmCountry' => ['required', 'string', 'size:2'],
            'firmNinea' => ['nullable', 'string', 'max:50'],
            'firmRccm' => ['nullable', 'string', 'max:50'],
        ]);

        $firm = $this->firm;
        if ($firm) {
            $firm->update([
                'name' => $validated['firmName'],
                'email' => $validated['firmEmail'],
                'phone' => filled($validated['firmPhone']) ? AuthService::normalizePhone($validated['firmPhone'], $validated['firmCountry']) : null,
                'address' => $validated['firmAddress'],
                'city' => $validated['firmCity'],
                'country_code' => $validated['firmCountry'],
                'ninea' => $validated['firmNinea'],
                'rccm' => $validated['firmRccm'],
            ]);
            unset($this->firm);
        }

        session()->flash('firm-saved', true);
    }

    public function saveAccount(): void
    {
        $validated = $this->validate([
            'firstName' => ['required', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'userEmail' => ['nullable', 'email', 'max:255'],
        ]);

        $user = Auth::user();
        $user->update([
            'first_name' => $validated['firstName'],
            'last_name' => $validated['lastName'],
            'email' => $validated['userEmail'] ?: null,
        ]);

        session()->flash('account-saved', true);
    }

    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'currentPassword' => ['required', 'string', 'current_password'],
                'newPassword' => ['required', 'string', Password::defaults(), 'confirmed:newPasswordConfirmation'],
            ], [
                'currentPassword.required' => __('Le mot de passe actuel est obligatoire.'),
                'currentPassword.current_password' => __('Le mot de passe actuel est incorrect.'),
                'newPassword.required' => __('Le nouveau mot de passe est obligatoire.'),
                'newPassword.confirmed' => __('La confirmation du nouveau mot de passe ne correspond pas.'),
                'newPassword.letters' => __('Le nouveau mot de passe doit contenir au moins une lettre.'),
                'newPassword.mixed' => __('Le nouveau mot de passe doit contenir au moins une majuscule et une minuscule.'),
                'newPassword.min' => __('Le nouveau mot de passe doit contenir au moins :min caractères.'),
                'newPassword.numbers' => __('Le nouveau mot de passe doit contenir au moins un chiffre.'),
                'newPassword.symbols' => __('Le nouveau mot de passe doit contenir au moins un symbole.'),
                'newPassword.uncompromised' => __('Le nouveau mot de passe a déjà été exposé dans une fuite de données. Choisissez-en un autre.'),
                'newPasswordConfirmation.required' => __('La confirmation du mot de passe est obligatoire.'),
            ]);
        } catch (ValidationException $e) {
            $this->reset('currentPassword', 'newPassword', 'newPasswordConfirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => $validated['newPassword'],
        ]);

        $this->reset('currentPassword', 'newPassword', 'newPasswordConfirmation');

        session()->flash('password-saved', true);
    }

    protected function extractLocalPhone(string $phone, string $countryCode): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        $prefix = preg_replace('/\D+/', '', (string) config("fayeku.countries.{$countryCode}.prefix", '')) ?? '';

        if ($prefix !== '' && str_starts_with($digits, $prefix)) {
            $digits = substr($digits, strlen($prefix));
        }

        return $digits;
    }

    protected function formatLocalPhone(string $digits, string $countryCode): string
    {
        return match ($countryCode) {
            'SN' => (function (string $value): string {
                $normalized = substr($value, 0, 9);

                return match (true) {
                    strlen($normalized) <= 2 => $normalized,
                    strlen($normalized) <= 5 => substr($normalized, 0, 2).' '.substr($normalized, 2),
                    strlen($normalized) <= 7 => substr($normalized, 0, 2).' '.substr($normalized, 2, 3).' '.substr($normalized, 5),
                    default => substr($normalized, 0, 2).' '.substr($normalized, 2, 3).' '.substr($normalized, 5, 2).' '.substr($normalized, 7),
                };
            })($digits),
            'CI' => trim(implode(' ', str_split(substr($digits, 0, 10), 2))),
            default => $digits,
        };
    }
}; ?>

<div class="settings-page">
    @php
        $settingsPhoneCountries = collect(config('fayeku.countries'))
            ->only(['SN', 'CI'])
            ->map(fn ($c) => $c['label'])
            ->all();
    @endphp

    {{-- ─── Header ──────────────────────────────────────────────────────── --}}
    <section class="app-shell-panel px-8 py-7">
        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-primary">{{ __('Paramètres') }}</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-ink">{{ __('Paramètres') }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ __('Gérez les informations de votre cabinet et la sécurité de votre compte.') }}</p>
    </section>

    @php
        $sections = [
            ['key' => 'profile', 'label' => __('Profil du cabinet')],
            ['key' => 'account', 'label' => __('Compte & sécurité')],
        ];
    @endphp

    <div class="mt-6">
        <nav class="grid grid-cols-2 border-b border-slate-200" aria-label="{{ __('Sections des paramètres') }}">
            @foreach ($sections as $section)
                <button
                    type="button"
                    wire:click="setSection('{{ $section['key'] }}')"
                    wire:key="settings-tab-{{ $section['key'] }}"
                    aria-current="{{ $activeSection === $section['key'] ? 'page' : 'false' }}"
                    @class([
                        'border-b-2 px-4 py-4 text-center text-sm font-semibold whitespace-nowrap transition focus:outline-none',
                        'border-primary text-primary' => $activeSection === $section['key'],
                        'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' => $activeSection !== $section['key'],
                    ])
                >
                    {{ $section['label'] }}
                </button>
            @endforeach
        </nav>
    </div>

    {{-- ─── Contenu principal ───────────────────────────────────────────── --}}
    <main class="mt-6">

            {{-- ═══ Section: Profil du cabinet ═══════════════════════════ --}}
            @if ($activeSection === 'profile')
                <div class="space-y-6">
                    {{-- Identité --}}
                    <section class="app-shell-panel px-6 py-6">
                        <h2 class="text-base font-bold text-ink">{{ __('Profil du cabinet') }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ __('Ces informations sont utilisées dans votre espace Fayeku et sur certains documents générés.') }}</p>

                        @if (session('firm-saved'))
                            <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                                {{ __('Profil du cabinet enregistré avec succès.') }}
                            </div>
                        @endif

                        <form wire:submit="saveFirmProfile" class="mt-6 space-y-8">
                            <div class="grid gap-6 sm:grid-cols-2">
                                <label class="auth-label sm:col-span-2">
                                    <span>{{ __('Nom du cabinet') }}</span>
                                    <input wire:model="firmName" type="text" required class="auth-input" />
                                    @error('firmName') <p class="auth-error">{{ $message }}</p> @enderror
                                </label>
                                <x-phone-input
                                    :label="__('Téléphone du cabinet')"
                                    country-name="firmCountry"
                                    :country-value="$firmCountry"
                                    :countries="$settingsPhoneCountries"
                                    country-model="firmCountry"
                                    phone-name="firmPhone"
                                    :phone-value="$firmPhone"
                                    phone-model="firmPhone"
                                    phone-placeholder="77 000 00 00"
                                />
                                @error('firmPhone') <p class="auth-error">{{ $message }}</p> @enderror
                                <label class="auth-label">
                                    <span>{{ __('Adresse e-mail professionnelle') }}</span>
                                    <input wire:model="firmEmail" type="email" class="auth-input" />
                                    @error('firmEmail') <p class="auth-error">{{ $message }}</p> @enderror
                                </label>
                                <label class="auth-label sm:col-span-2">
                                    <span>{{ __('Adresse') }}</span>
                                    <input wire:model="firmAddress" type="text" class="auth-input" />
                                    @error('firmAddress') <p class="auth-error">{{ $message }}</p> @enderror
                                </label>
                                <label class="auth-label">
                                    <span>{{ __('Ville') }}</span>
                                    <input wire:model="firmCity" type="text" class="auth-input" />
                                    @error('firmCity') <p class="auth-error">{{ $message }}</p> @enderror
                                </label>
                                <label class="auth-label">
                                    <span>{{ __('Pays du cabinet') }}</span>
                                    <x-select-native>
                                        <select wire:model="firmCountry" class="auth-select">
                                            <option value="SN">{{ __('Sénégal') }}</option>
                                            <option value="CI">{{ __('Côte d\'Ivoire') }}</option>
                                        </select>
                                    </x-select-native>
                                    @error('firmCountry') <p class="auth-error">{{ $message }}</p> @enderror
                                </label>
                            </div>

                            {{-- Informations légales --}}
                            <div>
                                <h3 class="text-sm font-semibold text-ink">{{ __('Informations légales') }}</h3>
                                <p class="mt-1 text-sm text-slate-500">{{ __('Identifiants fiscaux et registre du commerce.') }}</p>
                                <div class="mt-4 grid gap-6 sm:grid-cols-2">
                                    <label class="auth-label">
                                        <span>{{ __('NINEA') }}</span>
                                        <input wire:model="firmNinea" type="text" placeholder="Ex. SN123456789" class="auth-input" />
                                        @error('firmNinea') <p class="auth-error">{{ $message }}</p> @enderror
                                    </label>
                                    <label class="auth-label">
                                        <span>{{ __('RCCM') }}</span>
                                        <input wire:model="firmRccm" type="text" placeholder="Ex. SN-DKR-2024-B-12345" class="auth-input" />
                                        @error('firmRccm') <p class="auth-error">{{ $message }}</p> @enderror
                                    </label>
                                </div>
                            </div>

                            <div class="flex items-center gap-4 border-t border-slate-100 pt-6">
                                <button type="submit" class="inline-flex items-center rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90">
                                    {{ __('Enregistrer les modifications') }}
                                </button>
                            </div>
                        </form>
                    </section>
                </div>

            {{-- ═══ Section: Compte & sécurité ═══════════════════════════ --}}
            @elseif ($activeSection === 'account')
                <div class="space-y-6">
                    {{-- Informations du compte --}}
                    <section class="app-shell-panel px-6 py-6">
                        <h2 class="text-base font-bold text-ink">{{ __('Informations du compte') }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ __('Gérez les informations associées à votre compte utilisateur.') }}</p>

                        @if (session('account-saved'))
                            <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                                {{ __('Compte mis à jour avec succès.') }}
                            </div>
                        @endif

                        <form wire:submit="saveAccount" class="mt-6 space-y-6">
                            <div class="grid gap-6 sm:grid-cols-2">
                                <label class="auth-label">
                                    <span>{{ __('Prénom') }}</span>
                                    <input wire:model="firstName" type="text" required autocomplete="given-name" class="auth-input" />
                                    @error('firstName') <p class="auth-error">{{ $message }}</p> @enderror
                                </label>
                                <label class="auth-label">
                                    <span>{{ __('Nom') }}</span>
                                    <input wire:model="lastName" type="text" required autocomplete="family-name" class="auth-input" />
                                    @error('lastName') <p class="auth-error">{{ $message }}</p> @enderror
                                </label>
                                <label class="auth-label">
                                    <span>{{ __('Adresse e-mail') }}</span>
                                    <input wire:model="userEmail" type="email" autocomplete="email" class="auth-input" />
                                    @error('userEmail') <p class="auth-error">{{ $message }}</p> @enderror
                                </label>
                                <x-phone-input
                                    :label="__('Téléphone')"
                                    country-name="userCountryDisplay"
                                    :country-value="Auth::user()->country_code ?? 'SN'"
                                    :countries="$settingsPhoneCountries"
                                    phone-name="userPhoneDisplay"
                                    :phone-value="Auth::user()->phone"
                                    :readonly="true"
                                />
                            </div>

                            <div class="flex items-center gap-4 border-t border-slate-100 pt-6">
                                <button type="submit" class="inline-flex items-center rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90">
                                    {{ __('Mettre à jour le compte') }}
                                </button>
                            </div>
                        </form>
                    </section>

                    {{-- Sécurité — Mot de passe --}}
                    <section class="app-shell-panel px-6 py-6">
                        <h2 class="text-base font-bold text-ink">{{ __('Sécurité') }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ __('Protégez l\'accès à votre compte Fayeku.') }}</p>

                        @if (session('password-saved'))
                            <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                                {{ __('Mot de passe modifié avec succès.') }}
                            </div>
                        @endif

                        <form wire:submit="updatePassword" class="mt-6 space-y-6">
                            <div class="max-w-md space-y-6">
                                <label class="auth-label">
                                    <span>{{ __('Mot de passe actuel') }}</span>
                                    <input wire:model="currentPassword" type="password" required autocomplete="current-password" class="auth-input" />
                                    @error('currentPassword') <p class="auth-error">{{ $message }}</p> @enderror
                                </label>
                                <label class="auth-label">
                                    <span>{{ __('Nouveau mot de passe') }}</span>
                                    <input wire:model="newPassword" type="password" required autocomplete="new-password" class="auth-input" />
                                    @error('newPassword') <p class="auth-error">{{ $message }}</p> @enderror
                                </label>
                                <label class="auth-label">
                                    <span>{{ __('Confirmer le mot de passe') }}</span>
                                    <input wire:model="newPasswordConfirmation" type="password" required autocomplete="new-password" class="auth-input" />
                                    @error('newPasswordConfirmation') <p class="auth-error">{{ $message }}</p> @enderror
                                </label>
                            </div>

                            <div class="flex items-center gap-4 border-t border-slate-100 pt-6">
                                <button type="submit" class="inline-flex items-center rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90">
                                    {{ __('Modifier le mot de passe') }}
                                </button>
                            </div>
                        </form>
                    </section>

                </div>
            @endif

    </main>
</div>
