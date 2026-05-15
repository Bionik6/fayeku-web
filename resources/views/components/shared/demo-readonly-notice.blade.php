@props(['section' => null])

@if (config('fayeku.demo'))
    <div class="mt-4 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
        <svg class="mt-0.5 size-5 shrink-0 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
        </svg>
        <div class="flex-1 text-sm">
            <p class="font-semibold text-amber-900">{{ __('Édition désactivée en mode démonstration') }}</p>
            <p class="mt-0.5 text-xs text-amber-800">{{ __('Cette section est en lecture seule dans l\'environnement de démonstration. Vos modifications ne seront pas enregistrées.') }}</p>
        </div>
    </div>
@endif
