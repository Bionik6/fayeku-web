<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Auth\Models\Company;

new #[Title('Tableau de bord')] #[Layout('layouts::pme')] class extends Component {
    public ?Company $company = null;

    public string $currentMonth = '';

    public function mount(): void
    {
        $this->currentMonth = ucfirst(now()->locale('fr_FR')->translatedFormat('F Y'));
        $this->company = auth()->user()->companies()->where('type', 'sme')->first();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- En-tête --}}
    <section class="app-shell-panel overflow-hidden">
        <div class="p-6">
            <p class="text-sm font-semibold uppercase tracking-[0.24em] text-teal">{{ $currentMonth }}</p>
            <h2 class="mt-2 text-3xl font-semibold tracking-tight text-ink">
                {{ __('Bonjour,') }} {{ auth()->user()->first_name }} 👋
            </h2>
            <p class="mt-1 text-sm text-slate-500">
                {{ __('Voici un aperçu de votre activité pour') }} {{ $company?->name ?? __('votre entreprise') }}.
            </p>
        </div>
    </section>

    {{-- Statistiques --}}
    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">

        <article class="app-shell-panel p-6">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">{{ __('Chiffre d\'affaires') }}</p>
            <p class="mt-2 text-3xl font-bold tracking-tight text-ink">—</p>
            <p class="mt-1 text-sm text-slate-500">{{ __('Ce mois-ci') }}</p>
        </article>

        <article class="app-shell-panel p-6">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">{{ __('Factures en attente') }}</p>
            <p class="mt-2 text-3xl font-bold tracking-tight text-ink">—</p>
            <p class="mt-1 text-sm text-slate-500">{{ __('Montant impayé') }}</p>
        </article>

        <article class="app-shell-panel p-6">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">{{ __('Clients actifs') }}</p>
            <p class="mt-2 text-3xl font-bold tracking-tight text-ink">—</p>
            <p class="mt-1 text-sm text-slate-500">{{ __('Total clients') }}</p>
        </article>

        <article class="app-shell-panel p-6">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">{{ __('Solde trésorerie') }}</p>
            <p class="mt-2 text-3xl font-bold tracking-tight text-ink">—</p>
            <p class="mt-1 text-sm text-slate-500">{{ __('Estimation') }}</p>
        </article>

    </section>

    {{-- Contenu à venir --}}
    <section class="app-shell-panel flex flex-col items-center justify-center p-16 text-center">
        <div class="flex size-14 items-center justify-center rounded-2xl bg-mist">
            <x-app.icon name="dashboard" class="size-6 text-primary" />
        </div>
        <h3 class="mt-4 text-lg font-semibold text-ink">{{ __('Dashboard PME en cours de développement') }}</h3>
        <p class="mt-2 max-w-sm text-sm text-slate-500">
            {{ __('Les statistiques détaillées, graphiques et indicateurs clés seront disponibles prochainement.') }}
        </p>
    </section>

</div>
