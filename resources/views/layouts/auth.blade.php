<x-layouts::auth.simple :title="$title ?? null">
    @isset($aside)
        <x-slot:aside>{{ $aside }}</x-slot:aside>
    @endisset

    {{ $slot }}
</x-layouts::auth.simple>
