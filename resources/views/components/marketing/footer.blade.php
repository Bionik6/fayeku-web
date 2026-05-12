@props([
    'navigation',
    'legalLinks',
    'site',
])

<footer class="border-t border-gray-100 bg-white">
    <div class="max-w-7xl mx-auto px-5 lg:px-8 py-14 grid md:grid-cols-2 lg:grid-cols-4 gap-10">
        <div>
            <div class="flex items-center gap-3 mb-4">
                <img src="/logo.png" alt="Fayeku" class="w-10 h-10 rounded-lg" width="40" height="40" />
                <div>
                    <div class="font-bold">{{ $site['name'] }}</div>
                    <div class="text-xs -mt-0.5" style="color: var(--color-marketing-slate);">Facturation &amp; trésorerie</div>
                </div>
            </div>
            <p class="text-sm leading-relaxed mb-4" style="color: var(--color-marketing-slate);">
                {{ $site['description'] }}
            </p>
            <p class="text-sm italic" style="color: var(--color-marketing-slate);">Conçu à Dakar, pour le contexte sénégalais.</p>
        </div>

        <div>
            <h4 class="font-semibold mb-4 text-sm tracking-wide uppercase" style="color: var(--color-marketing-ink);">Navigation</h4>
            <ul class="space-y-2 text-sm" style="color: var(--color-marketing-slate);">
                @foreach ($navigation as $item)
                    <li><a href="{{ $item['href'] }}" class="hover:text-[color:var(--color-teal-fayeku)]">{{ $item['label'] }}</a></li>
                @endforeach
            </ul>
        </div>

        <div>
            <h4 class="font-semibold mb-4 text-sm tracking-wide uppercase" style="color: var(--color-marketing-ink);">Légal</h4>
            <ul class="space-y-2 text-sm" style="color: var(--color-marketing-slate);">
                @foreach ($legalLinks as $item)
                    <li><a href="{{ $item['href'] }}" class="hover:text-[color:var(--color-teal-fayeku)]">{{ $item['label'] }}</a></li>
                @endforeach
            </ul>
        </div>

        <div>
            <h4 class="font-semibold mb-4 text-sm tracking-wide uppercase" style="color: var(--color-marketing-ink);">Contact</h4>
            <ul class="space-y-2 text-sm" style="color: var(--color-marketing-slate);">
                <li><a href="mailto:{{ $site['contact']['email'] }}" class="hover:text-[color:var(--color-teal-fayeku)]">{{ $site['contact']['email'] }}</a></li>
                @if (! empty($site['contact']['phone']))
                    <li><a href="tel:{{ preg_replace('/\s+/', '', $site['contact']['phone']) }}" class="hover:text-[color:var(--color-teal-fayeku)]">{{ $site['contact']['phone'] }}</a></li>
                @endif
            </ul>
            <div class="flex gap-3 mt-4 text-sm" style="color: var(--color-marketing-slate);">
                @if (! empty($site['social']['linkedin']))
                    <a href="{{ $site['social']['linkedin'] }}" class="hover:text-[color:var(--color-teal-fayeku)]">LinkedIn</a>
                @endif
                @if (! empty($site['social']['whatsapp']))
                    <a href="{{ $site['social']['whatsapp'] }}" class="hover:text-[color:var(--color-teal-fayeku)]">WhatsApp</a>
                @endif
                @if (! empty($site['social']['x']))
                    <a href="{{ $site['social']['x'] }}" class="hover:text-[color:var(--color-teal-fayeku)]">X</a>
                @endif
            </div>
        </div>
    </div>
    <div class="border-t border-gray-100">
        <div class="max-w-7xl mx-auto px-5 lg:px-8 py-6 text-center text-xs" style="color: var(--color-marketing-slate);">
            &copy; {{ now()->year }} {{ $site['name'] }}. Tous droits réservés.
        </div>
    </div>
</footer>
