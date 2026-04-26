@props([
    'label' => 'Téléphone',
    'countryName' => 'country_code',
    'countryValue' => 'SN',
    'phoneName' => 'phone',
    'phoneValue' => '',
    'countries' => null,
    'countryModel' => null,
    'phoneModel' => null,
    'required' => false,
    'autocomplete' => 'tel',
    'autofocus' => false,
    'inputClass' => '',
    'containerClass' => 'flex items-stretch rounded-2xl border border-slate-300 bg-white transition has-[:focus]:border-primary has-[:focus]:ring-2 has-[:focus]:ring-primary/15',
    'textSize' => 'text-base',
    'placeholderClass' => 'placeholder:text-slate-400',
    'readonly' => false,
    'showLabel' => true,
])

@php
    $allCountries = config('fayeku.phone_countries', []);

    // Resolve which countries to show.
    // Accepts: null (→ SN+CI default), ['SN','CI'] (list of codes),
    // or legacy ['SN' => 'SEN (+221)', ...] (label map — extracts keys).
    if (is_null($countries) || empty($countries)) {
        $resolvedCountries = collect($allCountries)->only(['SN', 'CI'])->all();
    } else {
        $codes = array_is_list($countries) ? $countries : array_keys($countries);
        $resolvedCountries = collect($allCountries)->only($codes)->all();
    }

    $isSingleCountry = count($resolvedCountries) === 1;
    $selectedCountryCode = array_key_exists((string) $countryValue, $resolvedCountries)
        ? (string) $countryValue
        : (string) array_key_first($resolvedCountries);
    $selectedCountryData = $resolvedCountries[$selectedCountryCode];

    // Generic phone formatter using a format string (e.g. '+221 XX XXX XX XX').
    $formatPhone = static function (string $country, string $phone) use ($resolvedCountries): string {
        $data = $resolvedCountries[$country] ?? null;

        if (! $data || empty($data['format'])) {
            return preg_replace('/\D+/', '', $phone) ?? '';
        }

        $localPattern = (string) preg_replace('/^\+\d+\s*/', '', $data['format']);
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        // Strip country prefix digits if present (e.g. '221' from '+221771234567').
        $prefixDigits = preg_replace('/\D+/', '', $data['prefix']) ?? '';
        if ($prefixDigits !== '' && str_starts_with($digits, $prefixDigits)) {
            $digits = substr($digits, strlen($prefixDigits));
        }

        $maxDigits = substr_count($localPattern, 'X');
        $digits = substr($digits, 0, $maxDigits);

        $result = '';
        $di = 0;
        $digitsLen = strlen($digits);

        for ($i = 0; $i < strlen($localPattern) && $di < $digitsLen; $i++) {
            $result .= $localPattern[$i] === 'X' ? $digits[$di++] : $localPattern[$i];
        }

        return $result;
    };

    $formattedPhoneValue = $formatPhone($selectedCountryCode, (string) $phoneValue);

    // Build country data array for Alpine.js (only needed in editable mode).
    $countriesData = array_values(array_map(
        static fn ($code, $data) => [
            'code'   => $code,
            'name'   => $data['name'],
            'label'  => $data['label'],
            'prefix' => $data['prefix'],
            'format' => $data['format'],
        ],
        array_keys($resolvedCountries),
        array_values($resolvedCountries),
    ));
@endphp

<div {{ $attributes->class(['space-y-1']) }} data-phone-field>
    @if ($showLabel)
        <span class="mb-1.5 block text-sm font-medium text-slate-700">
            {{ $label }}@if ($required)<span class="text-ink"> *</span>@endif
        </span>
    @endif

    @if ($readonly)
        {{-- Read-only display --}}
        <div class="flex cursor-not-allowed items-stretch rounded-2xl border border-slate-200 bg-slate-50">
            <div class="flex items-center rounded-l-2xl px-4 py-3 text-base font-medium text-ink">
                {{ $selectedCountryData['label'] }}
            </div>
            <div class="flex min-h-[3.25rem] min-w-0 grow items-center px-4 py-3 text-base text-ink">
                {{ filled($phoneValue) ? $formattedPhoneValue : '—' }}
            </div>
        </div>
    @else
        {{-- Editable — Alpine.js powered --}}
        <div
            x-data="phoneInput({
                initialCountry: @js($selectedCountryCode),
                initialPhone: @js($formattedPhoneValue),
                countries: {{ Js::from($countriesData) }},
                countryModel: @js($countryModel),
                phoneModel: @js($phoneModel),
            })"
            @click.outside="open = false"
            class="relative"
        >
            <div class="{{ $containerClass }}">
                @if ($isSingleCountry)
                    {{-- Single country: static label --}}
                    <div class="flex items-center rounded-l-2xl px-4 py-3 {{ $textSize }} font-medium text-slate-500">
                        <span x-text="selected?.label ?? @js($selectedCountryData['label'])"></span>
                    </div>
                    <input type="hidden" name="{{ $countryName }}" :value="country" />
                @else
                    {{-- Multi-country: searchable dropdown --}}
                    <div class="relative shrink-0">
                        <button
                            type="button"
                            @click="open = !open"
                            class="flex h-full items-center gap-1.5 rounded-l-2xl px-4 py-3 {{ $textSize }} font-medium text-ink hover:bg-slate-50 focus:outline-none"
                            :aria-expanded="open"
                        >
                            <span x-text="selected?.label ?? '---'"></span>
                            <svg
                                viewBox="0 0 16 16"
                                fill="currentColor"
                                aria-hidden="true"
                                class="size-4 shrink-0 text-slate-400 transition-transform duration-150"
                                :class="{ 'rotate-180': open }"
                            >
                                <path
                                    d="M4.22 6.22a.75.75 0 0 1 1.06 0L8 8.94l2.72-2.72a.75.75 0 1 1 1.06 1.06l-3.25 3.25a.75.75 0 0 1-1.06 0L4.22 7.28a.75.75 0 0 1 0-1.06Z"
                                    clip-rule="evenodd"
                                    fill-rule="evenodd"
                                />
                            </svg>
                        </button>

                        {{-- Dropdown panel --}}
                        <div
                            x-show="open"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="absolute left-0 top-full z-50 mt-1 min-w-56 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg"
                            style="display: none"
                        >
                            {{-- Search input --}}
                            <div class="border-b border-slate-100 p-2">
                                <input
                                    x-model="search"
                                    x-ref="searchInput"
                                    type="text"
                                    placeholder="Rechercher un pays..."
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-primary"
                                />
                            </div>

                            {{-- Country list --}}
                            <ul class="max-h-52 overflow-y-auto py-1">
                                <template x-for="c in filtered" :key="c.code">
                                    <li
                                        @click="selectCountry(c.code)"
                                        class="cursor-pointer px-3 py-2 text-sm hover:bg-slate-50"
                                        :class="{ 'bg-primary/5 font-medium text-primary': c.code === country }"
                                    >
                                        <span x-text="c.name" class="font-medium"></span>
                                        <span x-text="' (' + c.prefix + ')'" class="ml-1 text-slate-400"></span>
                                    </li>
                                </template>
                                <li
                                    x-show="filtered.length === 0"
                                    class="px-3 py-2 text-center text-sm text-slate-400"
                                >
                                    Aucun pays trouvé
                                </li>
                            </ul>
                        </div>

                        {{-- Hidden input for country (form submit + Alpine binding) --}}
                        <input
                            type="hidden"
                            name="{{ $countryName }}"
                            x-ref="countryInput"
                            :value="country"
                        />
                    </div>
                @endif

                {{-- Phone number input --}}
                <input
                    x-ref="phoneInput"
                    name="{{ $phoneName }}"
                    type="tel"
                    inputmode="numeric"
                    :value="phone"
                    :placeholder="placeholder"
                    @if (filled($phoneModel)) wire:model="{{ $phoneModel }}" @endif
                    @if ($required) required @endif
                    @if ($autofocus) autofocus @endif
                    autocomplete="{{ $autocomplete }}"
                    @input="onPhoneInput"
                    class="block min-w-0 grow rounded-r-2xl border-0 bg-transparent px-4 py-3 {{ $textSize }} text-ink {{ $placeholderClass }} outline-none focus:ring-0 {{ $inputClass }}"
                />
            </div>
        </div>
    @endif
</div>
