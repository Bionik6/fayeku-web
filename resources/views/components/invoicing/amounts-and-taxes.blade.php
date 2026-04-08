@props(['taxMode', 'customTaxRate', 'discountType', 'discount', 'currency', 'currencyLabel'])

<section class="app-shell-panel p-6">
    <h3 class="mb-1 text-sm font-semibold uppercase tracking-[0.16em] text-slate-700">{{ __('Montants et taxes') }}</h3>
    <p class="mb-5 text-sm text-slate-600">{{ __('Ajustez la remise et la TVA. Le total se met à jour automatiquement.') }}</p>

    <div class="space-y-6">
        <p class="text-sm font-semibold uppercase tracking-widest text-slate-600">{{ __('Ajustements') }}</p>

        {{-- TVA --}}
        <div>
            <label class="mb-2 block text-sm font-medium text-slate-800">{{ __('TVA') }}</label>

            <div class="mb-3 flex gap-6">
                <label class="flex cursor-pointer items-center gap-2">
                    <input type="radio" wire:model.live="taxMode" value="0"
                           class="relative size-4 appearance-none rounded-full border border-slate-300 bg-white before:absolute before:inset-1 before:rounded-full before:bg-white not-checked:before:hidden checked:border-primary checked:bg-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" />
                    <span class="text-sm text-slate-700">{{ __('Sans TVA') }}</span>
                </label>
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

        {{-- Remise globale --}}
        <div>
            <label class="mb-2 block text-sm font-medium text-slate-800">{{ __('Remise globale') }}</label>

            <div class="mb-3 flex gap-6">
                <label class="flex cursor-pointer items-center gap-2">
                    <input type="radio" wire:model.live="discountType" value="percent"
                           class="relative size-4 appearance-none rounded-full border border-slate-300 bg-white before:absolute before:inset-1 before:rounded-full before:bg-white not-checked:before:hidden checked:border-primary checked:bg-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" />
                    <span class="text-sm text-slate-700">{{ __('Pourcentage') }}</span>
                </label>
                <label class="flex cursor-pointer items-center gap-2">
                    <input type="radio" wire:model.live="discountType" value="fixed"
                           class="relative size-4 appearance-none rounded-full border border-slate-300 bg-white before:absolute before:inset-1 before:rounded-full before:bg-white not-checked:before:hidden checked:border-primary checked:bg-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" />
                    <span class="text-sm text-slate-700">{{ __('Montant fixe') }}</span>
                </label>
            </div>

            <div class="flex w-48 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm focus-within:border-primary/40 focus-within:ring-2 focus-within:ring-primary/10">
                @if ($discountType === 'percent')
                    <input wire:model.live="discount" type="number" min="0" max="100"
                           placeholder="0"
                           @paste.prevent
                           class="min-w-0 flex-1 bg-transparent px-3 py-2.5 text-sm text-ink tabular-nums focus:outline-none"/>
                @else
                    <div class="min-w-0 flex-1"
                         x-data="{
                             raw: {{ min((int) ($discount ?? 0), \Modules\PME\Invoicing\Services\CurrencyService::maxAmount($currency)) }},
                             formatted: '',
                             get noDecimals() { return $wire.currencyJs.decimals === 0; },
                             get maxRaw() { return $wire.currencyJs.maxAmount; },
                             clamp(v) { return Math.min(Math.max(v, 0), this.maxRaw); },
                             formatNoDecimal(v) {
                                 return v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, $wire.currencyJs.thousandsSep);
                             },
                             onInput(e) {
                                 if (this.noDecimals) {
                                     this.raw = this.clamp(parseInt(e.target.value.replace(/\D/g, '')) || 0);
                                     this.formatted = this.raw > 0 ? this.formatNoDecimal(this.raw) : '';
                                     e.target.value = this.formatted;
                                 } else {
                                     let v = e.target.value.replace(/[^\d.,]/g, '').replace(',', '.');
                                     this.raw = this.clamp(Math.round(parseFloat(v || '0') * Math.pow(10, $wire.currencyJs.decimals)));
                                 }
                                 $wire.set('discount', this.raw);
                             },
                             init() {
                                 if (this.noDecimals) {
                                     this.formatted = this.raw > 0 ? this.formatNoDecimal(this.raw) : '';
                                 } else {
                                     this.formatted = this.raw > 0 ? (this.raw / Math.pow(10, $wire.currencyJs.decimals)).toFixed($wire.currencyJs.decimals) : '';
                                 }
                                 this.$watch('$wire.currencyJs', () => {
                                     if (this.noDecimals) {
                                         this.formatted = this.raw > 0 ? this.formatNoDecimal(this.raw) : '';
                                     } else {
                                         this.formatted = this.raw > 0 ? (this.raw / Math.pow(10, $wire.currencyJs.decimals)).toFixed($wire.currencyJs.decimals) : '';
                                     }
                                 });
                                 this.$watch('$wire.discount', (val) => {
                                     if (!val) { this.raw = 0; this.formatted = ''; }
                                 });
                             }
                         }"
                    >
                        <input type="text"
                               :value="formatted"
                               :inputmode="noDecimals ? 'numeric' : 'decimal'"
                               @input="onInput($event)"
                               placeholder="0"
                               class="w-full bg-transparent px-3 py-2.5 text-sm text-ink tabular-nums focus:outline-none"/>
                    </div>
                @endif
                <span class="flex shrink-0 items-center border-l border-slate-200 bg-slate-50/80 px-3 text-sm font-medium text-slate-600 select-none whitespace-nowrap">
                    {{ $discountType === 'percent' ? '%' : $currencyLabel }}
                </span>
            </div>
            <p class="mt-1.5 text-sm text-slate-500">
                {{ $discountType === 'percent' ? __('Appliquée sur le sous-total HT') : __('Montant déduit du sous-total HT') }}
            </p>
        </div>
    </div>
</section>
