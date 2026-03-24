<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Trésorerie')] #[Layout('layouts::pme')] class extends Component {}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- En-tête --}}
    <section class="app-shell-panel overflow-hidden">
        <div class="p-6">
            <p class="text-sm font-semibold uppercase tracking-[0.24em] text-teal">{{ __('Trésorerie') }}</p>
            <h2 class="mt-2 text-3xl font-semibold tracking-tight text-ink">{{ __('Trésorerie') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('Visualisez et anticipez les flux financiers de votre entreprise.') }}</p>
        </div>
    </section>

    {{-- Contenu à venir --}}
    <section class="app-shell-panel flex flex-col items-center justify-center p-16 text-center">
        <div class="flex size-14 items-center justify-center rounded-2xl bg-mist">
            <x-app.icon name="commissions" class="size-6 text-primary" />
        </div>
        <h3 class="mt-4 text-lg font-semibold text-ink">{{ __('Trésorerie — en cours de développement') }}</h3>
        <p class="mt-2 max-w-sm text-sm text-slate-500">
            {{ __('La visualisation de votre trésorerie et les prévisions financières seront disponibles prochainement.') }}
        </p>
    </section>

</div>
