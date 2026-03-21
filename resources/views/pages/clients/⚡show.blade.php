<?php

use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Auth\Models\Company;

new #[Title('Fiche client')] class extends Component {
    public Company $company;

    public function mount(Company $company): void
    {
        $this->company = $company;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
        <section class="app-shell-panel p-6">
            <div class="flex items-start gap-4">
                <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-mist text-lg font-bold text-primary">
                    {{ collect(explode(' ', $company->name))->map(fn ($w) => strtoupper($w[0] ?? ''))->take(2)->join('') }}
                </span>
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.24em] text-teal">{{ __('Fiche client') }}</p>
                    <h2 class="mt-1 text-3xl font-semibold tracking-tight text-ink">{{ $company->name }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $company->phone }}</p>
                </div>
            </div>
        </section>

        <section class="app-shell-panel p-6">
            <p class="text-sm text-slate-400">{{ __('La fiche détaillée de ce client sera disponible prochainement.') }}</p>
            <a href="{{ route('dashboard') }}" class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline">
                ← {{ __('Retour au dashboard') }}
            </a>
        </section>
</div>
