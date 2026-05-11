@props([
    'show' => false,
    'reference' => '',
    'strategyModel' => 'deleteDraftStrategy',
    'confirmAction' => 'confirmDeleteDraft',
    'cancelAction' => 'closeDeleteDraftModal',
])

@if ($show)
    <div class="relative z-50" role="dialog" aria-modal="true" x-data @keydown.escape.window="$wire.{{ $cancelAction }}()">
        <div class="fixed inset-0 bg-slate-500/75" aria-hidden="true"></div>
        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 sm:items-center">
                <div class="relative overflow-hidden rounded-2xl bg-white text-left shadow-xl sm:w-full sm:max-w-lg">
                    <button type="button" wire:click="{{ $cancelAction }}" class="absolute top-4 right-4 z-10 rounded-full border border-slate-200 bg-white p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700" aria-label="{{ __('Fermer') }}">
                        <flux:icon name="x-mark" class="size-5" />
                    </button>
                    <form wire:submit="{{ $confirmAction }}">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                            <div class="sm:flex sm:items-start">
                                <div class="mx-auto flex size-12 shrink-0 items-center justify-center rounded-full bg-rose-100 sm:mx-0 sm:size-10">
                                    <flux:icon name="trash" class="size-6 text-rose-600" />
                                </div>
                                <div class="mt-3 sm:mt-0 sm:ml-4 sm:flex-1 pr-8 text-left">
                                    <h3 class="text-xl font-semibold text-slate-900">{{ __('Supprimer ce brouillon') }}</h3>
                                    <p class="mt-2 text-base text-slate-500">
                                        {{ __('La référence :ref sera supprimée. Que souhaitez-vous faire de ce numéro ?', ['ref' => $reference]) }}
                                    </p>

                                    <fieldset class="mt-4 space-y-3">
                                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 hover:border-primary/40">
                                            <input type="radio" wire:model="{{ $strategyModel }}" value="release" class="mt-1 size-4 border-slate-300 text-primary focus:ring-primary">
                                            <div>
                                                <div class="text-base font-medium text-slate-800">{{ __('Libérer le numéro') }}</div>
                                                <div class="text-sm text-slate-500">{{ __('La prochaine facture pourra utiliser ce numéro. Suppression définitive, aucune trace côté comptable.') }}</div>
                                            </div>
                                        </label>
                                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 hover:border-primary/40">
                                            <input type="radio" wire:model="{{ $strategyModel }}" value="vacant" class="mt-1 size-4 border-slate-300 text-primary focus:ring-primary">
                                            <div>
                                                <div class="text-base font-medium text-slate-800">{{ __('Le laisser vacant') }}</div>
                                                <div class="text-sm text-slate-500">{{ __('Numérotation avec un trou, traçable côté comptable. Le brouillon reste accessible via le filtre Archivés.') }}</div>
                                            </div>
                                        </label>
                                    </fieldset>
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
                                class="inline-flex w-full justify-center rounded-2xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-rose-500 sm:w-auto"
                            >
                                {{ __('Confirmer') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif
