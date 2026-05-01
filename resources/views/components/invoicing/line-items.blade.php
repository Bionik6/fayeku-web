@props(['title', 'lines', 'currency'])

<section class="app-shell-panel p-6">
    <h3 class="mb-4 text-sm font-semibold uppercase tracking-[0.16em] text-slate-700">{{ $title }}</h3>
    @error('lines') <p class="mb-3 text-sm text-rose-600">{{ $message }}</p> @enderror

    <div class="space-y-5">
        @foreach ($lines as $index => $line)
            <div wire:key="line-{{ $index }}"
                 class="relative rounded-2xl border border-slate-200 bg-slate-50/30 p-5">
                {{-- Bouton supprimer positionné en haut à droite --}}
                @if (count($lines) > 1)
                    <div class="app-tooltip-wrapper absolute right-2 top-2">
                        <button
                                type="button"
                                wire:click="removeLine({{ $index }})"
                                class="rounded-xl border border-transparent p-2 text-slate-400 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600"
                        >
                            <svg class="size-4" fill="none"
                                 stroke="currentColor" stroke-width="1.5"
                                 viewBox="0 0 24 24">
                                <path stroke-linecap="round"
                                      stroke-linejoin="round"
                                      d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                            </svg>
                        </button>
                        <div class="app-tooltip">{{ __('Supprimer la ligne') }}</div>
                    </div>
                @endif

                <div class="space-y-4">
                    {{-- Row 1: Désignation --}}
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ __('Désignation') }}
                            <span class="text-rose-500">*</span></label>
                        <input wire:model.live.debounce.300ms="lines.{{ $index }}.description"
                               type="text"
                               placeholder="{{ __('Ex : Ciment, prestation…') }}"
                               class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"/>
                        @error("lines.{$index}.description") <p
                                class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Row 2: Qté / P.U. / Total --}}

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        {{-- Quantité --}}
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ __('Quantité') }}</label>
                            <input wire:model.live.debounce.300ms="lines.{{ $index }}.quantity"
                                   type="number" min="1"
                                   class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-ink tabular-nums focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"/>
                            @error("lines.{$index}.quantity") <p
                                    class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Prix unitaire --}}
                        <div
                                x-data="{
                                raw: {{ min((int) ($line['unit_price'] ?? 0), \App\Services\PME\CurrencyService::maxAmount($currency)) }},
                                formatted: '',
                                get noDecimals() { return $wire.currencyJs.decimals === 0; },
                                get maxRaw() { return $wire.currencyJs.maxAmount; },
                                formatNoDecimal(v) {
                                    return v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, $wire.currencyJs.thousandsSep);
                                },
                                clamp(v) { return Math.min(Math.max(v, 0), this.maxRaw); },
                                onInput(e) {
                                    if (this.noDecimals) {
                                        this.raw = this.clamp(parseInt(e.target.value.replace(/\D/g, '')) || 0);
                                        this.formatted = this.formatNoDecimal(this.raw);
                                        e.target.value = this.formatted;
                                    } else {
                                        let v = e.target.value.replace(/[^\d.]/g, '');
                                        this.raw = this.clamp(Math.round(parseFloat(v || '0') * Math.pow(10, $wire.currencyJs.decimals)));
                                    }
                                    $wire.set('lines.{{ $index }}.unit_price', this.raw);
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
                                }
                            }"
                        >
                            <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ __('Prix unitaire') }}
                                (<span x-text="$wire.currencyJs.label"></span>)</label>
                            <input
                                    type="text"
                                    :inputmode="noDecimals ? 'numeric' : 'decimal'"
                                    :value="formatted"
                                    @input="onInput($event)"
                                    placeholder="0"
                                    class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-ink tabular-nums focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                            />
                        </div>

                        {{-- Total --}}
                        <div x-data="{
                                 get c() { return $wire.currencyJs; },
                                 get total() {
                                     let qty = parseInt($wire.lines[{{ $index }}]?.quantity) || 0;
                                     let price = parseInt($wire.lines[{{ $index }}]?.unit_price) || 0;
                                     return qty * price;
                                 },
                                 get display() {
                                     let t = this.total;
                                     if (this.c.decimals > 0) {
                                         return (t / Math.pow(10, this.c.decimals)).toFixed(this.c.decimals);
                                     }
                                     return t.toString().replace(/\B(?=(\d{3})+(?!\d))/g, this.c.thousandsSep);
                                 }
                             }"
                        >
                            <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ __('Total') }}</label>
                            <input type="text" disabled
                                   :value="display + ' ' + $wire.currencyJs.label"
                                   tabindex="-1"
                                   class="w-full rounded-2xl border border-slate-200 bg-slate-100 px-4 py-3 text-right text-sm font-bold tabular-nums text-ink"/>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-5">
        <button type="button" wire:click="addLine"
                class="inline-flex w-full items-center justify-center rounded-2xl border border-dashed border-slate-300 py-3 text-sm font-medium text-primary transition hover:border-primary/40 hover:bg-primary/5">
            <svg class="mr-2 size-4" fill="none" stroke="currentColor"
                 stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            {{ __('Ajouter une ligne') }}
        </button>
    </div>
</section>
