<x-layouts::pme.sidebar :title="$title ?? null">
    <flux:main>
        <x-shared.demo-banner />
        {{ $slot }}
    </flux:main>
</x-layouts::pme.sidebar>
