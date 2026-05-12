<x-layouts.marketing
    :meta-title="$metaTitle"
    :meta-description="$metaDescription"
    :canonical-url="$canonicalUrl"
    :page-type="$pageType ?? 'article'"
    :breadcrumbs="$breadcrumbs ?? []"
>

<article class="bg-white">
    <header class="pt-12 lg:pt-20 pb-10 hero-bg">
        <div class="max-w-3xl mx-auto px-5 lg:px-8">
            <nav class="text-sm mb-6" style="color: var(--color-marketing-slate);" aria-label="Fil d’Ariane">
                <a href="{{ route('marketing.blog.index') }}" class="hover:underline">Blog</a>
                <span class="mx-2">›</span>
                <a href="{{ route('marketing.blog.index', ['category' => $article->category]) }}" class="hover:underline">{{ str_replace('-', ' ', $article->category) }}</a>
            </nav>
            <h1 class="h1 mb-6">{{ $article->title }}</h1>
            <div class="flex flex-wrap items-center gap-4 text-sm" style="color: var(--color-marketing-slate);">
                <span>Par {{ $article->author_name }}</span>
                <span>·</span>
                <time datetime="{{ optional($article->published_at)->toIso8601String() }}">
                    {{ optional($article->published_at)->isoFormat('LL') }}
                </time>
                <span>·</span>
                <span>{{ $article->reading_time_minutes }} min de lecture</span>
            </div>
        </div>
    </header>

    @if ($article->cover_image)
        <div class="max-w-4xl mx-auto px-5 lg:px-8 -mt-2 mb-10">
            <img src="{{ $article->cover_image }}" alt="{{ $article->cover_alt ?? $article->title }}" class="w-full h-auto rounded-2xl shadow-lg" />
        </div>
    @endif

    <div class="max-w-3xl mx-auto px-5 lg:px-8 py-8 prose-fayeku">
        {!! $article->body_html !!}
    </div>

    <div class="max-w-3xl mx-auto px-5 lg:px-8 my-12 p-8 rounded-2xl text-center" style="background: var(--color-mint-50);">
        <h2 class="text-2xl font-bold mb-3" style="color: var(--color-marketing-ink);">Prêt à essayer Fayeku ?</h2>
        <p class="mb-6" style="color: var(--color-marketing-slate);">2 mois d’essai sur tous les plans. Sans engagement.</p>
        <div class="flex flex-wrap justify-center gap-3">
            <a href="/contact" class="btn-primary">Démarrer 2 mois d’essai</a>
            <a href="/pricing" class="btn-secondary">Voir les tarifs</a>
        </div>
    </div>

    @if ($related->isNotEmpty())
        <section class="max-w-7xl mx-auto px-5 lg:px-8 py-12">
            <h2 class="text-2xl font-bold mb-8" style="color: var(--color-marketing-ink);">Lire aussi</h2>
            <div class="grid md:grid-cols-3 gap-6">
                @foreach ($related as $rel)
                    <article class="bg-white rounded-2xl border border-gray-100 p-6">
                        <p class="text-xs uppercase tracking-wide font-semibold mb-2" style="color: var(--color-teal-fayeku);">
                            {{ str_replace('-', ' ', $rel->category) }}
                        </p>
                        <h3 class="text-base font-bold mb-2" style="color: var(--color-marketing-ink);">
                            <a href="{{ route('marketing.blog.show', $rel) }}" class="hover:underline">{{ $rel->title }}</a>
                        </h3>
                        <p class="text-sm leading-relaxed" style="color: var(--color-marketing-slate);">{{ $rel->excerpt }}</p>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</article>

@if ($article->is_published)
    <script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $article->title,
        'description' => $article->meta_description ?? $article->excerpt,
        'datePublished' => optional($article->published_at)->toIso8601String(),
        'dateModified' => optional($article->updated_at)->toIso8601String(),
        'author' => [
            '@type' => 'Organization',
            'name' => $article->author_name,
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => config('marketing.site.name'),
            'logo' => [
                '@type' => 'ImageObject',
                'url' => rtrim((string) config('marketing.site.url'), '/').'/logo.png',
            ],
        ],
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id' => $article->url,
        ],
        'image' => $article->cover_image ?: rtrim((string) config('marketing.site.url'), '/').'/og-image.png',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endif

</x-layouts.marketing>
