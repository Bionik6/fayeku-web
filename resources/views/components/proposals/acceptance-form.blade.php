@props([
    'mode',
    'modeModel' => 'acceptMode',
    'noteModel' => 'acceptanceNote',
    'poReferenceModel' => 'poReference',
    'poDateModel' => 'poReceivedAt',
    'poNotesModel' => 'poNotes',
    'poFileModel' => 'poFile',
    'poFile' => null,
    'removePoFileModel' => 'removePoFile',
    'removePoFile' => false,
    'existingPoFilePath' => null,
])

@php
    $cardBase = 'block cursor-pointer rounded-xl p-4 transition';
    $cardSelected = 'border-2 border-emerald-700 bg-emerald-50/50';
    $cardUnselected = 'border border-slate-200 bg-white hover:border-slate-300';
@endphp

<p class="text-base font-medium text-slate-700">{{ __("Comment le client a-t-il accepté ?") }}</p>

<fieldset class="mt-3 space-y-3">
    {{-- Carte 1 : Acceptation informelle --}}
    <label class="{{ $cardBase }} {{ $mode === 'informal' ? $cardSelected : $cardUnselected }}">
        <div class="flex items-start gap-3">
            <span class="relative mt-1 inline-block size-5 shrink-0">
                <input type="radio" wire:model.live="{{ $modeModel }}" value="informal" class="peer sr-only" />
                <span class="absolute inset-0 rounded-full border-2 border-slate-300 bg-white peer-checked:border-emerald-700"></span>
                <span class="pointer-events-none absolute inset-[5px] rounded-full bg-emerald-700 opacity-0 peer-checked:opacity-100"></span>
            </span>
            <div class="flex-1">
                <div class="text-base font-semibold text-slate-800">{{ __('Acceptation informelle') }}</div>
                <div class="mt-0.5 text-sm text-slate-500">{{ __('Verbale, email, WhatsApp ou tout autre accord non formalisé') }}</div>
                @if ($mode === 'informal')
                    <div class="mt-3">
                        <label for="acceptance-form-note" class="block text-sm font-medium text-slate-700">{{ __('Note (optionnel)') }}</label>
                        <textarea
                            id="acceptance-form-note"
                            wire:model="{{ $noteModel }}"
                            rows="3"
                            placeholder="{{ __('Ex : accord reçu par WhatsApp le 11/05') }}"
                            class="mt-1 block w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-base text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                        ></textarea>
                    </div>
                @endif
            </div>
        </div>
    </label>

    {{-- Carte 2 : Bon de commande reçu --}}
    <label class="{{ $cardBase }} {{ $mode === 'po' ? $cardSelected : $cardUnselected }}">
        <div class="flex items-start gap-3">
            <span class="relative mt-1 inline-block size-5 shrink-0">
                <input type="radio" wire:model.live="{{ $modeModel }}" value="po" class="peer sr-only" />
                <span class="absolute inset-0 rounded-full border-2 border-slate-300 bg-white peer-checked:border-emerald-700"></span>
                <span class="pointer-events-none absolute inset-[5px] rounded-full bg-emerald-700 opacity-0 peer-checked:opacity-100"></span>
            </span>
            <div class="flex-1">
                <div class="text-base font-semibold text-slate-800">{{ __('Bon de commande reçu') }}</div>
                <div class="mt-0.5 text-sm text-slate-500">{{ __('Document formel émis par le client') }}</div>

                @if ($mode === 'po')
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="acceptance-form-po-ref" class="block text-sm font-medium text-slate-700">
                                {{ __('Numéro de BC') }} <span class="text-rose-500">*</span>
                            </label>
                            <input id="acceptance-form-po-ref" wire:model="{{ $poReferenceModel }}" type="text" placeholder="BC-2026/0042"
                                   class="mt-1 block w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-base text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10" />
                        </div>
                        <div>
                            <label for="acceptance-form-po-date" class="block text-sm font-medium text-slate-700">
                                {{ __('Date de réception') }} <span class="text-rose-500">*</span>
                            </label>
                            <div
                                wire:ignore
                                wire:key="acceptance-form-po-date-picker"
                                x-data="{
                                    picker: null,
                                    init() {
                                        this.picker = flatpickr(this.$refs.input, {
                                            dateFormat: 'Y-m-d',
                                            altInput: true,
                                            altFormat: 'd/m/Y',
                                            defaultDate: $wire.{{ $poDateModel }},
                                            onChange: (dates, dateStr) => $wire.set('{{ $poDateModel }}', dateStr),
                                        });
                                        this.$watch('$wire.{{ $poDateModel }}', (val) => {
                                            if (this.picker && val) this.picker.setDate(val, false);
                                        });
                                    },
                                    destroy() { if (this.picker) this.picker.destroy(); }
                                }"
                            >
                                <input id="acceptance-form-po-date" x-ref="input" type="text" readonly
                                       class="mt-1 block w-full cursor-pointer rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-base text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10" />
                            </div>
                        </div>

                        <div class="sm:col-span-2">
                            <label for="acceptance-form-po-notes" class="block text-sm font-medium text-slate-700">{{ __('Note (optionnel)') }}</label>
                            <textarea
                                id="acceptance-form-po-notes"
                                wire:model="{{ $poNotesModel }}"
                                rows="2"
                                placeholder="{{ __('Détails internes sur ce bon de commande…') }}"
                                class="mt-1 block w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-base text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                            ></textarea>
                        </div>

                        <div class="sm:col-span-2">
                            <p class="block text-sm font-medium text-slate-700">
                                {{ __('Fichier du BC') }}
                                <span class="font-normal text-slate-400">{{ __('(optionnel · PDF ou image, 5 Mo max)') }}</span>
                            </p>

                            @if ($existingPoFilePath && ! $poFile && ! $removePoFile)
                                <div class="mt-1 flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-base">
                                    <span class="inline-flex items-center gap-2 text-slate-700">
                                        <flux:icon name="document" class="size-4 text-slate-400" />
                                        {{ basename($existingPoFilePath) }}
                                    </span>
                                    <button type="button" wire:click="$set('{{ $removePoFileModel }}', true)" class="text-sm font-semibold text-rose-600 hover:text-rose-500">{{ __('Supprimer') }}</button>
                                </div>
                                <p class="mt-1 text-sm text-slate-500">{{ __('Choisissez un nouveau fichier ci-dessous pour le remplacer.') }}</p>
                            @elseif ($removePoFile)
                                <div class="mt-1 flex items-center justify-between gap-3 rounded-2xl border border-rose-100 bg-rose-50/60 px-4 py-3 text-base text-rose-700">
                                    <span>{{ __('Fichier marqué pour suppression à l\'enregistrement.') }}</span>
                                    <button type="button" wire:click="$set('{{ $removePoFileModel }}', false)" class="text-sm font-semibold text-rose-700 hover:text-rose-600 underline">{{ __('Annuler') }}</button>
                                </div>
                            @endif

                            <label for="acceptance-form-po-file" class="mt-1 flex cursor-pointer flex-col items-center justify-center gap-1 rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50/80 px-4 py-5 text-center transition hover:border-emerald-600">
                                @if ($poFile)
                                    <flux:icon name="document-check" class="size-6 text-emerald-600" />
                                    <div class="text-base font-medium text-slate-700">{{ $poFile->getClientOriginalName() }}</div>
                                    <button type="button" wire:click="$set('{{ $poFileModel }}', null)" class="text-sm font-semibold text-rose-600 underline hover:text-rose-500">{{ __('Retirer') }}</button>
                                @else
                                    <flux:icon name="cloud-arrow-up" class="size-6 text-slate-400" />
                                    <div class="text-base text-slate-600">
                                        {{ __('Glisser un fichier ici, ou') }}
                                        <span class="font-semibold text-emerald-700 underline">{{ __('parcourir') }}</span>
                                    </div>
                                    <div class="text-sm text-slate-500">{{ __('PDF, JPG, PNG — 5 Mo max') }}</div>
                                @endif
                                <input id="acceptance-form-po-file" wire:model="{{ $poFileModel }}" type="file" accept=".pdf,.jpg,.jpeg,.png" class="sr-only" />
                            </label>
                            <div wire:loading wire:target="{{ $poFileModel }}" class="mt-1 inline-flex items-center gap-2 text-sm text-slate-500">
                                <flux:icon name="arrow-path" class="size-4 animate-spin" />
                                {{ __('Téléversement…') }}
                            </div>
                        </div>
                    </div>

                    {{-- Erreurs serveur, n'apparaissent qu'après soumission --}}
                    @error($poReferenceModel) <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror
                    @error($poDateModel) <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    @error($poNotesModel) <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    @error($poFileModel) <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                @endif
            </div>
        </div>
    </label>
</fieldset>
