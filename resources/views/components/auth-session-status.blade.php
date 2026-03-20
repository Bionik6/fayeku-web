@props([
    'status',
])

@if ($status)
    <div {{ $attributes->merge(['class' => 'rounded-2xl bg-mist px-4 py-3 text-sm font-medium text-primary']) }}>
        {{ $status }}
    </div>
@endif
