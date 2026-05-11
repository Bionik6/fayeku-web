@props([
    'show' => false,
    'confirmAction' => 'confirmIssue',
    'cancelAction' => 'closeIssueModal',
])

@if ($show)
    <div class="relative z-50" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-500/75" aria-hidden="true"></div>
        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 sm:items-center">
                <div class="relative overflow-hidden rounded-2xl bg-white text-left shadow-xl sm:w-full sm:max-w-lg">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex size-12 shrink-0 items-center justify-center rounded-full bg-primary/10 sm:mx-0 sm:size-10">
                                <flux:icon name="paper-airplane" class="size-6 text-primary" />
                            </div>
                            <div class="mt-3 sm:mt-0 sm:ml-4 text-left">
                                <h3 class="text-base font-semibold text-slate-900">{{ __('Émettre la facture') }}</h3>
                                <p class="mt-2 text-sm text-slate-500">
                                    {{ __('Une fois émise, cette facture sera considérée comme envoyée au client. Vous pourrez encore enregistrer des paiements, mais les modifications devront passer par une annulation ou un avoir.') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-50 px-4 py-3 sm:flex sm:justify-end sm:gap-3 sm:px-6">
                        <button
                            type="button"
                            wire:click="{{ $cancelAction }}"
                            class="mt-3 inline-flex w-full justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto"
                        >
                            {{ __('Annuler') }}
                        </button>
                        <button
                            type="button"
                            wire:click="{{ $confirmAction }}"
                            class="inline-flex w-full justify-center rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-strong sm:w-auto"
                        >
                            {{ __('Émettre la facture') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
