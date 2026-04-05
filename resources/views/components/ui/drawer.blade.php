@props([
    'title',
    'subtitle'     => null,
    'closeAction'  => 'closeDrawer',
])

<div
    x-data="{
        open: false,
        close() {
            this.open = false;
            setTimeout(() => $wire.{{ $closeAction }}(), 500);
        },
    }"
    x-init="$nextTick(() => { open = true })"
    @keydown.escape.window="close()"
    class="fixed inset-0 z-50 overflow-hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="drawer-title"
>
    {{-- Backdrop --}}
    <div
        x-show="open"
        x-transition:enter="ease-in-out duration-500"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in-out duration-500"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-gray-500/75"
        aria-hidden="true"
    ></div>

    {{-- Panel --}}
    <div class="fixed inset-0 overflow-hidden">
        <div class="absolute inset-0 overflow-hidden" @click.self="close()">
            <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10 sm:pl-16">
                <div
                    x-show="open"
                    x-transition:enter="transform transition ease-in-out duration-500 sm:duration-700"
                    x-transition:enter-start="translate-x-full"
                    x-transition:enter-end="translate-x-0"
                    x-transition:leave="transform transition ease-in-out duration-500 sm:duration-700"
                    x-transition:leave-start="translate-x-0"
                    x-transition:leave-end="translate-x-full"
                    class="pointer-events-auto w-screen max-w-md"
                >
                    <div class="flex h-full flex-col overflow-y-auto bg-white py-6 shadow-xl">
                        <div class="px-4 sm:px-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <h2 id="drawer-title" class="text-base font-semibold text-ink">{{ $title }}</h2>
                                    @if ($subtitle)
                                        <p class="mt-0.5 text-sm text-slate-500">{{ $subtitle }}</p>
                                    @endif
                                </div>
                                <div class="ml-3 flex h-7 items-center">
                                    <button
                                        type="button"
                                        @click="close()"
                                        class="relative rounded-md text-slate-400 hover:text-slate-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                                    >
                                        <span class="absolute -inset-2.5"></span>
                                        <span class="sr-only">{{ __('Fermer') }}</span>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" class="size-6">
                                            <path d="M6 18 18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="relative mt-6 flex-1 overflow-y-auto px-4 sm:px-6">
                            {{ $slot }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
