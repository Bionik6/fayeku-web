@props([
    'lifecycle',
])

@php
    $document = $lifecycle['document'] ?? [];
    $badges = $lifecycle['badges'] ?? [];
    $steps = $lifecycle['steps'] ?? [];
    $message = $lifecycle['message'] ?? null;
    $note = $lifecycle['note'] ?? null;

    $circleClasses = [
        'completed' => 'border-primary bg-primary text-white ring-primary/10',
        'current' => 'border-primary bg-white text-primary ring-primary/10',
        'pending' => 'border-slate-300 bg-white text-slate-400 ring-slate-100',
        'danger' => 'border-rose-600 bg-rose-600 text-white ring-rose-100',
        'warning' => 'border-amber-500 bg-amber-500 text-white ring-amber-100',
        'muted-failed' => 'border-slate-600 bg-slate-600 text-white ring-slate-100',
    ];

    $labelClasses = [
        'completed' => 'text-ink',
        'current' => 'text-ink',
        'pending' => 'text-slate-600',
        'danger' => 'text-rose-600',
        'warning' => 'text-amber-600',
        'muted-failed' => 'text-slate-600',
    ];

    $connectorClasses = [
        'active' => 'bg-primary',
        'neutral' => 'bg-slate-200',
        'muted' => 'bg-slate-300',
        'none' => 'bg-slate-200',
    ];
@endphp

<section data-document-lifecycle class="app-shell-panel p-4 sm:p-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap items-center gap-3">
            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold {{ $document['class'] ?? 'bg-slate-100 text-slate-700' }}">
                {{ __($document['badge'] ?? $document['label'] ?? 'Document') }}
            </span>
            <h4 class="text-base font-semibold text-ink">{{ __($lifecycle['title'] ?? '') }}</h4>
        </div>

        @if (count($badges) > 0)
            <div class="flex flex-wrap gap-2 sm:justify-end">
                @foreach ($badges as $badge)
                    <span wire:key="document-lifecycle-badge-{{ $loop->index }}" class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $badge['class'] }}">
                        {{ __($badge['label']) }}
                    </span>
                @endforeach
            </div>
        @endif
    </div>

    <div class="mt-4">
        @if ($message)
            <div class="flex gap-3 rounded-xl bg-slate-50 px-4 py-3">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-full border border-dashed border-slate-300 text-slate-500">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 7.125 16.875 4.5M18 14v4.75A2.25 2.25 0 0 1 15.75 21h-10.5A2.25 2.25 0 0 1 3 18.75v-10.5A2.25 2.25 0 0 1 5.25 6H10" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-ink">{{ __($message['title']) }}</p>
                    <p class="mt-0.5 text-sm leading-5 text-slate-600">{{ __($message['body']) }}</p>
                </div>
            </div>
        @else
            {{-- Mobile : timeline verticale --}}
            <ol class="flex flex-col gap-4 sm:hidden">
                @foreach ($steps as $step)
                    @php
                        $state = $step['state'] ?? 'pending';
                        $connector = $step['connector'] ?? 'none';
                    @endphp

                    <li wire:key="document-lifecycle-step-mobile-{{ $loop->index }}" class="relative flex min-w-0 gap-3">
                        @if (! $loop->last)
                            <span aria-hidden="true" class="absolute left-3 top-6 h-[calc(100%+1rem)] w-0.5 -translate-x-1/2 {{ $connectorClasses[$connector] ?? $connectorClasses['neutral'] }}"></span>
                        @endif

                        <div class="relative z-10 flex size-6 shrink-0 items-center justify-center rounded-full border ring-2 {{ $circleClasses[$state] ?? $circleClasses['pending'] }}">
                            @include('components.documents.partials.lifecycle-circle-icon', ['state' => $state])
                        </div>

                        <div class="min-w-0">
                            <p class="text-sm font-semibold leading-5 {{ $labelClasses[$state] ?? $labelClasses['pending'] }}">
                                {{ __($step['label'] ?? '') }}
                            </p>
                            <p class="text-xs leading-5 text-slate-500">{{ $step['detail'] ?? '—' }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>

            {{-- Desktop : timeline horizontale qui occupe toute la largeur,
                 dernier step ancré tout à droite. --}}
            <div class="hidden sm:block">
                <ol class="flex items-center">
                    @foreach ($steps as $step)
                        @php
                            $state = $step['state'] ?? 'pending';
                            $connector = $step['connector'] ?? 'none';
                        @endphp

                        <li wire:key="document-lifecycle-step-{{ $loop->index }}" class="relative z-10 flex size-6 shrink-0 items-center justify-center rounded-full border ring-2 {{ $circleClasses[$state] ?? $circleClasses['pending'] }}">
                            @include('components.documents.partials.lifecycle-circle-icon', ['state' => $state])
                        </li>

                        @if (! $loop->last)
                            <span aria-hidden="true" class="h-0.5 flex-1 {{ $connectorClasses[$connector] ?? $connectorClasses['neutral'] }}"></span>
                        @endif
                    @endforeach
                </ol>

                <div class="mt-2 flex gap-4">
                    @foreach ($steps as $step)
                        @php
                            $state = $step['state'] ?? 'pending';
                            $align = $loop->first ? 'text-left' : ($loop->last ? 'text-right' : 'text-center');
                        @endphp

                        <div class="min-w-0 flex-1 {{ $align }}">
                            <p class="text-sm font-semibold leading-5 {{ $labelClasses[$state] ?? $labelClasses['pending'] }}">
                                {{ __($step['label'] ?? '') }}
                            </p>
                            <p class="text-xs leading-5 text-slate-500">{{ $step['detail'] ?? '—' }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            @if ($note)
                <div class="mt-3 border-t border-slate-100 pt-3">
                    <p class="text-xs leading-5 text-slate-500">{{ __($note) }}</p>
                </div>
            @endif
        @endif
    </div>
</section>
