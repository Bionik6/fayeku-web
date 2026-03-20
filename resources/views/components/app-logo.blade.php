@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="Fayeku" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-xl bg-primary/10 text-primary">
            <x-app-logo-icon class="size-6" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="Fayeku" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-xl bg-primary/10 text-primary">
            <x-app-logo-icon class="size-6" />
        </x-slot>
    </flux:brand>
@endif
