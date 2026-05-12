<x-layouts.marketing
    :meta-title="$metaTitle"
    :meta-description="$metaDescription"
    :canonical-url="$canonicalUrl"
    :page-type="$pageType ?? null"
    :breadcrumbs="$breadcrumbs ?? []"
    :faq-items="$faqItems ?? []"
    :noindex="$noindex ?? false"
>

<section class="hero-bg relative overflow-hidden">
    <div class="absolute -top-24 right-0 w-[480px] h-[480px] rounded-full blur-3xl opacity-50 -z-0" style="background: var(--color-mint-200);"></div>
    <div class="max-w-7xl mx-auto px-5 lg:px-8 pt-12 pb-12 sm:pt-16 sm:pb-16 lg:pt-24 lg:pb-24 grid lg:grid-cols-12 gap-10 items-center relative">
        <div class="lg:col-span-7">
            <p class="eyebrow mb-4">{{ $landing['eyebrow'] }}</p>
            <h1 class="h1 mb-6">{{ $landing['h1'] }}</h1>
            <p class="text-lg max-w-2xl mb-8 leading-relaxed" style="color: var(--color-marketing-slate);">
                {{ $landing['subtitle'] }}
            </p>
            <div class="flex flex-wrap gap-3 mb-6">
                <a href="{{ $landing['cta_primary']['href'] }}" class="btn-primary">
                    {{ $landing['cta_primary']['label'] }}
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </a>
                <a href="{{ $landing['cta_secondary']['href'] }}" class="btn-secondary">{{ $landing['cta_secondary']['label'] }}</a>
            </div>
            @if (! empty($landing['proof_points']))
                <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm" style="color: var(--color-marketing-slate);">
                    @foreach ($landing['proof_points'] as $point)
                        <span class="flex items-center gap-2">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0FB85C" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            {{ $point }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="lg:col-span-5">
            <img src="/hero-dashboard.svg" alt="{{ $landing['h1'] }}" class="w-full h-auto rounded-2xl shadow-lg" loading="lazy" width="800" height="600" />
        </div>
    </div>
</section>

@if (! empty($landing['problems']))
<section class="py-16 lg:py-20 bg-white">
    <div class="max-w-5xl mx-auto px-5 lg:px-8">
        <h2 class="text-3xl lg:text-4xl font-bold mb-4 text-center" style="color: var(--color-marketing-ink);">Le quotidien d’une PME sans Fayeku</h2>
        <p class="text-center max-w-2xl mx-auto mb-10" style="color: var(--color-marketing-slate);">Si vous reconnaissez l’une de ces situations, vous gagnerez du temps et du cash avec Fayeku.</p>
        <ul class="grid sm:grid-cols-2 gap-4">
            @foreach ($landing['problems'] as $problem)
                <li class="flex items-start gap-3 p-4 bg-gray-50 rounded-xl">
                    <svg class="flex-shrink-0 mt-0.5" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    <span style="color: var(--color-marketing-ink);">{{ $problem }}</span>
                </li>
            @endforeach
        </ul>
    </div>
</section>
@endif

<section class="py-16 lg:py-24" style="background: var(--color-mint-50);">
    <div class="max-w-7xl mx-auto px-5 lg:px-8">
        <h2 class="text-3xl lg:text-4xl font-bold mb-12 text-center" style="color: var(--color-marketing-ink);">Ce que Fayeku change pour vous</h2>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach ($landing['features'] as $feature)
                <article class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-4" style="background: var(--color-mint-100); color: var(--color-teal-fayeku);">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                    <h3 class="text-lg font-bold mb-2" style="color: var(--color-marketing-ink);">{{ $feature['title'] }}</h3>
                    <p class="text-sm leading-relaxed" style="color: var(--color-marketing-slate);">{{ $feature['description'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

@if (! empty($landing['use_cases']))
<section class="py-16 lg:py-20 bg-white">
    <div class="max-w-6xl mx-auto px-5 lg:px-8">
        <h2 class="text-3xl lg:text-4xl font-bold mb-12 text-center" style="color: var(--color-marketing-ink);">Pour qui ?</h2>
        <div class="grid md:grid-cols-3 gap-6">
            @foreach ($landing['use_cases'] as $useCase)
                <div class="p-6 rounded-2xl border border-gray-100">
                    <h3 class="text-lg font-bold mb-2" style="color: var(--color-marketing-ink);">{{ $useCase['title'] }}</h3>
                    <p class="text-sm leading-relaxed" style="color: var(--color-marketing-slate);">{{ $useCase['description'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

@if (! empty($landing['faq']))
<section class="py-16 lg:py-24 bg-white" id="faq">
    <div class="max-w-3xl mx-auto px-5 lg:px-8">
        <h2 class="text-3xl lg:text-4xl font-bold mb-10 text-center" style="color: var(--color-marketing-ink);">Questions fréquentes</h2>
        <div class="space-y-4">
            @foreach ($landing['faq'] as $item)
                <details class="group bg-white border border-gray-100 rounded-xl p-5 shadow-sm">
                    <summary class="flex items-center justify-between cursor-pointer font-semibold" style="color: var(--color-marketing-ink);">
                        <span>{{ $item['question'] }}</span>
                        <svg class="transition-transform group-open:rotate-180" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                    </summary>
                    <p class="mt-3 text-sm leading-relaxed" style="color: var(--color-marketing-slate);">{{ $item['answer'] }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>
@endif

<section class="py-16 lg:py-24" style="background: var(--color-teal-fayeku);">
    <div class="max-w-4xl mx-auto px-5 lg:px-8 text-center text-white">
        <h2 class="text-3xl lg:text-4xl font-bold mb-4">Prêt à essayer Fayeku ?</h2>
        <p class="text-lg mb-8 opacity-90">2 mois d’essai inclus sur chaque plan. Sans engagement.</p>
        <div class="flex flex-wrap justify-center gap-3">
            <a href="{{ $landing['cta_primary']['href'] }}" class="btn-primary bg-white text-teal-900 hover:bg-gray-100">
                {{ $landing['cta_primary']['label'] }}
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </a>
            <a href="/pricing" class="btn-secondary border-white text-white hover:bg-white/10">Voir les tarifs</a>
        </div>
    </div>
</section>

</x-layouts.marketing>
