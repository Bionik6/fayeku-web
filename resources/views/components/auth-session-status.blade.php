@props([
    'status',
])

@if ($status)
    <div {{ $attributes->merge(['class' => 'rounded-3xl bg-mist px-4 py-3 text-sm font-medium leading-6 text-primary']) }}>
        {{ $status }}
    </div>
@endif
