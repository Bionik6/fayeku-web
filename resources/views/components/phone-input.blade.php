@props([
    'label' => 'Téléphone',
    'countryName' => 'country_code',
    'countryValue' => 'SN',
    'phoneName' => 'phone',
    'phoneValue' => '',
    'required' => false,
    'autocomplete' => 'tel',
    'autofocus' => false,
    'phonePlaceholder' => 'XX XXX XX XX',
    'labelClass' => 'text-sm font-medium text-ink',
    'containerClass' => '',
    'inputClass' => '',
])

@php
    $countries = [
        'SN' => 'SEN (+221)',
        'CI' => 'CIV (+225)',
    ];

    $containerClasses = trim($containerClass ?: 'flex items-stretch rounded-2xl border border-slate-300 bg-white transition focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/15');
    $phoneClasses = trim('min-w-0 grow rounded-r-2xl border-0 bg-transparent px-4 py-3 text-base text-ink placeholder:text-slate-400 outline-none focus:ring-0 '.$inputClass);
@endphp

<div {{ $attributes->class(['space-y-3']) }} data-phone-field>
    <span class="{{ $labelClass }}">
        {{ $label }}@if ($required) * @endif
    </span>

    <div class="{{ $containerClasses }}">
        <div class="relative shrink-0">
            <select
                name="{{ $countryName }}"
                class="h-full appearance-none rounded-l-2xl border-0 bg-transparent py-3 pl-4 pr-10 text-base font-medium text-ink outline-none focus:ring-0"
                data-phone-country
            >
                @foreach ($countries as $value => $optionLabel)
                    <option value="{{ $value }}" @selected($countryValue === $value)>{{ $optionLabel }}</option>
                @endforeach
            </select>

            <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true">
                    <path d="m6 8 4 4 4-4" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </div>
        </div>

        <div class="my-3 w-px shrink-0 bg-slate-200"></div>

        <input
            name="{{ $phoneName }}"
            type="tel"
            value="{{ $phoneValue }}"
            @if ($required) required @endif
            @if ($autofocus) autofocus @endif
            autocomplete="{{ $autocomplete }}"
            placeholder="{{ $phonePlaceholder }}"
            class="{{ $phoneClasses }}"
            data-phone-input
        />
    </div>
</div>
