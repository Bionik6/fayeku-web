<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main>
        <x-shared.demo-banner />
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
