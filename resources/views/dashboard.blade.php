<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <div class="rounded-[2rem] border border-primary/10 bg-white p-8 shadow-soft">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-teal">{{ __('Bienvenue') }}</p>
            <h1 class="mt-3 text-3xl font-semibold text-ink">{{ __('Le dashboard Fayeku arrive ici.') }}</h1>
            <p class="mt-4 max-w-3xl text-base leading-7 text-slate-600">
                {{ __('La vitrine publique et les ecrans d authentification utilisent maintenant la meme identite Fayeku. Les modules metier pourront ensuite reprendre ce socle visuel a mesure que l application se remplit.') }}
            </p>
        </div>

        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <div class="relative aspect-video overflow-hidden rounded-[1.5rem] border border-primary/10 bg-white">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-primary/10" />
            </div>
            <div class="relative aspect-video overflow-hidden rounded-[1.5rem] border border-primary/10 bg-white">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-primary/10" />
            </div>
            <div class="relative aspect-video overflow-hidden rounded-[1.5rem] border border-primary/10 bg-white">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-primary/10" />
            </div>
        </div>
        <div class="relative h-full flex-1 overflow-hidden rounded-[1.5rem] border border-primary/10 bg-white">
            <x-placeholder-pattern class="absolute inset-0 size-full stroke-primary/10" />
        </div>
    </div>
</x-layouts::app>
