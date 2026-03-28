@props([
    'dismissSeconds' => 5,
])

<div
    x-data="{
        toasts: [],
        duration: {{ $dismissSeconds }} * 1000,
        types: {
            error:   { bg: 'bg-red-600', track: 'bg-red-700/50', muted: 'text-red-100', hover: 'hover:bg-red-500' },
            success: { bg: 'bg-emerald-600', track: 'bg-emerald-700/50', muted: 'text-emerald-100', hover: 'hover:bg-emerald-500' },
            warning: { bg: 'bg-amber-500', track: 'bg-amber-600/50', muted: 'text-amber-100', hover: 'hover:bg-amber-400' },
        },
        add(type, title, messages = []) {
            const id = Date.now();
            this.toasts.push({ id, type, title, messages });
            this.$nextTick(() => {
                const bar = this.$el.querySelector(`[data-progress='${id}']`);
                if (bar) { bar.style.animation = `toast-shrink ${this.duration}ms linear forwards`; }
            });
            setTimeout(() => this.remove(id), this.duration);
        },
        remove(id) { this.toasts = this.toasts.filter(t => t.id !== id); },
    }"
    x-on:toast.window="add($event.detail.type, $event.detail.title, $event.detail.messages || [])"
    x-on:validation-errors.window="add('error', `${$event.detail.messages.length} ${$event.detail.messages.length > 1 ? '{{ __('erreurs empêchent') }}' : '{{ __('erreur empêche') }}'} {{ __('la sauvegarde') }}`, $event.detail.messages)"
    class="fixed bottom-4 right-4 z-50 flex w-full max-w-md flex-col gap-3"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-show="true"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-2"
            class="overflow-hidden rounded-lg shadow-lg"
            :class="types[toast.type].bg"
        >
            <div class="p-4">
                <div class="flex">
                    <div class="shrink-0">
                        {{-- Error icon --}}
                        <svg x-show="toast.type === 'error'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5 text-white">
                            <path d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM8.28 7.22a.75.75 0 0 0-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 1 0 1.06 1.06L10 11.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L11.06 10l1.72-1.72a.75.75 0 0 0-1.06-1.06L10 8.94 8.28 7.22Z" clip-rule="evenodd" fill-rule="evenodd" />
                        </svg>
                        {{-- Success icon --}}
                        <svg x-show="toast.type === 'success'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5 text-white">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
                        </svg>
                        {{-- Warning icon --}}
                        <svg x-show="toast.type === 'warning'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5 text-white">
                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3 flex-1">
                        <h3 class="text-sm font-semibold text-white" x-text="toast.title"></h3>
                        <div x-show="toast.messages.length > 0" class="mt-2 text-sm" :class="types[toast.type].muted">
                            <ul role="list" class="list-disc space-y-1 pl-5">
                                <template x-for="msg in toast.messages" :key="msg">
                                    <li x-text="msg"></li>
                                </template>
                            </ul>
                        </div>
                    </div>
                    <div class="ml-auto pl-3">
                        <button @click="remove(toast.id)" type="button" class="-m-1.5 inline-flex rounded-md p-1.5 text-white/70 hover:text-white" :class="types[toast.type].hover">
                            <span class="sr-only">{{ __('Fermer') }}</span>
                            <svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
            {{-- Progress bar --}}
            <div class="h-1 w-full" :class="types[toast.type].track">
                <div :data-progress="toast.id" class="h-full origin-left bg-white/60" style="animation: none;"></div>
            </div>
        </div>
    </template>

    <style>
        @keyframes toast-shrink {
            from { transform: scaleX(1); }
            to { transform: scaleX(0); }
        }
    </style>
</div>
