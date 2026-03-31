@props([
    'label' => 'Téléphone',
    'countryName' => 'country_code',
    'countryValue' => 'SN',
    'phoneName' => 'phone',
    'phoneValue' => '',
    'countries' => [],
    'countryModel' => null,
    'phoneModel' => null,
    'required' => false,
    'autocomplete' => 'tel',
    'autofocus' => false,
    'phonePlaceholder' => 'XX XXX XX XX',
    'inputClass' => '',
    'readonly' => false,
])

@php
    // Resolve countries list — empty array falls back to SN + CI from config.
    $resolvedCountries = (is_array($countries) && count($countries) > 0)
        ? $countries
        : [
            'SN' => config('fayeku.countries.SN.label', 'SEN (+221)'),
            'CI' => config('fayeku.countries.CI.label', 'CIV (+225)'),
        ];

    $isSingleCountry = count($resolvedCountries) === 1;
    $selectedCountryCode = $isSingleCountry ? array_key_first($resolvedCountries) : $countryValue;
    $selectedCountryLabel = $resolvedCountries[$selectedCountryCode] ?? $selectedCountryCode;

    // Pre-format the phone value for display.
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
    })($selectedCountryCode, (string) $phoneValue);
@endphp

<div {{ $attributes->class(['space-y-1']) }} data-phone-field>
    <span class="block auth-field-label">
        {{ $label }}@if ($required) * @endif
    </span>

    @if ($readonly)
        <div class="flex cursor-not-allowed items-stretch rounded-2xl border border-slate-200 bg-slate-50">
            <div class="flex items-center rounded-l-2xl px-4 py-3 text-base font-medium text-ink">
                {{ $selectedCountryLabel }}
            </div>
            <div class="flex min-h-[3.25rem] min-w-0 grow items-center px-4 py-3 text-base text-ink">
                {{ filled($phoneValue) ? $formattedPhoneValue : '—' }}
            </div>
        </div>
    @else
        <div class="flex items-stretch rounded-2xl border border-slate-300 bg-white transition
                    has-[select:focus-within]:border-primary has-[select:focus-within]:ring-2 has-[select:focus-within]:ring-primary/15
                    has-[input[type=tel]:focus]:border-primary has-[input[type=tel]:focus]:ring-2 has-[input[type=tel]:focus]:ring-primary/15">

            @if ($isSingleCountry)
                {{-- Static label — no dropdown --}}
                <div class="flex items-center rounded-l-2xl px-4 py-3 text-base font-medium text-slate-500">
                    {{ $selectedCountryLabel }}
                </div>
                <input type="hidden" name="{{ $countryName }}" value="{{ $selectedCountryCode }}" data-phone-country-static />
            @else
                {{-- Grid overlay pattern: select + chevron in the same grid cell --}}
                <div class="grid shrink-0 grid-cols-1 focus-within:relative">
                    <select
                        name="{{ $countryName }}"
                        @if (filled($countryModel)) wire:model.live="{{ $countryModel }}" @endif
                        class="col-start-1 row-start-1 h-full w-full appearance-none rounded-l-2xl border-0 bg-transparent py-3 pl-4 pr-10 text-base font-medium text-ink outline-none focus:ring-0"
                        data-phone-country
                    >
                        @foreach ($resolvedCountries as $value => $optionLabel)
                            <option value="{{ $value }}" @selected($selectedCountryCode === $value)>{{ $optionLabel }}</option>
                        @endforeach
                    </select>
                    <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"
                         class="pointer-events-none col-start-1 row-start-1 mr-3 size-4 self-center justify-self-end text-slate-400">
                        <path d="M4.22 6.22a.75.75 0 0 1 1.06 0L8 8.94l2.72-2.72a.75.75 0 1 1 1.06 1.06l-3.25 3.25a.75.75 0 0 1-1.06 0L4.22 7.28a.75.75 0 0 1 0-1.06Z"
                              clip-rule="evenodd" fill-rule="evenodd" />
                    </svg>
                </div>
            @endif

            <input
                name="{{ $phoneName }}"
                type="tel"
                value="{{ $formattedPhoneValue }}"
                @if (filled($phoneModel)) wire:model="{{ $phoneModel }}" @endif
                @if ($required) required @endif
                @if ($autofocus) autofocus @endif
                autocomplete="{{ $autocomplete }}"
                placeholder="{{ $phonePlaceholder }}"
                class="block min-w-0 grow rounded-r-2xl border-0 bg-transparent px-4 py-3 text-base text-ink placeholder:text-slate-400 outline-none focus:ring-0 {{ $inputClass }}"
                data-phone-input
            />
        </div>
    @endif
</div>
