@props([
    'navigation',
    'legalLinks',
    'site',
])

<footer class="border-t border-primary/10 bg-white">
    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-16 sm:px-6 lg:grid-cols-[1.2fr_0.9fr_0.9fr_1.1fr] lg:px-8">
        <div class="space-y-4">
            <div>
                <p class="text-xl font-semibold text-primary">{{ $site['name'] }}</p>
                <p class="mt-2 max-w-sm text-sm leading-6 text-slate-600">{{ $site['description'] }}</p>
            </div>
            <p class="text-sm text-slate-500">Conçu à Dakar, pour le contexte sénégalais.</p>
        </div>

        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-teal">Navigation</p>
            <ul class="mt-4 space-y-3 text-sm text-slate-600">
                @foreach ($navigation as $item)
                    <li><a href="{{ $item['href'] }}" class="hover:text-primary">{{ $item['label'] }}</a></li>
                @endforeach
            </ul>
        </div>

        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-teal">Légal</p>
            <ul class="mt-4 space-y-3 text-sm text-slate-600">
                @foreach ($legalLinks as $item)
                    <li><a href="{{ $item['href'] }}" class="hover:text-primary">{{ $item['label'] }}</a></li>
                @endforeach
            </ul>
        </div>

        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-teal">Contact</p>
            <div class="mt-4 space-y-3 text-sm text-slate-600">
                <p>{{ $site['contact']['address'] }}</p>
                <p><a href="mailto:{{ $site['contact']['email'] }}" class="hover:text-primary">{{ $site['contact']['email'] }}</a></p>
                <div class="flex gap-4 pt-2">
                    <a href="{{ $site['social']['linkedin'] }}" class="hover:text-primary">LinkedIn</a>
                    <a href="{{ $site['social']['whatsapp'] }}" class="hover:text-primary">WhatsApp</a>
                    <a href="{{ $site['social']['x'] }}" class="hover:text-primary">X</a>
                </div>
            </div>
        </div>
    </div>

    <div class="border-t border-primary/10 px-4 py-5 text-center text-sm text-slate-500 sm:px-6 lg:px-8">
        &copy; {{ now()->year }} Fayeku. Tous droits réservés.
    </div>
</footer>
