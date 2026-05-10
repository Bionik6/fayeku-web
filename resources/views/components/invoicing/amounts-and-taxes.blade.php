@props(['hasTax', 'taxMode', 'customTaxRate'])

<section class="app-shell-panel p-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h3 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-700">{{ __('TVA') }}</h3>
            <p class="mt-1 text-sm text-slate-500">{{ __('Activez la TVA si elle doit être ajoutée à cette facture. Laissez désactivé pour une facture sans TVA.') }}</p>
        </div>
        <button
            type="button"
            wire:click="$toggle('hasTax')"
            class="relative flex h-7 w-12 shrink-0 items-center rounded-full transition
                {{ $hasTax ? 'bg-primary' : 'bg-slate-300' }}"
            aria-label="{{ __('Activer la TVA') }}"
        >
            <span class="absolute size-5 rounded-full bg-white shadow transition-all
                {{ $hasTax ? 'left-[1.4rem]' : 'left-1' }}"></span>
        </button>
    </div>

    @if ($hasTax)
        <div class="mt-5">
            <div class="mb-3 flex gap-6">
                <label class="flex cursor-pointer items-center gap-2">
                    <input type="radio" wire:model.live="taxMode" value="18"
                           class="relative size-4 appearance-none rounded-full border border-slate-300 bg-white before:absolute before:inset-1 before:rounded-full before:bg-white not-checked:before:hidden checked:border-primary checked:bg-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" />
                    <span class="text-sm text-slate-700">18 %</span>
                </label>
                <label class="flex cursor-pointer items-center gap-2">
                    <input type="radio" wire:model.live="taxMode" value="custom"
                           class="relative size-4 appearance-none rounded-full border border-slate-300 bg-white before:absolute before:inset-1 before:rounded-full before:bg-white not-checked:before:hidden checked:border-primary checked:bg-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" />
                    <span class="text-sm text-slate-700">{{ __('Taux personnalisé') }}</span>
                </label>
            </div>

            @if ($taxMode === 'custom')
                <div class="flex w-48 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm focus-within:border-primary/40 focus-within:ring-2 focus-within:ring-primary/10">
                    <input wire:model.live="customTaxRate" type="number" min="0" max="100"
                           placeholder="0"
                           @paste.prevent
                           class="min-w-0 flex-1 bg-transparent px-3 py-2.5 text-sm text-ink tabular-nums focus:outline-none"/>
                    <span class="flex shrink-0 items-center border-l border-slate-200 bg-slate-50/80 px-3 text-sm font-medium text-slate-600 select-none">%</span>
                </div>
            @endif
            <p class="mt-1.5 text-sm text-slate-500">{{ __('La TVA est calculée après application de la remise.') }}</p>
        </div>
    @endif
</section>
