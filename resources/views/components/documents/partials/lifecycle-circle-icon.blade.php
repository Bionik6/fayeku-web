@php
    $state = $state ?? 'pending';
@endphp

@if ($state === 'completed')
    <svg viewBox="0 0 20 20" fill="currentColor" class="size-4" aria-hidden="true">
        <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.31a1 1 0 0 1-1.42 0L4.29 10.23a1 1 0 1 1 1.42-1.408l3.04 3.066 6.54-6.592a1 1 0 0 1 1.414-.006Z" clip-rule="evenodd" />
    </svg>
@elseif ($state === 'current')
    <span class="size-3 rounded-full bg-current"></span>
@elseif ($state === 'danger' || $state === 'muted-failed')
    <svg viewBox="0 0 20 20" fill="currentColor" class="size-4" aria-hidden="true">
        <path fill-rule="evenodd" d="M5.22 5.22a.75.75 0 0 1 1.06 0L10 8.94l3.72-3.72a.75.75 0 1 1 1.06 1.06L11.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06L10 11.06l-3.72 3.72a.75.75 0 0 1-1.06-1.06L8.94 10 5.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
    </svg>
@elseif ($state === 'warning')
    <span class="text-sm font-black leading-none">!</span>
@endif
