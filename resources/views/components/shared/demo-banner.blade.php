@if (config('fayeku.demo'))
    <div class="-mt-3 mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 lg:-mt-5">
        <div class="flex items-start gap-3">
            <div class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-600">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 0 1-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 0 1 4.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0 1 12 15a9.065 9.065 0 0 0-6.23-.693L5 14.5m14.8.8 1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0 1 12 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5" />
                </svg>
            </div>
            <div class="flex-1">
                <p class="text-sm font-semibold text-amber-900">
                    {{ __('Mode démonstration actif') }}
                </p>
                <p class="mt-1 text-xs text-amber-800">
                    {{ __('Les données affichées sont fictives. Aucun envoi WhatsApp, SMS ou e-mail n\'est réellement effectué dans cet environnement de démonstration.') }}
                </p>
            </div>
        </div>
    </div>
@endif
