@props([
    'value' => 'sme',
    'name' => 'profile',
])

@php
    $current = old($name, $value);

    $options = [
        'sme' => [
            'icon' => 'building-storefront',
            'title' => __('Espace PME'),
            'description' => __('Pour une PME qui se connecte ou récupère son compte.'),
        ],
        'accountant' => [
            'icon' => 'briefcase',
            'title' => __('Cabinet Comptable'),
            'description' => __('Pour un comptable qui gère son cabinet.'),
        ],
    ];
@endphp

<fieldset>
    <legend class="text-sm font-semibold text-ink">{{ __('Vous êtes') }}</legend>
    <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2">
        @foreach ($options as $key => $option)
            <label
                class="group relative flex cursor-pointer rounded-xl border border-slate-200 bg-white p-4 transition has-checked:border-primary has-checked:bg-primary/5 has-checked:outline-2 has-checked:-outline-offset-2 has-checked:outline-primary has-focus-visible:outline-3 has-focus-visible:-outline-offset-1 has-focus-visible:outline-primary"
            >
                <input
                    type="radio"
                    name="{{ $name }}"
                    value="{{ $key }}"
                    x-model="profile"
                    @if ($current === $key) checked @endif
                    class="absolute inset-0 appearance-none focus:outline-none"
                />
                <div class="flex flex-1 items-start gap-3">
                    <flux:icon
                        :name="$option['icon']"
                        class="size-6 shrink-0 text-slate-400 group-has-checked:text-primary"
                    />
                    <div>
                        <span class="block text-sm font-semibold text-ink">{{ $option['title'] }}</span>
                        <span class="mt-0.5 block text-xs leading-snug text-slate-500">{{ $option['description'] }}</span>
                    </div>
                </div>
                <flux:icon
                    name="check-circle"
                    class="invisible size-5 shrink-0 text-primary group-has-checked:visible"
                />
            </label>
        @endforeach
    </div>
    <x-auth-field-error :name="$name" />
</fieldset>
