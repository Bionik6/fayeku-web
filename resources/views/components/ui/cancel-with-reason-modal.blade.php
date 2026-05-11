@props([
    'show' => false,
    'title' => 'Annuler le document',
    'description' => 'L\'annulation est définitive. Le document conserve sa référence et reste consultable, mais il sort du cycle de vie commercial.',
    'reasonModel' => 'cancelReason',
    'confirmAction' => 'confirmCancel',
    'cancelAction' => 'closeCancelModal',
    'confirmLabel' => 'Annuler le document',
])

@if ($show)
    <div class="relative z-50" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-500/75 transition-opacity" aria-hidden="true"></div>
        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl sm:w-full sm:max-w-lg">
                    <form wire:submit="{{ $confirmAction }}">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                            <div class="sm:flex sm:items-start">
                                <div class="mx-auto flex size-12 shrink-0 items-center justify-center rounded-full bg-rose-100 sm:mx-0 sm:size-10">
                                    <flux:icon name="exclamation-triangle" class="size-6 text-rose-600" />
                                </div>
                                <div class="mt-3 sm:mt-0 sm:ml-4 sm:flex-1 text-left">
                                    <h3 class="text-base font-semibold text-slate-900">{{ __($title) }}</h3>
                                    <p class="mt-2 text-sm text-slate-500">{{ __($description) }}</p>

                                    <div class="mt-4">
                                        <label for="cancel-reason-{{ $reasonModel }}" class="block text-sm font-medium text-slate-700">
                                            {{ __('Motif de l\'annulation') }} <span class="text-rose-500">*</span>
                                        </label>
                                        <textarea
                                            id="cancel-reason-{{ $reasonModel }}"
                                            wire:model="{{ $reasonModel }}"
                                            rows="3"
                                            required
                                            class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-ink shadow-sm focus:border-primary focus:ring focus:ring-primary/20"
                                            placeholder="{{ __('Ex. : commande annulée par le client') }}"
                                        ></textarea>
                                        @error($reasonModel)
                                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-slate-50 px-4 py-3 sm:flex sm:justify-end sm:gap-3 sm:px-6">
                            <button
                                type="button"
                                wire:click="{{ $cancelAction }}"
                                class="mt-3 inline-flex w-full justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto"
                            >
                                {{ __('Garder le document') }}
                            </button>
                            <button
                                type="submit"
                                class="inline-flex w-full justify-center rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-rose-500 sm:w-auto"
                            >
                                {{ __($confirmLabel) }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif
