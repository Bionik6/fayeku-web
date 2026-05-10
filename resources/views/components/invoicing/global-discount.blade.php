@props(['hasDiscount', 'discountType', 'discount', 'currency', 'currencyLabel'])

<section class="app-shell-panel p-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h3 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-700">{{ __('Remise globale') }}</h3>
            <p class="mt-1 text-sm text-slate-500">{{ __('Appliquez une réduction sur l\'ensemble de la facture, en pourcentage ou en montant fixe.') }}</p>
        </div>
        <button
            type="button"
            wire:click="$toggle('hasDiscount')"
            class="relative flex h-7 w-12 shrink-0 items-center rounded-full transition
                {{ $hasDiscount ? 'bg-primary' : 'bg-slate-300' }}"
            aria-label="{{ __('Activer la remise') }}"
        >
            <span class="absolute size-5 rounded-full bg-white shadow transition-all
                {{ $hasDiscount ? 'left-[1.4rem]' : 'left-1' }}"></span>
        </button>
    </div>

    @if ($hasDiscount)
        <div class="mt-5">
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
                             raw: {{ min((int) ($discount ?? 0), \App\Services\PME\CurrencyService::maxAmount($currency)) }},
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
                @if ($discountType === 'percent')
                    <span wire:key="discount-suffix-percent" class="flex shrink-0 items-center border-l border-slate-200 bg-slate-50/80 px-3 text-sm font-medium text-slate-600 select-none whitespace-nowrap">%</span>
                @else
                    <span wire:key="discount-suffix-fixed" class="flex shrink-0 items-center border-l border-slate-200 bg-slate-50/80 px-3 text-sm font-medium text-slate-600 select-none whitespace-nowrap">{{ $currencyLabel }}</span>
                @endif
            </div>
            <p class="mt-1.5 text-sm text-slate-500">
                {{ $discountType === 'percent' ? __('Appliquée sur le sous-total HT') : __('Montant déduit du sous-total HT') }}
            </p>
        </div>
    @endif
</section>
