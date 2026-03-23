@props([
    'label' => 'Téléphone',
    'countryName' => 'country_code',
    'countryValue' => 'SN',
    'phoneName' => 'phone',
    'phoneValue' => '',
    'countries' => [
        'SN' => 'SEN (+221)',
        'CI' => 'CIV (+225)',
    ],
    'countryModel' => null,
    'phoneModel' => null,
    'required' => false,
    'autocomplete' => 'tel',
    'autofocus' => false,
    'phonePlaceholder' => 'XX XXX XX XX',
    'labelClass' => 'auth-field-label',
    'containerClass' => '',
    'inputClass' => '',
    'readonly' => false,
])

@php
    $countries = is_array($countries) && $countries !== [] ? $countries : [
        'SN' => 'SEN (+221)',
        'CI' => 'CIV (+225)',
    ];

    $containerClasses = trim($containerClass ?: 'flex items-stretch rounded-2xl border border-slate-300 bg-white transition focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/15');
    $phoneClasses = trim('min-w-0 grow rounded-r-2xl border-0 bg-transparent px-4 py-3 text-base text-ink placeholder:text-slate-400 outline-none focus:ring-0 '.$inputClass);
    $selectedCountryLabel = $countries[$countryValue] ?? $countryValue;
    $formattedPhoneValue = (function (string $country, string $phone): string {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        $prefix = preg_replace('/\D+/', '', (string) config("fayeku.countries.{$country}.prefix", '')) ?? '';

        if ($prefix !== '' && str_starts_with($digits, $prefix)) {
            $digits = substr($digits, strlen($prefix));
        }

        return match ($country) {
            'SN' => (function (string $value): string {
                $normalized = substr($value, 0, 9);

                return match (true) {
                    strlen($normalized) <= 2 => $normalized,
                    strlen($normalized) <= 5 => substr($normalized, 0, 2).' '.substr($normalized, 2),
                    strlen($normalized) <= 7 => substr($normalized, 0, 2).' '.substr($normalized, 2, 3).' '.substr($normalized, 5),
                    default => substr($normalized, 0, 2).' '.substr($normalized, 2, 3).' '.substr($normalized, 5, 2).' '.substr($normalized, 7),
                };
            })($digits),
            'CI' => trim(implode(' ', str_split(substr($digits, 0, 10), 2))),
            default => $digits,
        };
    })($countryValue, (string) $phoneValue);
@endphp

<div {{ $attributes->class(['space-y-1']) }} data-phone-field>
    <span class="block {{ $labelClass }}">
        {{ $label }}@if ($required) * @endif
    </span>

    <div class="{{ $containerClasses }}">
        @if ($readonly)
            <div class="flex items-center rounded-l-2xl px-4 py-3 text-base font-medium text-ink">
                {{ $selectedCountryLabel }}
            </div>
        @else
            <div class="relative shrink-0">
                <select
                    name="{{ $countryName }}"
                    @if (filled($countryModel)) wire:model.live="{{ $countryModel }}" @endif
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
        @endif

        <div class="my-3 w-px shrink-0 bg-slate-200"></div>

        @if ($readonly)
            <div class="flex min-h-[3.25rem] min-w-0 grow items-center px-4 py-3 text-base text-ink">
                {{ filled($phoneValue) ? $formattedPhoneValue : '—' }}
            </div>
        @else
            <input
                name="{{ $phoneName }}"
                type="tel"
                value="{{ $formattedPhoneValue }}"
                @if (filled($phoneModel)) wire:model="{{ $phoneModel }}" @endif
                @if ($required) required @endif
                @if ($autofocus) autofocus @endif
                autocomplete="{{ $autocomplete }}"
                placeholder="{{ $phonePlaceholder }}"
                class="{{ $phoneClasses }}"
                data-phone-input
            />
        @endif
    </div>
</div>
