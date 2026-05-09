@php
    $stepKeys = ['intent', 'identity', 'signature', 'first_client', 'confirmation'];
    $stepLabels = trans('onboarding.aside.steps');
    $stepTitles = trans('onboarding.aside.titles');
    $stepDescriptions = trans('onboarding.aside.descriptions');
    $tips = trans('onboarding.aside.tips');
    $totalSteps = count($stepKeys);
    $isComplete = $currentStep >= $totalSteps - 1;
    $currentKey = $stepKeys[$currentStep] ?? 'intent';
    $tip = $tips[$currentKey] ?? null;
    $progressPct = $isComplete ? 100 : (int) round((($currentStep + 1) / $totalSteps) * 100);
@endphp

<div class="space-y-4">
    {{-- Badge en haut --}}
    <span class="inline-flex items-center gap-2 rounded-full border border-[#024D4D]/10 bg-white/70 px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-[0.18em] text-teal">
        @if ($isComplete)
            <svg viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3 text-accent">
                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
            </svg>
            {{ __('onboarding.aside.badge_complete') }}
        @else
            <span class="h-1.5 w-1.5 rounded-full bg-accent"></span>
            {{ __('onboarding.aside.badge_in_progress') }}
        @endif
    </span>

    <h1 class="text-balance text-3xl font-semibold leading-[1.05] text-[#024D4D] sm:text-[34px] lg:text-[40px] lg:leading-[48px]">
        {{ $stepTitles[$currentKey] }}
    </h1>

    <p class="text-base leading-7 text-[#1D5D5D]">
        {{ $stepDescriptions[$currentKey] }}
    </p>
</div>

{{-- Carte "Votre progression" --}}
<div class="rounded-2xl border border-[#024D4D]/10 bg-white/85 p-5 shadow-sm">
    <div class="flex items-center justify-between">
        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#024D4D]/70">
            {{ __('onboarding.aside.progress_card_title') }}
        </p>
        <p class="text-xs font-semibold tabular-nums text-[#024D4D]/60">
            @if ($isComplete)
                {{ __('onboarding.aside.progress_complete_label') }}
            @else
                {{ __('onboarding.aside.progress_step_n_of_total', ['step' => $currentStep + 1, 'total' => $totalSteps]) }}
            @endif
        </p>
    </div>

    {{-- Mince barre horizontale --}}
    <div class="mt-3 h-1 w-full overflow-hidden rounded-full bg-[#024D4D]/10">
        <div class="h-full rounded-full bg-primary transition-all duration-500" style="width: {{ $progressPct }}%"></div>
    </div>

    {{-- Liste verticale des steps (style "circles with text") --}}
    <nav aria-label="Progress" class="mt-5">
        <ol role="list" class="overflow-hidden">
            @foreach ($stepKeys as $i => $key)
                @php
                    $isStepDone = $currentStep > $i || ($isComplete && $i === $totalSteps - 1);
                    $isStepCurrent = $currentStep === $i && ! $isComplete;
                    $isOptional = $key === 'first_client';
                    $isLast = $i === $totalSteps - 1;
                @endphp
                <li class="relative {{ $isLast ? '' : 'pb-6' }}">
                    @unless ($isLast)
                        <div
                            aria-hidden="true"
                            class="absolute left-4 top-4 -ml-px mt-0.5 h-full w-0.5 {{ $isStepDone ? 'bg-primary' : 'bg-[#024D4D]/15' }}"
                        ></div>
                    @endunless

                    <div
                        @if ($isStepCurrent) aria-current="step" @endif
                        class="relative flex items-start"
                    >
                        <span class="flex h-8 items-center">
                            @if ($isStepDone)
                                <span class="relative z-10 flex size-8 items-center justify-center rounded-full bg-primary">
                                    <svg viewBox="0 0 20 20" fill="currentColor" class="size-4 text-white">
                                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            @elseif ($isStepCurrent)
                                <span class="relative z-10 flex size-8 items-center justify-center rounded-full border-2 border-primary bg-white">
                                    <span class="size-2.5 rounded-full bg-primary"></span>
                                </span>
                            @else
                                <span class="relative z-10 flex size-8 items-center justify-center rounded-full border-2 border-[#024D4D]/20 bg-white">
                                    <span class="text-[11px] font-medium text-[#024D4D]/45">{{ $i + 1 }}</span>
                                </span>
                            @endif
                        </span>

                        <span class="ml-4 flex min-w-0 flex-1 items-center justify-between gap-2">
                            <span class="flex flex-col">
                                <span class="text-sm font-medium {{ $isStepCurrent ? 'text-primary' : ($isStepDone ? 'text-[#024D4D]' : 'text-[#024D4D]/55') }}">
                                    {{ $stepLabels[$key] }}
                                </span>
                            </span>

                            <span class="flex shrink-0 items-center gap-1.5">
                                @if ($isOptional && ! $isStepDone)
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                        {{ __('onboarding.aside.progress_optional_chip') }}
                                    </span>
                                @endif
                                @if ($isStepCurrent)
                                    <span class="inline-flex items-center rounded-full bg-green-50 px-1.5 py-0.5 text-[13px] font-medium text-green-700 inset-ring inset-ring-green-600/20">
                                        {{ __('onboarding.aside.progress_in_progress_chip') }}
                                    </span>
                                @elseif ($isStepDone && $isLast)
                                    <span class="inline-flex items-center gap-1 text-[11px] font-medium text-accent">
                                        <svg viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3">
                                            <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                        </svg>
                                        {{ __('onboarding.aside.progress_done_chip') }}
                                    </span>
                                @endif
                            </span>
                        </span>
                    </div>
                </li>
            @endforeach
        </ol>
    </nav>
</div>

{{-- Carte d'astuce contextuelle --}}
@if ($tip)
    <div class="flex items-start gap-3 rounded-2xl bg-white/80 p-4 shadow-sm">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-mist text-primary">
            <flux:icon :name="$tip['icon']" class="h-5 w-5" />
        </span>
        <p class="text-sm leading-snug text-[#1D5D5D]">
            <strong class="font-semibold text-[#024D4D]">{{ $tip['title'] }}</strong>
            {{ $tip['body'] }}
        </p>
    </div>
@endif
