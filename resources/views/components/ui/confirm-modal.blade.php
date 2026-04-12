@props([
    'confirmId' => null,      // Livewire context: shown when non-null, confirm calls wire:click
    'formAction' => null,     // Non-Livewire context: shown via Alpine x-show="open", confirm submits a form POST
    'title',
    'description',
    'confirmAction' => null,  // wire:click method name (Livewire context only)
    'cancelAction' => null,   // wire:click method name (Livewire context only)
    'confirmLabel' => 'Confirmer',
    'variant' => 'destructive', // destructive | primary
])

@php
    $iconBg    = $variant === 'destructive' ? 'bg-rose-100'   : 'bg-primary/10';
    $iconColor = $variant === 'destructive' ? 'text-rose-600' : 'text-primary';
    $btnClass  = $variant === 'destructive'
        ? 'bg-rose-600 hover:bg-rose-500 text-white'
        : 'bg-primary hover:bg-primary-strong text-white';
    $isFormMode = $formAction !== null;
@endphp

@if ($isFormMode || $confirmId)
    @if ($isFormMode) <template x-teleport="body"> @endif
    <div
        @if ($isFormMode) x-show="open" x-cloak @endif
        class="relative z-50"
        aria-labelledby="modal-confirm-title"
        role="dialog"
        aria-modal="true"
    >
        <div class="fixed inset-0 bg-slate-500/75 transition-opacity" aria-hidden="true"></div>
        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">

                    {{-- Body --}}
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex size-12 shrink-0 items-center justify-center rounded-full {{ $iconBg }} sm:mx-0 sm:size-10">
                                @if ($variant === 'destructive')
                                    <svg class="size-6 {{ $iconColor }}" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                    </svg>
                                @else
                                    <svg class="size-6 {{ $iconColor }}" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m6.75 12-3-3m0 0-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                    </svg>
                                @endif
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 id="modal-confirm-title" class="text-base font-semibold text-slate-900">{{ $title }}</h3>
                                <div class="mt-2">
                                    <p class="text-sm text-slate-500">{{ $description }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Gray footer --}}
                    <div class="bg-slate-50 px-4 py-3 sm:flex sm:justify-end sm:gap-3 sm:px-6">
                        @if ($isFormMode)
                            <button
                                type="button"
                                @click="open = false"
                                class="mt-3 inline-flex w-full justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto"
                            >
                                {{ __('Annuler') }}
                            </button>
                            <form method="POST" action="{{ $formAction }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="inline-flex w-full justify-center rounded-xl px-4 py-2.5 text-sm font-semibold shadow-sm sm:w-auto {{ $btnClass }}"
                                >
                                    {{ $confirmLabel }}
                                </button>
                            </form>
                        @else
                            <button
                                type="button"
                                wire:click="{{ $cancelAction }}"
                                class="mt-3 inline-flex w-full justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto"
                            >
                                {{ __('Annuler') }}
                            </button>
                            <button
                                type="button"
                                wire:click="{{ $confirmAction }}('{{ $confirmId }}')"
                                class="inline-flex w-full justify-center rounded-xl px-4 py-2.5 text-sm font-semibold shadow-sm sm:w-auto {{ $btnClass }}"
                            >
                                {{ $confirmLabel }}
                            </button>
                        @endif
                    </div>

                </div>
            </div>
        </div>
    </div>
    @if ($isFormMode) </template> @endif
@endif
