<?php

use Livewire\Attributes\On;
use Livewire\Component;
use Modules\Auth\Models\Company;
use Modules\PME\Clients\Models\Client;

new class extends Component {
    public ?Company $company = null;

    public bool $showModal = false;

    public string $clientName = '';

    public string $clientPhone = '';

    public string $clientPhoneCountry = 'SN';

    /** @var array<string, string> */
    public array $clientPhoneCountries = [];

    public string $clientEmail = '';

    public string $clientTaxId = '';

    public string $clientAddress = '';

    public function mount(?Company $company = null): void
    {
        $this->company = $company;
        $this->clientPhoneCountry = $company?->country_code ?? 'SN';
        $this->clientPhoneCountries = collect(config('fayeku.phone_countries'))
            ->map(fn ($c) => $c['label'])
            ->all();
    }

    #[On('open-create-client-modal')]
    public function openModal(): void
    {
        $this->resetValidation();
        $this->reset(['clientName', 'clientPhone', 'clientEmail', 'clientTaxId', 'clientAddress']);
        $this->clientPhoneCountry = $this->company?->country_code ?? 'SN';
        $this->showModal = true;
    }

    public function save(): void
    {
        abort_unless($this->company && auth()->user()->can('create', Client::class), 403);

        $validated = $this->validate([
            'clientName'    => ['required', 'string', 'max:255'],
            'clientPhone'   => ['required', 'string', 'max:30'],
            'clientEmail'   => ['nullable', 'email', 'max:255'],
            'clientTaxId'   => ['nullable', 'string', 'max:100'],
            'clientAddress' => ['nullable', 'string', 'max:500'],
        ], [
            'clientName.required'  => __('Le nom du client est requis.'),
            'clientPhone.required' => __('Le numéro de téléphone est requis.'),
            'clientEmail.email'    => __("L'adresse email doit être valide."),
        ]);

        $client = Client::query()->create([
            'company_id' => $this->company->id,
            'name'       => trim($validated['clientName']),
            'phone'      => $this->normalizePhone($validated['clientPhone']),
            'email'      => $this->emptyToNull($validated['clientEmail'] ?? ''),
            'tax_id'     => $this->emptyToNull($validated['clientTaxId'] ?? ''),
            'address'    => $this->emptyToNull($validated['clientAddress'] ?? ''),
        ]);

        $this->showModal = false;
        $this->dispatch('client-created', id: $client->id, name: $client->name);
    }

    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === '' || $digits === null) {
            return null;
        }

        if (str_starts_with($phone, '+')) {
            return '+'.$digits;
        }

        $prefix = preg_replace(
            '/\D+/',
            '',
            (string) config("fayeku.phone_countries.{$this->clientPhoneCountry}.prefix", '221')
        );

        if (str_starts_with($digits, $prefix)) {
            return '+'.$digits;
        }

        return '+'.$prefix.$digits;
    }

    private function emptyToNull(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}; ?>

<div>
@if ($showModal)
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        wire:click.self="$set('showModal', false)"
        x-data
        @keydown.escape.window="$wire.set('showModal', false)"
    >
        <div class="relative w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl">
            <form wire:submit="save">
                {{-- Header --}}
                <div class="flex items-start justify-between border-b border-slate-100 px-7 py-6">
                    <div>
                        <h2 class="text-lg font-semibold text-ink">{{ __('Nouveau client') }}</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ __('Ajoutez les informations de contact et les données business utiles à la facturation et au recouvrement.') }}
                        </p>
                    </div>
                    <button
                        type="button"
                        wire:click="$set('showModal', false)"
                        class="ml-4 shrink-0 rounded-full border border-slate-200 p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                    >
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- Body --}}
                <div class="px-7 py-6">
                    <div class="grid gap-5 md:grid-cols-2">
                        {{-- Nom --}}
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">
                                {{ __('Nom client ou Raison Sociale') }} <span class="text-rose-500">*</span>
                            </label>
                            <input
                                wire:model="clientName"
                                type="text"
                                required
                                autofocus
                                class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                            />
                            @error('clientName') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Téléphone --}}
                        <x-phone-input
                            :label="__('Téléphone / WhatsApp')"
                            country-name="clientPhoneCountry"
                            :country-value="$clientPhoneCountry"
                            country-model="clientPhoneCountry"
                            phone-name="clientPhone"
                            :phone-value="$clientPhone"
                            phone-model="clientPhone"
                            :countries="$clientPhoneCountries"
                            required
                        />
                        @error('clientPhone') <p class="-mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror

                        {{-- Email --}}
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ __('Email') }}</label>
                            <input
                                wire:model="clientEmail"
                                type="email"
                                placeholder="contact@client.sn"
                                class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                            />
                            @error('clientEmail') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Identifiant fiscal --}}
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ __('Identifiant fiscal') }}</label>
                            <input
                                wire:model="clientTaxId"
                                type="text"
                                placeholder="NINEA / RCCM / NCC"
                                class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                            />
                        </div>

                        {{-- Adresse --}}
                        <div class="md:col-span-2">
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ __('Adresse') }}</label>
                            <input
                                wire:model="clientAddress"
                                type="text"
                                placeholder="{{ __('Rue, quartier, ville…') }}"
                                class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                            />
                        </div>
                    </div>

                    <div class="mt-5 rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                        {{ __('Les coordonnées client serviront aussi aux relances WhatsApp, SMS et email selon le canal choisi.') }}
                    </div>
                </div>

                {{-- Footer --}}
                <div class="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50/50 px-7 py-4">
                    <button
                        type="button"
                        wire:click="$set('showModal', false)"
                        class="inline-flex items-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary"
                    >
                        {{ __('Annuler') }}
                    </button>
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-2xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-strong"
                    >
                        {{ __('Créer le client') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif
</div>
