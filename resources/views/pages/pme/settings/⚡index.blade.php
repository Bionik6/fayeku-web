<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Livewire\Actions\Logout;
use App\Models\Auth\Company;
use App\Models\Auth\Subscription;
use App\Services\Auth\AuthService;

new #[Title('Paramètres')] #[Layout('layouts::pme')] class extends Component {
    #[Url(as: 'section')]
    public string $activeSection = 'company';

    // ─── Profil de l'entreprise ─────────────────────────────────────────
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

    // ─── Signature des relances ─────────────────────────────────────────
    public string $senderName = '';
    public string $senderRole = '';

    // ─── Sécurité ───────────────────────────────────────────────────────
    public string $currentPassword = '';
    public string $newPassword = '';
    public string $newPasswordConfirmation = '';

    // ─── Modales ────────────────────────────────────────────────────────
    public bool $showCancelPlanModal = false;
    public bool $showDeleteAccountModal = false;
    public string $deletePassword = '';

    public function mount(): void
    {
        if (! in_array($this->activeSection, ['company', 'profile', 'signature', 'password', 'plan', 'danger'], true)) {
            $this->activeSection = 'company';
        }

        $user = Auth::user();
        $this->firstName = $user->first_name;
        $this->lastName = $user->last_name;
        $this->userEmail = $user->email ?? '';

        $company = $this->company;
        if ($company) {
            $this->firmName = $company->name ?? '';
            $this->firmEmail = $company->email ?? '';
            $country = $company->country_code ?? 'SN';
            $this->firmPhone = $this->formatLocalPhone(
                $this->extractLocalPhone($company->phone ?? '', $country),
                $country,
            );
            $this->firmAddress = $company->address ?? '';
            $this->firmCity = $company->city ?? '';
            $this->firmCountry = $country;
            $this->firmNinea = $company->ninea ?? '';
            $this->firmRccm = $company->rccm ?? '';
            $this->senderName = $company->sender_name ?? '';
            $this->senderRole = $company->sender_role ?? '';
        }
    }

    #[Computed]
    public function company(): ?Company
    {
        return Auth::user()->smeCompany();
    }

    #[Computed]
    public function subscription(): ?Subscription
    {
        return $this->company?->subscription;
    }

    #[Computed]
    public function currentPlan(): ?array
    {
        $slug = $this->subscription?->plan_slug;

        if (! $slug) {
            return null;
        }

        return collect(config('marketing.pricing_plans'))->firstWhere('slug', $slug);
    }

    #[Computed]
    public function allPlans(): array
    {
        return config('marketing.pricing_plans', []);
    }

    #[Computed]
    public function comparisonSections(): array
    {
        return config('marketing.pricing_comparison_sections', []);
    }

    public function planRank(string $slug): int
    {
        return match ($slug) {
            'basique' => 0,
            'essentiel' => 1,
            'entreprise' => 2,
            default => -1,
        };
    }

    public function setSection(string $section): void
    {
        if (! in_array($section, ['company', 'profile', 'signature', 'password', 'plan', 'danger'], true)) {
            return;
        }

        $this->activeSection = $section;
        $this->resetErrorBag();
    }

    #[Computed]
    public function signaturePreview(): string
    {
        $company = $this->company ?? tap(new Company, fn (Company $c) => $c->name = 'Votre entreprise');
        $company->sender_name = trim($this->senderName) ?: null;
        $company->sender_role = trim($this->senderRole) ?: null;

        return $company->composeSenderSignature();
    }

    public function saveCompanyProfile(): void
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

        $company = $this->company;
        if ($company) {
            $company->update([
                'name' => $validated['firmName'],
                'email' => $validated['firmEmail'],
                'phone' => filled($validated['firmPhone']) ? AuthService::normalizePhone($validated['firmPhone'], $validated['firmCountry']) : null,
                'address' => $validated['firmAddress'],
                'city' => $validated['firmCity'],
                'country_code' => $validated['firmCountry'],
                'ninea' => $validated['firmNinea'],
                'rccm' => $validated['firmRccm'],
            ]);
            unset($this->company);
        }

        session()->flash('firm-saved', true);
    }

    public function saveSignature(): void
    {
        $validated = $this->validate([
            'senderName' => ['nullable', 'string', 'max:100'],
            'senderRole' => ['nullable', 'string', 'max:100'],
        ]);

        $company = $this->company;
        if ($company) {
            $company->update([
                'sender_name' => filled($validated['senderName']) ? trim($validated['senderName']) : null,
                'sender_role' => filled($validated['senderRole']) ? trim($validated['senderRole']) : null,
            ]);
            unset($this->company);
        }

        session()->flash('signature-saved', true);
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

    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'deletePassword' => ['required', 'string', 'current_password'],
        ], [
            'deletePassword.required' => __('Le mot de passe est obligatoire.'),
            'deletePassword.current_password' => __('Le mot de passe est incorrect.'),
        ]);

        $user = Auth::user();

        $logout();

        $user?->delete();

        $this->redirect('/', navigate: true);
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

@php
    $settingsPhoneCountries = collect(config('fayeku.countries'))
        ->only(['SN', 'CI'])
        ->map(fn ($c) => $c['label'])
        ->all();

@endphp

<div class="settings-page">
    {{-- ─── Header ──────────────────────────────────────────────────────── --}}
    <section class="app-shell-panel px-8 py-7">
        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-primary">{{ __('Paramètres') }}</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-ink">{{ __('Paramètres') }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ __('Gérez les informations de votre entreprise et la sécurité de votre compte.') }}</p>
    </section>

    {{-- ─── Layout stacked ──────────────────────────────────────────────── --}}
    <div class="mt-6 lg:flex lg:gap-x-16">

        {{-- ─── Sidebar navigation ──────────────────────────────────────── --}}
        <aside class="flex overflow-x-auto border-b border-slate-900/5 py-4 lg:block lg:w-64 lg:flex-none lg:border-0 lg:py-10">
            <nav class="flex-none px-4 sm:px-6 lg:px-0">
                <ul role="list" class="flex gap-x-3 gap-y-2 whitespace-nowrap lg:flex-col">
                    {{-- Mon Entreprise --}}
                    <li>
                        <button type="button" wire:click="setSection('company')" @class([
                            'group flex w-full items-center gap-4 rounded-2xl px-3 py-3 text-[1.05rem] leading-7 font-semibold transition',
                            'bg-primary text-white' => $activeSection === 'company',
                            'text-ink hover:bg-slate-100 hover:text-primary active:bg-slate-200 cursor-pointer' => $activeSection !== 'company',
                        ])>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" @class(['size-6 shrink-0 transition', 'text-accent' => $activeSection === 'company', 'text-ink group-hover:text-primary' => $activeSection !== 'company'])>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                            </svg>
                            {{ __('Mon Entreprise') }}
                        </button>
                    </li>
                    {{-- Mon Profil --}}
                    <li>
                        <button type="button" wire:click="setSection('profile')" @class([
                            'group flex w-full items-center gap-4 rounded-2xl px-3 py-3 text-[1.05rem] leading-7 font-semibold transition',
                            'bg-primary text-white' => $activeSection === 'profile',
                            'text-ink hover:bg-slate-100 hover:text-primary active:bg-slate-200 cursor-pointer' => $activeSection !== 'profile',
                        ])>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" @class(['size-6 shrink-0 transition', 'text-accent' => $activeSection === 'profile', 'text-ink group-hover:text-primary' => $activeSection !== 'profile'])>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                            {{ __('Mon Profil') }}
                        </button>
                    </li>
                    {{-- Signature des relances --}}
                    <li>
                        <button type="button" wire:click="setSection('signature')" @class([
                            'group flex w-full items-center gap-4 rounded-2xl px-3 py-3 text-[1.05rem] leading-7 font-semibold transition',
                            'bg-primary text-white' => $activeSection === 'signature',
                            'text-ink hover:bg-slate-100 hover:text-primary active:bg-slate-200 cursor-pointer' => $activeSection !== 'signature',
                        ])>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" @class(['size-6 shrink-0 transition', 'text-accent' => $activeSection === 'signature', 'text-ink group-hover:text-primary' => $activeSection !== 'signature'])>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
                            </svg>
                            {{ __('Signature des relances') }}
                        </button>
                    </li>
                    {{-- Mot de passe --}}
                    <li>
                        <button type="button" wire:click="setSection('password')" @class([
                            'group flex w-full items-center gap-4 rounded-2xl px-3 py-3 text-[1.05rem] leading-7 font-semibold transition',
                            'bg-primary text-white' => $activeSection === 'password',
                            'text-ink hover:bg-slate-100 hover:text-primary active:bg-slate-200 cursor-pointer' => $activeSection !== 'password',
                        ])>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" @class(['size-6 shrink-0 transition', 'text-accent' => $activeSection === 'password', 'text-ink group-hover:text-primary' => $activeSection !== 'password'])>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                            </svg>
                            {{ __('Mot de passe') }}
                        </button>
                    </li>
                    {{-- Plan --}}
                    <li>
                        <button type="button" wire:click="setSection('plan')" @class([
                            'group flex w-full items-center gap-4 rounded-2xl px-3 py-3 text-[1.05rem] leading-7 font-semibold transition',
                            'bg-primary text-white' => $activeSection === 'plan',
                            'text-ink hover:bg-slate-100 hover:text-primary active:bg-slate-200 cursor-pointer' => $activeSection !== 'plan',
                        ])>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" @class(['size-6 shrink-0 transition', 'text-accent' => $activeSection === 'plan', 'text-ink group-hover:text-primary' => $activeSection !== 'plan'])>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                            </svg>
                            {{ __('Plan') }}
                        </button>
                    </li>
                    {{-- Danger --}}
                    <li>
                        <button type="button" wire:click="setSection('danger')" @class([
                            'group flex w-full items-center gap-4 rounded-2xl px-3 py-3 text-[1.05rem] leading-7 font-semibold transition',
                            'bg-primary text-white' => $activeSection === 'danger',
                            'text-ink hover:bg-slate-100 hover:text-primary active:bg-slate-200 cursor-pointer' => $activeSection !== 'danger',
                        ])>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" @class(['size-6 shrink-0 transition', 'text-accent' => $activeSection === 'danger', 'text-ink group-hover:text-primary' => $activeSection !== 'danger'])>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                            </svg>
                            {{ __('Danger') }}
                        </button>
                    </li>
                </ul>
            </nav>
        </aside>

        {{-- ─── Contenu principal ───────────────────────────────────────── --}}
        <main class="flex-auto py-6 lg:py-10">
            <div class="mx-auto max-w-2xl lg:mx-0 lg:max-w-none">

                {{-- ═══ Section : Mon Entreprise ════════════════════════════ --}}
                @if ($activeSection === 'company')
                    <section class="app-shell-panel px-6 py-6">
                        <h2 class="text-base font-bold text-ink">{{ __('Mon Entreprise') }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ __('Ces informations sont utilisées dans votre espace Fayeku et sur vos documents générés.') }}</p>

                        @if (session('firm-saved'))
                            <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                                {{ __('Profil de l\'entreprise enregistré avec succès.') }}
                            </div>
                        @endif

                        <form wire:submit="saveCompanyProfile" class="mt-6 space-y-8">
                            <div class="grid gap-6 sm:grid-cols-2">
                                <label class="auth-label sm:col-span-2">
                                    <span>{{ __('Nom de l\'entreprise') }}</span>
                                    <input wire:model="firmName" type="text" required class="auth-input" />
                                    @error('firmName') <p class="auth-error">{{ $message }}</p> @enderror
                                </label>
                                <x-phone-input
                                    :label="__('Téléphone de l\'entreprise')"
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
                                    <span>{{ __('Pays') }}</span>
                                    <x-select-native>
                                        <select wire:model.live="firmCountry" class="auth-select col-start-1 row-start-1 appearance-none">
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

                {{-- ═══ Section : Mon Profil ═════════════════════════════════ --}}
                @elseif ($activeSection === 'profile')
                    <section class="app-shell-panel px-6 py-6">
                        <h2 class="text-base font-bold text-ink">{{ __('Mon Profil') }}</h2>
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

                {{-- ═══ Section : Signature des relances ═══════════════════════ --}}
                @elseif ($activeSection === 'signature')
                    <section class="app-shell-panel px-6 py-6">
                        <h2 class="text-base font-bold text-ink">{{ __('Personnalisez la signature de vos relances WhatsApp') }}</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ __('Par défaut, vos relances sont signées au nom de votre entreprise. Vous pouvez personnaliser la signature pour renforcer la relation avec vos clients.') }}
                        </p>

                        @if (session('signature-saved'))
                            <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                                {{ __('Signature enregistrée avec succès.') }}
                            </div>
                        @endif

                        <form wire:submit="saveSignature" class="mt-6 space-y-6">
                            <div class="grid gap-6 sm:grid-cols-2">
                                <label class="auth-label">
                                    <span>{{ __('Votre nom (optionnel)') }}</span>
                                    <input
                                        wire:model.live.debounce.200ms="senderName"
                                        type="text"
                                        maxlength="100"
                                        placeholder="Ex. Moussa Diop"
                                        class="auth-input"
                                    />
                                    @error('senderName') <p class="auth-error">{{ $message }}</p> @enderror
                                </label>
                                <label class="auth-label">
                                    <span>{{ __('Votre fonction (optionnel)') }}</span>
                                    <input
                                        wire:model.live.debounce.200ms="senderRole"
                                        type="text"
                                        maxlength="100"
                                        placeholder="Ex. Directeur commercial"
                                        class="auth-input"
                                    />
                                    @error('senderRole') <p class="auth-error">{{ $message }}</p> @enderror
                                </label>
                            </div>

                            {{-- Aperçu de la signature --}}
                            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 px-5 py-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                    {{ __('Aperçu de la signature') }}
                                </p>
                                <p class="mt-2 text-sm font-medium text-ink" wire:key="signature-preview-{{ $senderName }}-{{ $senderRole }}">
                                    {{ $this->signaturePreview }}
                                </p>
                                <p class="mt-2 text-xs text-slate-500">
                                    {{ __('Cette ligne apparaîtra dans vos messages WhatsApp à l\'endroit de la signature, juste avant « via Fayeku ».') }}
                                </p>
                            </div>

                            <div class="flex items-center gap-4 border-t border-slate-100 pt-6">
                                <button type="submit" class="inline-flex items-center rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90">
                                    {{ __('Enregistrer la signature') }}
                                </button>
                            </div>
                        </form>
                    </section>

                {{-- ═══ Section : Mot de passe ═══════════════════════════════ --}}
                @elseif ($activeSection === 'password')
                    <section class="app-shell-panel px-6 py-6">
                        <h2 class="text-base font-bold text-ink">{{ __('Mot de passe') }}</h2>
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

                {{-- ═══ Section : Plan ═══════════════════════════════════════ --}}
                @elseif ($activeSection === 'plan')
                    @php
                        $sub = $this->subscription;
                        $currentSlug = $sub?->plan_slug;
                        $status = $sub?->status;
                        $isTrialExpired = $status === 'trial' && $sub?->trial_ends_at?->isPast();
                        $currentRank = $currentSlug ? $this->planRank($currentSlug) : -1;
                    @endphp

                    <div class="space-y-6">
                        {{-- ── Usage WhatsApp du mois ──────────────────────────────── --}}
                        @php
                            $quotaUsage = $this->company
                                ? app(\App\Services\Shared\QuotaService::class)->usage($this->company, 'reminders')
                                : null;
                        @endphp
                        @if ($quotaUsage)
                            <section @class([
                                'app-shell-panel px-6 py-6',
                                'border-rose-200' => ! $quotaUsage['unlimited'] && $quotaUsage['available'] <= 0,
                                'border-amber-200' => ! $quotaUsage['unlimited'] && $quotaUsage['available'] > 0 && ($quotaUsage['percent'] ?? 0) >= 80,
                            ])>
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div>
                                        <h3 class="text-base font-bold text-ink">{{ __('Usage WhatsApp ce mois-ci') }}</h3>
                                        <p class="mt-1 text-sm text-slate-500">{{ __('Relances et notifications envoyées à vos clients via WhatsApp.') }}</p>
                                    </div>
                                    @if ($quotaUsage['unlimited'])
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                                            {{ __('Illimité') }}
                                        </span>
                                    @elseif ($quotaUsage['available'] <= 0)
                                        <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 ring-1 ring-inset ring-rose-600/20">
                                            {{ __('Épuisé') }}
                                        </span>
                                    @elseif (($quotaUsage['percent'] ?? 0) >= 80)
                                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20">
                                            {{ __('Attention') }}
                                        </span>
                                    @endif
                                </div>

                                @if ($quotaUsage['unlimited'])
                                    <p class="mt-4 text-sm text-slate-600">
                                        {{ __('Vous disposez d\'envois WhatsApp illimités avec votre plan.') }}
                                        <span class="font-semibold text-ink">{{ number_format($quotaUsage['used'], 0, ',', ' ') }} {{ __('message(s) envoyé(s) ce mois.') }}</span>
                                    </p>
                                @else
                                    <div class="mt-5">
                                        <div class="flex items-baseline justify-between">
                                            <div>
                                                <span class="text-3xl font-bold text-ink">{{ number_format($quotaUsage['used'], 0, ',', ' ') }}</span>
                                                <span class="text-sm text-slate-500"> / {{ number_format($quotaUsage['limit'] + $quotaUsage['addons'], 0, ',', ' ') }} {{ __('messages') }}</span>
                                            </div>
                                            <span class="text-sm font-semibold text-slate-600">{{ $quotaUsage['percent'] ?? 0 }}%</span>
                                        </div>
                                        <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                            <div @class([
                                                'h-full rounded-full transition-all',
                                                'bg-rose-500' => $quotaUsage['available'] <= 0,
                                                'bg-amber-500' => $quotaUsage['available'] > 0 && ($quotaUsage['percent'] ?? 0) >= 80,
                                                'bg-primary' => ($quotaUsage['percent'] ?? 0) < 80,
                                            ]) style="width: {{ $quotaUsage['percent'] ?? 0 }}%"></div>
                                        </div>
                                        @if ($quotaUsage['available'] <= 0)
                                            <p class="mt-3 text-sm text-rose-700">
                                                {{ __('Votre quota est épuisé. Les prochaines relances et notifications ne seront plus envoyées jusqu\'à la fin du mois ou jusqu\'à un upgrade de votre plan.') }}
                                            </p>
                                        @elseif (($quotaUsage['percent'] ?? 0) >= 80)
                                            <p class="mt-3 text-sm text-amber-700">
                                                {{ __('Il vous reste :count envoi(s) ce mois.', ['count' => number_format($quotaUsage['available'], 0, ',', ' ')]) }}
                                            </p>
                                        @else
                                            <p class="mt-3 text-sm text-slate-500">
                                                {{ __('Il vous reste :count envoi(s) ce mois.', ['count' => number_format($quotaUsage['available'], 0, ',', ' ')]) }}
                                            </p>
                                        @endif
                                    </div>
                                @endif
                            </section>
                        @endif

                        {{-- ── Bannière plan actuel ─────────────────────────────── --}}
                        <section class="app-shell-panel px-6 py-6">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <h2 class="text-base font-bold text-ink">{{ __('Plan actuel') }}</h2>
                                    <p class="mt-1 text-sm text-slate-500">{{ __('Gérez votre abonnement et votre facturation.') }}</p>
                                </div>

                                @if ($sub && $status !== 'cancelled')
                                    <button type="button" wire:click="$set('showCancelPlanModal', true)" class="text-sm font-medium text-rose-600 transition hover:text-rose-500">
                                        {{ __('Résilier l\'abonnement') }}
                                    </button>
                                @endif
                            </div>

                            @if ($sub)
                                <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50/50 p-5">
                                    <div class="flex flex-wrap items-center justify-between gap-4">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary">
                                                {{ $this->currentPlan['name'] ?? ucfirst($currentSlug) }}
                                            </p>
                                            <p class="mt-1 text-2xl font-bold text-ink">
                                                {{ $this->currentPlan['price'] ?? '—' }}
                                            </p>
                                            @if ($sub->current_period_end && $status === 'active')
                                                <p class="mt-0.5 text-sm text-slate-500">
                                                    {{ __('Renouvellement le :date', ['date' => $sub->current_period_end->translatedFormat('d F Y')]) }}
                                                </p>
                                            @endif
                                        </div>

                                        <div>
                                            @if ($isTrialExpired)
                                                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20">
                                                    {{ __('Essai expiré') }}
                                                </span>
                                            @elseif ($status === 'trial')
                                                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20">
                                                    {{ __('Essai · expire le :date', ['date' => $sub->trial_ends_at?->translatedFormat('d M Y') ?? '—']) }}
                                                </span>
                                            @elseif ($status === 'active')
                                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                                                    {{ __('Actif') }}
                                                </span>
                                            @elseif ($status === 'cancelled')
                                                <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 ring-1 ring-inset ring-rose-600/20">
                                                    {{ __('Résilié') }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Détails de l'abonnement --}}
                                    <dl class="mt-5 grid gap-4 border-t border-slate-200 pt-5 sm:grid-cols-3">
                                        <div>
                                            <dt class="text-xs font-medium text-slate-500">{{ __('Cycle') }}</dt>
                                            <dd class="mt-1 text-sm font-semibold text-ink">
                                                {{ match ($sub->billing_cycle) {
                                                    'monthly' => __('Mensuel'),
                                                    'annual', 'yearly' => __('Annuel'),
                                                    'trial' => __('Essai'),
                                                    default => ucfirst($sub->billing_cycle ?? '—'),
                                                } }}
                                            </dd>
                                        </div>
                                        @if ($sub->current_period_start && $sub->current_period_end)
                                            <div>
                                                <dt class="text-xs font-medium text-slate-500">{{ __('Période en cours') }}</dt>
                                                <dd class="mt-1 text-sm font-semibold text-ink">
                                                    {{ $sub->current_period_start->translatedFormat('d M Y') }}
                                                    &mdash;
                                                    {{ $sub->current_period_end->translatedFormat('d M Y') }}
                                                </dd>
                                            </div>
                                        @endif
                                        @if ($sub->cancelled_at)
                                            <div>
                                                <dt class="text-xs font-medium text-slate-500">{{ __('Résilié le') }}</dt>
                                                <dd class="mt-1 text-sm font-semibold text-rose-600">
                                                    {{ $sub->cancelled_at->translatedFormat('d F Y') }}
                                                </dd>
                                            </div>
                                        @endif
                                    </dl>

                                    {{-- Lien de paiement (manuel) --}}
                                    @if ($status === 'trial' || $isTrialExpired)
                                        <div class="mt-5 border-t border-slate-200 pt-5">
                                            <a href="{{ config('marketing.site.social.whatsapp', '/contact') }}" target="_blank" class="inline-flex items-center rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90">
                                                {{ __('Payer mon abonnement') }}
                                            </a>
                                            <p class="mt-2 text-xs text-slate-500">{{ __('Vous serez redirigé vers notre service de paiement.') }}</p>
                                        </div>
                                    @endif
                                </div>
                            @else
                                <div class="mt-6 rounded-2xl border border-dashed border-slate-300 px-6 py-10 text-center">
                                    <svg class="mx-auto size-10 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <p class="mt-3 text-sm font-semibold text-ink">{{ __('Aucun abonnement actif') }}</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ __('Choisissez un plan ci-dessous pour commencer.') }}</p>
                                </div>
                            @endif
                        </section>

                        {{-- ── Changer de plan ──────────────────────────────────── --}}
                        <section class="app-shell-panel px-6 py-6">
                            <h3 class="text-base font-bold text-ink">{{ __('Changer de plan') }}</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ __('Comparez les plans et choisissez celui qui correspond le mieux à vos besoins.') }}</p>

                            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                                @foreach ($this->allPlans as $plan)
                                    @php
                                        $planRank = $this->planRank($plan['slug']);
                                        $isCurrent = $currentSlug === $plan['slug'];
                                        $isUpgrade = $planRank > $currentRank && $currentRank >= 0;
                                        $isDowngrade = $planRank < $currentRank && $currentRank >= 0;
                                        $isEnterprise = $plan['slug'] === 'entreprise';
                                    @endphp
                                    <div @class([
                                        'relative rounded-2xl border p-5 transition',
                                        'border-primary bg-primary/5 ring-1 ring-primary' => $isCurrent,
                                        'border-slate-200 hover:border-slate-300' => ! $isCurrent,
                                    ])>
                                        {{-- Badge actuel / populaire --}}
                                        @if ($isCurrent)
                                            <span class="absolute -top-2.5 right-4 inline-flex items-center rounded-full bg-primary px-2.5 py-0.5 text-[0.65rem] font-bold uppercase tracking-wider text-white">
                                                {{ __('Actuel') }}
                                            </span>
                                        @elseif (! empty($plan['popular']))
                                            <span class="absolute -top-2.5 right-4 inline-flex items-center rounded-full bg-amber-500 px-2.5 py-0.5 text-[0.65rem] font-bold uppercase tracking-wider text-white">
                                                {{ __('Populaire') }}
                                            </span>
                                        @endif

                                        <p class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">{{ $plan['name'] }}</p>
                                        <p class="mt-2 text-lg font-bold text-ink">{{ $plan['price'] }}</p>
                                        <p class="mt-0.5 text-xs text-slate-500">{{ $plan['secondary_price'] }}</p>

                                        {{-- Fonctionnalités --}}
                                        <ul class="mt-4 space-y-1.5">
                                            @foreach ($plan['features'] as $feature)
                                                <li class="flex items-start gap-x-2 text-xs text-slate-600">
                                                    <svg class="mt-0.5 size-3.5 shrink-0 text-emerald-500" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                                    </svg>
                                                    {{ $feature }}
                                                </li>
                                            @endforeach
                                        </ul>

                                        {{-- CTA --}}
                                        <div class="mt-5">
                                            @if ($isCurrent)
                                                <span class="block w-full rounded-xl border border-primary/20 bg-primary/5 px-4 py-2 text-center text-sm font-semibold text-primary">
                                                    {{ __('Plan actuel') }}
                                                </span>
                                            @elseif ($isEnterprise)
                                                <a href="{{ config('marketing.site.social.whatsapp', '/contact') }}" target="_blank" class="block w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-center text-sm font-semibold text-ink transition hover:border-primary hover:text-primary">
                                                    {{ __('Nous contacter') }}
                                                </a>
                                            @elseif ($isUpgrade)
                                                <a href="{{ config('marketing.site.social.whatsapp', '/contact') }}" target="_blank" class="block w-full rounded-xl bg-primary px-4 py-2 text-center text-sm font-semibold text-white transition hover:bg-primary/90">
                                                    {{ __('Passer à :plan', ['plan' => $plan['name']]) }}
                                                </a>
                                            @elseif ($isDowngrade)
                                                <a href="{{ config('marketing.site.social.whatsapp', '/contact') }}" target="_blank" class="block w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-center text-sm font-semibold text-ink transition hover:border-slate-300">
                                                    {{ __('Rétrograder') }}
                                                </a>
                                            @else
                                                <a href="{{ config('marketing.site.social.whatsapp', '/contact') }}" target="_blank" class="block w-full rounded-xl bg-primary px-4 py-2 text-center text-sm font-semibold text-white transition hover:bg-primary/90">
                                                    {{ $plan['cta'] }}
                                                </a>
                                            @endif
                                        </div>

                                        {{-- Indicateur upgrade/downgrade --}}
                                        @if ($isUpgrade)
                                            <p class="mt-2 text-center text-xs font-medium text-emerald-600">
                                                <svg class="mr-0.5 inline size-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 17a.75.75 0 0 1-.75-.75V5.612L5.29 9.77a.75.75 0 0 1-1.08-1.04l5.25-5.5a.75.75 0 0 1 1.08 0l5.25 5.5a.75.75 0 1 1-1.08 1.04l-3.96-4.158V16.25A.75.75 0 0 1 10 17Z" clip-rule="evenodd" /></svg>
                                                {{ __('Upgrade') }}
                                            </p>
                                        @elseif ($isDowngrade)
                                            <p class="mt-2 text-center text-xs font-medium text-amber-600">
                                                <svg class="mr-0.5 inline size-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a.75.75 0 0 1 .75.75v10.638l3.96-4.158a.75.75 0 1 1 1.08 1.04l-5.25 5.5a.75.75 0 0 1-1.08 0l-5.25-5.5a.75.75 0 1 1 1.08-1.04l3.96 4.158V3.75A.75.75 0 0 1 10 3Z" clip-rule="evenodd" /></svg>
                                                {{ __('Downgrade') }}
                                            </p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </section>

                        {{-- ── Comparaison détaillée ────────────────────────────── --}}
                        <section class="app-shell-panel px-6 py-6" x-data="{ showComparison: false }">
                            <button type="button" @click="showComparison = !showComparison" class="flex w-full items-center justify-between text-left">
                                <div>
                                    <h3 class="text-base font-bold text-ink">{{ __('Comparaison détaillée') }}</h3>
                                    <p class="mt-1 text-sm text-slate-500">{{ __('Fonctionnalités incluses par plan.') }}</p>
                                </div>
                                <svg :class="showComparison && 'rotate-180'" class="size-5 shrink-0 text-slate-400 transition-transform duration-200" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                                </svg>
                            </button>

                            <div x-show="showComparison" x-collapse class="mt-6 overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead>
                                        <tr class="border-b border-slate-200">
                                            <th class="w-1/2 pb-3 pr-4 font-medium text-slate-500">{{ __('Fonctionnalité') }}</th>
                                            @foreach ($this->allPlans as $plan)
                                                <th @class([
                                                    'w-1/6 pb-3 px-3 text-center font-medium whitespace-nowrap',
                                                    'text-primary' => $currentSlug === $plan['slug'],
                                                    'text-slate-500' => $currentSlug !== $plan['slug'],
                                                ])>{{ $plan['name'] }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($this->comparisonSections as $sectionIdx => $section)
                                            {{-- Titre de section --}}
                                            <tr>
                                                <td colspan="4" class="{{ $sectionIdx > 0 ? 'pt-6' : 'pt-3' }} pb-2 text-sm font-semibold text-ink">
                                                    {{ $section['title'] }}
                                                </td>
                                            </tr>
                                            {{-- Lignes de la section --}}
                                            @foreach ($section['rows'] as $row)
                                                <tr class="border-t border-slate-100">
                                                    <td class="py-2.5 pr-4 text-slate-600">{{ $row['label'] }}</td>
                                                    @foreach (['basique', 'essentiel', 'entreprise'] as $planSlug)
                                                        <td @class([
                                                            'py-2.5 px-3 text-center whitespace-nowrap',
                                                            'font-semibold text-ink bg-primary/5' => $currentSlug === $planSlug,
                                                            'text-slate-600' => $currentSlug !== $planSlug,
                                                        ])>
                                                            @php $val = $row['values'][$planSlug] ?? '—'; @endphp
                                                            @if ($val === 'Oui')
                                                                <svg class="mx-auto size-4 text-emerald-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
                                                            @elseif ($val === 'Non')
                                                                <svg class="mx-auto size-4 text-slate-300" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg>
                                                            @else
                                                                {{ $val }}
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        {{-- ── Historique de facturation ─────────────────────────── --}}
                        <section class="app-shell-panel px-6 py-6">
                            <h3 class="text-base font-bold text-ink">{{ __('Historique de facturation') }}</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ __('Consultez et téléchargez vos factures d\'abonnement.') }}</p>

                            <div class="mt-6 rounded-2xl border border-dashed border-slate-300 px-6 py-10 text-center">
                                <svg class="mx-auto size-9 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                </svg>
                                <p class="mt-3 text-sm font-semibold text-ink">{{ __('Bientôt disponible') }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ __('L\'historique de facturation et le téléchargement des factures seront disponibles prochainement.') }}</p>
                            </div>
                        </section>
                    </div>

                    {{-- Modal de résiliation --}}
                    @if ($showCancelPlanModal)
                        <div
                            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                            wire:click.self="$set('showCancelPlanModal', false)"
                            x-data
                            @keydown.escape.window="$wire.set('showCancelPlanModal', false)"
                        >
                            <div class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
                                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                    <div class="sm:flex sm:items-start">
                                        <div class="mx-auto flex size-12 shrink-0 items-center justify-center rounded-full bg-rose-100 sm:mx-0 sm:size-10">
                                            <svg class="size-6 text-rose-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                            </svg>
                                        </div>
                                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                            <h3 class="text-base font-semibold text-slate-900">{{ __('Résilier votre abonnement ?') }}</h3>
                                            <div class="mt-2">
                                                <p class="text-sm text-slate-500">{{ __('Votre abonnement restera actif jusqu\'à la fin de la période en cours. Vous perdrez ensuite l\'accès aux fonctionnalités de votre plan.') }}</p>
                                            </div>

                                            @if ($this->currentPlan)
                                                <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                                                    <p class="font-semibold">{{ __('En résiliant, vous perdez :') }}</p>
                                                    <ul class="mt-2 space-y-1">
                                                        @foreach ($this->currentPlan['features'] as $feature)
                                                            <li class="flex items-start gap-x-2">
                                                                <svg class="mt-0.5 size-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg>
                                                                {{ $feature }}
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-slate-50 px-4 py-3 sm:flex sm:justify-end sm:gap-3 sm:px-6">
                                    <button
                                        type="button"
                                        wire:click="$set('showCancelPlanModal', false)"
                                        class="mt-3 inline-flex w-full justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto"
                                    >
                                        {{ __('Garder mon plan') }}
                                    </button>
                                    <a
                                        href="{{ config('marketing.site.social.whatsapp', '/contact') }}"
                                        target="_blank"
                                        class="inline-flex w-full justify-center rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-rose-500 sm:w-auto"
                                    >
                                        {{ __('Résilier') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif

                {{-- ═══ Section : Danger ═════════════════════════════════════ --}}
                @elseif ($activeSection === 'danger')
                    <section class="app-shell-panel border-rose-200 px-6 py-6">
                        <h2 class="text-base font-bold text-rose-600">{{ __('Supprimer le compte') }}</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ __('Supprimez définitivement votre compte et toutes ses données. Cette action est irréversible.') }}
                        </p>

                        <div class="mt-6">
                            <button
                                type="button"
                                wire:click="$set('showDeleteAccountModal', true)"
                                class="inline-flex items-center rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-500"
                            >
                                {{ __('Supprimer mon compte') }}
                            </button>
                        </div>
                    </section>

                    {{-- Modal de suppression du compte --}}
                    @if ($showDeleteAccountModal)
                        <div
                            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                            wire:click.self="$set('showDeleteAccountModal', false)"
                            x-data
                            @keydown.escape.window="$wire.set('showDeleteAccountModal', false)"
                        >
                            <div class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
                                <form wire:submit="deleteUser">
                                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                        <div class="sm:flex sm:items-start">
                                            <div class="mx-auto flex size-12 shrink-0 items-center justify-center rounded-full bg-rose-100 sm:mx-0 sm:size-10">
                                                <svg class="size-6 text-rose-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                                </svg>
                                            </div>
                                            <div class="mt-3 w-full text-center sm:mt-0 sm:ml-4 sm:text-left">
                                                <h3 class="text-base font-semibold text-slate-900">{{ __('Êtes-vous sûr de vouloir supprimer votre compte ?') }}</h3>
                                                <div class="mt-2">
                                                    <p class="text-sm text-slate-500">{{ __('Une fois votre compte supprimé, toutes ses données seront définitivement perdues. Veuillez entrer votre mot de passe pour confirmer.') }}</p>
                                                </div>
                                                <div class="mt-4">
                                                    <label class="auth-label">
                                                        <span>{{ __('Mot de passe') }}</span>
                                                        <input wire:model="deletePassword" type="password" required autocomplete="current-password" class="auth-input" />
                                                        @error('deletePassword') <p class="auth-error">{{ $message }}</p> @enderror
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="bg-slate-50 px-4 py-3 sm:flex sm:justify-end sm:gap-3 sm:px-6">
                                        <button
                                            type="button"
                                            wire:click="$set('showDeleteAccountModal', false)"
                                            class="mt-3 inline-flex w-full justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto"
                                        >
                                            {{ __('Annuler') }}
                                        </button>
                                        <button
                                            type="submit"
                                            class="inline-flex w-full justify-center rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-rose-500 sm:w-auto"
                                        >
                                            {{ __('Supprimer le compte') }}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif
                @endif

            </div>
        </main>

    </div>
</div>
