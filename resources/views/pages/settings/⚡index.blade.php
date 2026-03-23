<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Auth\Models\Company;

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

    // ─── Notifications (UI only V1) ─────────────────────────────────────
    public bool $notifyOverdueApp = true;
    public bool $notifyOverdueEmail = true;
    public bool $notifyInactiveApp = true;
    public bool $notifyInactiveEmail = false;
    public bool $notifyNewSmeApp = true;
    public bool $notifyNewSmeEmail = true;
    public bool $notifyExportApp = true;
    public bool $notifyExportEmail = false;
    public bool $notifyCommissionApp = true;
    public bool $notifyCommissionEmail = true;
    public bool $notifyWeeklyApp = false;
    public bool $notifyWeeklyEmail = true;

    // ─── Export comptable (UI only V1) ──────────────────────────────────
    public string $exportFormat = 'sage100';
    public string $exportPeriod = 'current_month';
    public string $exportNaming = 'fayeku-export-{periode}-{format}';

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
            $this->firmPhone = $firm->phone ?? '';
            $this->firmAddress = $firm->address ?? '';
            $this->firmCity = $firm->city ?? '';
            $this->firmCountry = $firm->country_code ?? 'SN';
            $this->firmNinea = $firm->ninea ?? '';
            $this->firmRccm = $firm->rccm ?? '';
        }
    }

    #[Computed]
    public function firm(): ?Company
    {
        return Auth::user()->companies()->where('type', 'accountant_firm')->first();
    }

    #[Computed]
    public function subscription(): ?object
    {
        return $this->firm?->subscription;
    }

    #[Computed]
    public function teamMembers(): array
    {
        if (! $this->firm) {
            return [];
        }

        return $this->firm->users()->get()->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->full_name,
            'email' => $u->email,
            'phone' => $u->phone,
            'role' => $u->pivot->role,
            'initials' => $u->initials(),
        ])->toArray();
    }

    public function setSection(string $section): void
    {
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
                'phone' => $validated['firmPhone'],
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

    public function saveNotifications(): void
    {
        session()->flash('notifications-saved', true);
    }

    public function saveExportSettings(): void
    {
        session()->flash('export-saved', true);
    }

    #[Computed]
    public function exportFilePreview(): string
    {
        $period = match ($this->exportPeriod) {
            'current_month' => now()->locale('fr_FR')->isoFormat('MMMM-YYYY'),
            'previous_month' => now()->subMonth()->locale('fr_FR')->isoFormat('MMMM-YYYY'),
            default => 'selection',
        };

        $format = $this->exportFormat;

        return str_replace(['{periode}', '{format}'], [$period, $format], $this->exportNaming).'.csv';
    }
}; ?>

<div class="settings-page">
    @php
        $settingsPhoneCountries = [
            'SN' => 'SEN (+221)',
            'CI' => 'CIV (+225)',
            'ML' => 'MLI (+223)',
            'BF' => 'BFA (+226)',
            'GN' => 'GIN (+224)',
            'TG' => 'TGO (+228)',
            'BJ' => 'BEN (+229)',
            'NE' => 'NER (+227)',
            'CM' => 'CMR (+237)',
            'GA' => 'GAB (+241)',
        ];
    @endphp

    {{-- ─── Header ──────────────────────────────────────────────────────── --}}
    <section class="app-shell-panel px-8 py-7">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary">{{ __('Paramètres') }}</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-ink">{{ __('Paramètres') }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ __('Gérez les informations de votre cabinet, vos préférences et vos paramètres comptables.') }}</p>
    </section>

    {{-- ─── Layout: sidebar + content ───────────────────────────────────── --}}
    <div class="mt-6 lg:flex lg:gap-x-8">

        {{-- Sidebar interne --}}
        <aside class="flex overflow-x-auto pb-4 lg:block lg:w-64 lg:flex-none lg:pb-0">
            <nav class="flex-none">
                <ul role="list" class="flex gap-x-3 gap-y-1 whitespace-nowrap lg:flex-col">
                    @php
                        $sections = [
                            ['key' => 'profile', 'label' => __('Profil du cabinet'), 'icon' => 'user-circle'],
                            ['key' => 'account', 'label' => __('Compte & sécurité'), 'icon' => 'shield-check'],
                            ['key' => 'notifications', 'label' => __('Notifications'), 'icon' => 'bell'],
                            ['key' => 'export', 'label' => __('Export comptable'), 'icon' => 'document-arrow-down'],
                            ['key' => 'billing', 'label' => __('Facturation & abonnement'), 'icon' => 'credit-card'],
                        ];
                    @endphp
                    @foreach ($sections as $section)
                        <li wire:key="nav-{{ $section['key'] }}">
                            <button
                                type="button"
                                wire:click="setSection('{{ $section['key'] }}')"
                                @class([
                                    'group flex w-full gap-x-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition',
                                    'bg-mist text-primary' => $activeSection === $section['key'],
                                    'text-slate-600 hover:bg-slate-50 hover:text-primary' => $activeSection !== $section['key'],
                                ])
                            >
                                <flux:icon :name="$section['icon']" variant="outline" @class([
                                    'size-5 shrink-0',
                                    'text-primary' => $activeSection === $section['key'],
                                    'text-slate-400 group-hover:text-primary' => $activeSection !== $section['key'],
                                ]) />
                                {{ $section['label'] }}
                            </button>
                        </li>
                    @endforeach
                </ul>
            </nav>
        </aside>

        {{-- Contenu principal --}}
        <main class="mt-4 min-w-0 flex-1 lg:mt-0">

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
                                <label class="auth-label">
                                    <span>{{ __('Adresse e-mail professionnelle') }}</span>
                                    <input wire:model="firmEmail" type="email" class="auth-input" />
                                    @error('firmEmail') <p class="auth-error">{{ $message }}</p> @enderror
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
                                @error('firmPhone') <p class="auth-error sm:col-span-2">{{ $message }}</p> @enderror
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
                                    <select wire:model="firmCountry" class="auth-select">
                                        <option value="SN">{{ __('Sénégal') }}</option>
                                        <option value="CI">{{ __('Côte d\'Ivoire') }}</option>
                                        <option value="ML">{{ __('Mali') }}</option>
                                        <option value="BF">{{ __('Burkina Faso') }}</option>
                                        <option value="GN">{{ __('Guinée') }}</option>
                                        <option value="TG">{{ __('Togo') }}</option>
                                        <option value="BJ">{{ __('Bénin') }}</option>
                                        <option value="NE">{{ __('Niger') }}</option>
                                        <option value="CM">{{ __('Cameroun') }}</option>
                                        <option value="GA">{{ __('Gabon') }}</option>
                                    </select>
                                    @error('firmCountry') <p class="auth-error">{{ $message }}</p> @enderror
                                </label>
                            </div>

                            {{-- Informations légales --}}
                            <div>
                                <h3 class="text-sm font-semibold text-ink">{{ __('Informations légales') }}</h3>
                                <p class="mt-1 text-xs text-slate-500">{{ __('Identifiants fiscaux et registre du commerce.') }}</p>
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

                    {{-- Double authentification --}}
                    <section class="app-shell-panel px-6 py-6">
                        <h2 class="text-base font-bold text-ink">{{ __('Authentification à deux facteurs') }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ __('Ajoutez une couche de sécurité supplémentaire à votre compte.') }}</p>

                        <div class="mt-6">
                            @if (Auth::user()->two_factor_confirmed_at)
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">{{ __('Activée') }}</span>
                                    <span class="text-sm text-slate-500">{{ __('L\'authentification à deux facteurs est active sur votre compte.') }}</span>
                                </div>
                            @else
                                <flux:modal.trigger name="two-factor-setup-modal">
                                    <button
                                        type="button"
                                        wire:click="$dispatch('start-two-factor-setup')"
                                        class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                                    >
                                        <flux:icon name="shield-check" variant="outline" class="mr-2 size-4" />
                                        {{ __('Activer la double authentification') }}
                                    </button>
                                </flux:modal.trigger>
                            @endif
                        </div>

                        <livewire:pages::settings.two-factor-setup-modal :requires-confirmation="true" />
                    </section>

                    {{-- Supprimer le compte --}}
                    <section class="app-shell-panel px-6 py-6">
                        <livewire:pages::settings.delete-user-form />
                    </section>
                </div>

            {{-- ═══ Section: Notifications ═══════════════════════════════ --}}
            @elseif ($activeSection === 'notifications')
                <div class="space-y-6">
                    <section class="app-shell-panel px-6 py-6">
                        <h2 class="text-base font-bold text-ink">{{ __('Notifications') }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ __('Choisissez les alertes et résumés que vous souhaitez recevoir.') }}</p>

                        @if (session('notifications-saved'))
                            <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                                {{ __('Préférences de notifications enregistrées.') }}
                            </div>
                        @endif

                        {{-- En-tête canaux --}}
                        <div class="mt-6 hidden items-center justify-end gap-8 pr-1 sm:flex">
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('App') }}</span>
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Email') }}</span>
                        </div>

                        {{-- Groupe 1: Alertes métier --}}
                        <div class="mt-4">
                            <h3 class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">{{ __('Alertes métier') }}</h3>
                            <div class="mt-3 divide-y divide-slate-100">
                                @php
                                    $businessAlerts = [
                                        ['label' => __('Impayés critiques'), 'desc' => __('Recevoir une notification lorsqu\'un impayé devient critique.'), 'appModel' => 'notifyOverdueApp', 'emailModel' => 'notifyOverdueEmail'],
                                        ['label' => __('Clients à surveiller'), 'desc' => __('Recevoir une notification lorsqu\'un client devient inactif ou présente un risque.'), 'appModel' => 'notifyInactiveApp', 'emailModel' => 'notifyInactiveEmail'],
                                        ['label' => __('Nouvelles inscriptions PME'), 'desc' => __('Recevoir une notification lorsqu\'une PME rejoint Fayeku via votre cabinet.'), 'appModel' => 'notifyNewSmeApp', 'emailModel' => 'notifyNewSmeEmail'],
                                    ];
                                @endphp
                                @foreach ($businessAlerts as $alert)
                                    <div class="flex items-center justify-between gap-4 py-4" wire:key="notif-{{ $alert['appModel'] }}">
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-ink">{{ $alert['label'] }}</p>
                                            <p class="mt-0.5 text-xs text-slate-500">{{ $alert['desc'] }}</p>
                                        </div>
                                        <div class="flex shrink-0 items-center gap-6">
                                            <flux:switch wire:model.live="{{ $alert['appModel'] }}" />
                                            <flux:switch wire:model.live="{{ $alert['emailModel'] }}" />
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Groupe 2: Activité --}}
                        <div class="mt-8">
                            <h3 class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">{{ __('Activité') }}</h3>
                            <div class="mt-3 divide-y divide-slate-100">
                                @php
                                    $activityAlerts = [
                                        ['label' => __('Exports terminés'), 'desc' => __('Recevoir une notification lorsqu\'un export est prêt à être téléchargé.'), 'appModel' => 'notifyExportApp', 'emailModel' => 'notifyExportEmail'],
                                        ['label' => __('Commissions versées'), 'desc' => __('Recevoir une notification lorsqu\'un versement partenaire est effectué.'), 'appModel' => 'notifyCommissionApp', 'emailModel' => 'notifyCommissionEmail'],
                                        ['label' => __('Résumé hebdomadaire'), 'desc' => __('Recevoir un résumé hebdomadaire de votre portefeuille et des alertes.'), 'appModel' => 'notifyWeeklyApp', 'emailModel' => 'notifyWeeklyEmail'],
                                    ];
                                @endphp
                                @foreach ($activityAlerts as $alert)
                                    <div class="flex items-center justify-between gap-4 py-4" wire:key="notif-{{ $alert['appModel'] }}">
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-ink">{{ $alert['label'] }}</p>
                                            <p class="mt-0.5 text-xs text-slate-500">{{ $alert['desc'] }}</p>
                                        </div>
                                        <div class="flex shrink-0 items-center gap-6">
                                            <flux:switch wire:model.live="{{ $alert['appModel'] }}" />
                                            <flux:switch wire:model.live="{{ $alert['emailModel'] }}" />
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="mt-8 flex items-center gap-4 border-t border-slate-100 pt-6">
                            <button type="button" wire:click="saveNotifications" class="inline-flex items-center rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90">
                                {{ __('Enregistrer les préférences') }}
                            </button>
                        </div>
                    </section>
                </div>

            {{-- ═══ Section: Export comptable ═════════════════════════════ --}}
            @elseif ($activeSection === 'export')
                <div class="space-y-6">
                    <section class="app-shell-panel px-6 py-6">
                        <h2 class="text-base font-bold text-ink">{{ __('Export comptable') }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ __('Définissez les paramètres utilisés pour vos exports comptables.') }}</p>

                        @if (session('export-saved'))
                            <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                                {{ __('Paramètres d\'export enregistrés avec succès.') }}
                            </div>
                        @endif

                        <div class="mt-6 space-y-6">
                            <div class="grid gap-6 sm:grid-cols-2">
                                <label class="auth-label">
                                    <span>{{ __('Format par défaut') }}</span>
                                    <select wire:model.live="exportFormat" class="auth-select">
                                        <option value="sage100">Sage 100</option>
                                        <option value="ebp">EBP</option>
                                        <option value="excel">Excel</option>
                                    </select>
                                </label>

                                <label class="auth-label">
                                    <span>{{ __('Période par défaut') }}</span>
                                    <select wire:model.live="exportPeriod" class="auth-select">
                                        <option value="current_month">{{ __('Mois en cours') }}</option>
                                        <option value="previous_month">{{ __('Mois précédent') }}</option>
                                        <option value="manual">{{ __('Sélection manuelle') }}</option>
                                    </select>
                                </label>

                                <label class="auth-label sm:col-span-2">
                                    <span>{{ __('Nommage des fichiers exportés') }}</span>
                                    <input wire:model.live="exportNaming" type="text" placeholder="fayeku-export-{periode}-{format}" class="auth-input" />
                                </label>
                            </div>

                            {{-- Aperçu --}}
                            <div class="rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Aperçu du nom de fichier') }}</p>
                                <p class="mt-1 font-mono text-sm text-ink">{{ $this->exportFilePreview }}</p>
                            </div>
                        </div>

                        <div class="mt-8 flex items-center gap-4 border-t border-slate-100 pt-6">
                            <button type="button" wire:click="saveExportSettings" class="inline-flex items-center rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90">
                                {{ __('Enregistrer les paramètres d\'export') }}
                            </button>
                        </div>
                    </section>
                </div>

            {{-- ═══ Section: Facturation & abonnement ════════════════════ --}}
            @elseif ($activeSection === 'billing')
                <div class="space-y-6">
                    <section class="app-shell-panel px-6 py-6">
                        <h2 class="text-base font-bold text-ink">{{ __('Facturation & abonnement') }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ __('Consultez votre offre actuelle et vos informations de facturation.') }}</p>

                        {{-- Carte offre actuelle --}}
                        <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50/80 p-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Offre actuelle') }}</p>
                                    <p class="mt-1 text-lg font-bold text-ink">
                                        {{ ucfirst($this->firm?->plan ?? 'basique') }}
                                    </p>
                                </div>
                                @php
                                    $plan = $this->firm?->plan ?? 'basique';
                                    $badgeClass = match ($plan) {
                                        'gold' => 'bg-amber-50 text-amber-800 ring-amber-200',
                                        'essentiel' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
                                        default => 'bg-slate-100 text-slate-700 ring-slate-200',
                                    };
                                @endphp
                                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset {{ $badgeClass }}">
                                    {{ ucfirst($plan) }}
                                </span>
                            </div>
                        </div>

                        {{-- Détails --}}
                        <dl class="mt-6 divide-y divide-slate-100 text-sm">
                            <div class="flex justify-between py-4">
                                <dt class="font-medium text-slate-500">{{ __('Offre') }}</dt>
                                <dd class="font-semibold text-ink">{{ ucfirst($this->firm?->plan ?? 'basique') }}</dd>
                            </div>
                            @if ($this->subscription)
                                <div class="flex justify-between py-4">
                                    <dt class="font-medium text-slate-500">{{ __('Abonnement mensuel') }}</dt>
                                    <dd class="font-semibold text-ink">{{ number_format($this->subscription->price_paid, 0, ',', ' ') }} FCFA</dd>
                                </div>
                                <div class="flex justify-between py-4">
                                    <dt class="font-medium text-slate-500">{{ __('Statut') }}</dt>
                                    <dd>
                                        @if ($this->subscription->status === 'active')
                                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">{{ __('Actif') }}</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ ucfirst($this->subscription->status) }}</span>
                                        @endif
                                    </dd>
                                </div>
                                @if ($this->subscription->current_period_end)
                                    <div class="flex justify-between py-4">
                                        <dt class="font-medium text-slate-500">{{ __('Prochaine échéance') }}</dt>
                                        <dd class="font-semibold text-ink">{{ $this->subscription->current_period_end->locale('fr_FR')->isoFormat('D MMMM YYYY') }}</dd>
                                    </div>
                                @endif
                            @else
                                <div class="flex justify-between py-4">
                                    <dt class="font-medium text-slate-500">{{ __('Statut') }}</dt>
                                    <dd class="text-sm text-slate-500">{{ __('Aucun abonnement actif') }}</dd>
                                </div>
                            @endif
                            <div class="flex justify-between py-4">
                                <dt class="font-medium text-slate-500">{{ __('Moyen de paiement') }}</dt>
                                <dd class="font-semibold text-ink">Wave</dd>
                            </div>
                        </dl>

                        {{-- Équipe --}}
                        @if (count($this->teamMembers) > 0)
                            <div class="mt-8 border-t border-slate-100 pt-6">
                                <h3 class="text-sm font-semibold text-ink">{{ __('Équipe du cabinet') }}</h3>
                                <p class="mt-1 text-xs text-slate-500">{{ __('Membres autorisés à accéder à l\'espace du cabinet.') }}</p>

                                <ul class="mt-4 divide-y divide-slate-100">
                                    @foreach ($this->teamMembers as $member)
                                        <li class="flex items-center gap-4 py-3" wire:key="member-{{ $member['id'] }}">
                                            <div class="flex size-9 items-center justify-center rounded-xl bg-mist text-xs font-bold text-primary">
                                                {{ $member['initials'] }}
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-medium text-ink">{{ $member['name'] }}</p>
                                                <p class="text-xs text-slate-500">{{ $member['phone'] }}</p>
                                            </div>
                                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                                                {{ ucfirst($member['role']) }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </section>
                </div>
            @endif

        </main>
    </div>
</div>
