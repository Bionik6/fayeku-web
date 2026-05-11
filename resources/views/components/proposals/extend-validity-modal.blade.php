@props([
    'show' => false,
    'dateModel' => 'newValidUntil',
    'confirmAction' => 'confirmExtendValidity',
    'cancelAction' => 'closeExtendValidityModal',
    'minDate' => null,
    'title' => 'Prolonger la validité',
    'description' => 'Choisissez une nouvelle date de validité. Si le document était expiré, il repassera en "Envoyé".',
])

@if ($show)
    <div class="relative z-50" role="dialog" aria-modal="true" x-data @keydown.escape.window="$wire.{{ $cancelAction }}()">
        <div class="fixed inset-0 bg-slate-500/75" aria-hidden="true"></div>
        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 sm:items-center">
                <div class="relative overflow-hidden rounded-2xl bg-white text-left shadow-xl sm:w-full sm:max-w-md">
                    <button type="button" wire:click="{{ $cancelAction }}" class="absolute top-4 right-4 z-10 rounded-full border border-slate-200 bg-white p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700" aria-label="{{ __('Fermer') }}">
                        <flux:icon name="x-mark" class="size-5" />
                    </button>
                    <form wire:submit="{{ $confirmAction }}">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                            <div class="sm:flex sm:items-start">
                                <div class="mx-auto flex size-12 shrink-0 items-center justify-center rounded-full bg-amber-100 sm:mx-0 sm:size-10">
                                    <flux:icon name="calendar-days" class="size-6 text-amber-600" />
                                </div>
                                <div class="mt-3 sm:mt-0 sm:ml-4 sm:flex-1 pr-8 text-left">
                                    <h3 class="text-xl font-semibold text-slate-900">{{ __($title) }}</h3>
                                    <p class="mt-2 text-base text-slate-500">{{ __($description) }}</p>

                                    <div class="mt-4">
                                        <label for="extend-validity-{{ $dateModel }}" class="block text-sm font-medium text-slate-700">
                                            {{ __('Nouvelle date de validité') }}
                                        </label>
                                        <div
                                            wire:ignore
                                            x-data="{
                                                picker: null,
                                                init() {
                                                    this.picker = flatpickr(this.$refs.input, {
                                                        dateFormat: 'Y-m-d',
                                                        altInput: true,
                                                        altFormat: 'd/m/Y',
                                                        defaultDate: $wire.{{ $dateModel }},
                                                        @if ($minDate) minDate: @js($minDate), @endif
                                                        onChange: (dates, dateStr) => $wire.set('{{ $dateModel }}', dateStr),
                                                    });
                                                    this.$watch('$wire.{{ $dateModel }}', (val) => {
                                                        if (this.picker && val) this.picker.setDate(val, false);
                                                    });
                                                },
                                                destroy() { if (this.picker) this.picker.destroy(); }
                                            }"
                                        >
                                            <input id="extend-validity-{{ $dateModel }}" x-ref="input" type="text" readonly required
                                                   class="mt-2 block w-full cursor-pointer rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-base text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10" />
                                        </div>
                                        @error($dateModel)
                                            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-slate-50 px-4 py-3 sm:flex sm:justify-end sm:gap-3 sm:px-6">
                            <button
                                type="button"
                                wire:click="{{ $cancelAction }}"
                                class="mt-3 inline-flex w-full justify-center rounded-2xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto"
                            >
                                {{ __('Annuler') }}
                            </button>
                            <button
                                type="submit"
                                class="inline-flex w-full justify-center rounded-2xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-strong sm:w-auto"
                            >
                                {{ __('Prolonger') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif
