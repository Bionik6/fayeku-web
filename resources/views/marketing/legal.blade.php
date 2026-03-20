<x-layouts.marketing :meta-title="$metaTitle" :meta-description="$metaDescription" :canonical-url="$canonicalUrl">
    <x-marketing.page-hero
        eyebrow="Legal"
        :title="$legalPage['title']"
        :description="$legalPage['description']"
    />

    <section class="pb-24">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="space-y-6">
                @foreach ($legalPage['sections'] as $section)
                    <article class="rounded-[2rem] border border-primary/10 bg-white p-8 shadow-soft">
                        <h2 class="text-2xl font-semibold text-ink">{{ $section['title'] }}</h2>
                        <p class="mt-4 text-base leading-7 text-slate-600">{{ $section['body'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
</x-layouts.marketing>
