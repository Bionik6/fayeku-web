@php
    $intents = \App\Enums\Auth\OnboardingIntent::options();
@endphp

<div class="space-y-7">
    <header class="space-y-2">
        <h1 class="text-2xl font-semibold leading-tight text-ink sm:text-[28px]">
            {{ __('onboarding.intent.title', ['first_name' => auth()->user()->first_name]) }}
        </h1>
        <p class="text-sm text-slate-500">{{ __('onboarding.intent.subtitle') }}</p>
    </header>

    <div class="space-y-3">
        @foreach ($intents as $intentCase)
            @php $isSelected = $intent === $intentCase->value; @endphp
            <button
                type="button"
                wire:click="selectIntent('{{ $intentCase->value }}')"
                class="group flex w-full cursor-pointer items-center gap-4 rounded-2xl border-2 p-4 text-left transition focus:outline-none focus:ring-2 focus:ring-primary/20 {{ $isSelected ? 'border-primary bg-mist' : 'border-slate-200 bg-white hover:border-primary/40 hover:bg-mist/50' }}"
                data-test="intent-{{ $intentCase->value }}"
            >
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ $isSelected ? 'bg-primary text-white' : 'bg-mist text-primary' }}">
                    <flux:icon :name="$intentCase->icon()" class="h-5 w-5" />
                </span>

                <span class="min-w-0 flex-1">
                    <span class="block text-base font-semibold text-ink">{{ $intentCase->label() }}</span>
                    <span class="mt-0.5 block text-sm text-slate-500">{{ $intentCase->description() }}</span>
                </span>

                {{-- Indicateur radio à droite --}}
                @if ($isSelected)
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary text-white">
                        <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5">
                            <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                        </svg>
                    </span>
                @else
                    <span class="h-6 w-6 shrink-0 rounded-full border-2 border-slate-300"></span>
                @endif
            </button>
        @endforeach

        @error('intent') <p class="text-sm font-medium text-rose-600">{{ $message }}</p> @enderror
    </div>

    <div class="flex justify-end border-t border-slate-100 pt-5">
        <button
            type="button"
            wire:click="goToStep(1)"
            @disabled(! $intent)
            class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-semibold text-white shadow-soft transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
            data-test="step-intent-next"
        >
            {{ __('onboarding.wizard.next') }}
            <flux:icon name="arrow-right" class="h-4 w-4" />
        </button>
    </div>
</div>
