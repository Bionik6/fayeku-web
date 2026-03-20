@props([
    'title',
    'description',
])

<div class="flex w-full flex-col text-center">
    <p class="text-sm font-semibold uppercase tracking-[0.24em] text-teal">Espace securise</p>
    <flux:heading size="xl" class="mt-3 text-ink">{{ $title }}</flux:heading>
    <flux:subheading class="mt-2 text-slate-600">{{ $description }}</flux:subheading>
</div>
