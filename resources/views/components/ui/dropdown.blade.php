@props(['label' => 'Actions', 'align' => 'right'])

<div
    x-data="{ open: false, top: 0, left: 0, right: 0 }"
    class="inline-block"
    @ui-dropdown-close.window="open = false"
>
    <button
        x-ref="trigger"
        type="button"
        @click="
            const wasOpen = open;
            $dispatch('ui-dropdown-close');
            const rect = $refs.trigger.getBoundingClientRect();
            $nextTick(() => {
                if (!wasOpen) {
                    top   = rect.bottom + 8;
                    right = window.innerWidth - rect.right;
                    left  = rect.left;
                    open  = true;
                }
            })
        "
        class="inline-flex items-center gap-x-1.5 rounded-xl bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-xs ring-1 ring-inset ring-slate-300 hover:bg-slate-50"
    >
        {{ $label }}
        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="-mr-0.5 size-4 text-slate-400">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" />
        </svg>
    </button>

    <template x-teleport="body">
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="transform opacity-0 scale-95"
            x-transition:enter-end="transform opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="transform opacity-100 scale-100"
            x-transition:leave-end="transform opacity-0 scale-95"
            @click.outside="open = false"
            :style="{{ $align === 'right'
                ? '`position: fixed; z-index: 9999; top: ${top}px; right: ${right}px`'
                : '`position: fixed; z-index: 9999; top: ${top}px; left: ${left}px`' }}"
            class="w-auto min-w-44 rounded-xl bg-white py-1 shadow-lg ring-1 ring-black/5 focus:outline-none"
            role="menu"
            aria-orientation="vertical"
        >
            {{ $slot }}
        </div>
    </template>
</div>
