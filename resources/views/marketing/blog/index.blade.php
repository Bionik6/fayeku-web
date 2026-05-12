<x-layouts.marketing
    :meta-title="$metaTitle"
    :meta-description="$metaDescription"
    :canonical-url="$canonicalUrl"
    :page-type="$pageType ?? 'blog'"
    :breadcrumbs="$breadcrumbs ?? []"
>

<section class="hero-bg relative overflow-hidden">
    <div class="max-w-7xl mx-auto px-5 lg:px-8 pt-12 pb-10 lg:pt-20 lg:pb-12 relative">
        <p class="eyebrow mb-3">Blog Fayeku</p>
        <h1 class="h1 mb-4">Guides pour PME et cabinets comptables au Sénégal</h1>
        <p class="text-lg max-w-2xl leading-relaxed" style="color: var(--color-marketing-slate);">
            Facturation, recouvrement WhatsApp, conformité DGID, trésorerie : les bonnes pratiques pour reprendre le contrôle de votre cash.
        </p>
    </div>
</section>

<section class="py-12 bg-white">
    <div class="max-w-7xl mx-auto px-5 lg:px-8">
        @if ($categories->isNotEmpty())
            <div class="flex flex-wrap gap-2 mb-10">
                <a href="{{ route('marketing.blog.index') }}" class="px-4 py-2 rounded-full text-sm font-semibold transition {{ $activeCategory === '' ? 'bg-teal-900 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">Tous</a>
                @foreach ($categories as $cat)
                    <a href="{{ route('marketing.blog.index', ['category' => $cat]) }}"
                       class="px-4 py-2 rounded-full text-sm font-semibold transition {{ $activeCategory === $cat ? 'bg-teal-900 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                        {{ ucfirst(str_replace('-', ' ', $cat)) }}
                    </a>
                @endforeach
            </div>
        @endif

        @if ($articles->isEmpty())
            <p class="text-center py-12" style="color: var(--color-marketing-slate);">Aucun article publié pour le moment.</p>
        @else
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($articles as $article)
                    <article class="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm hover:shadow-md transition flex flex-col">
                        @if ($article->cover_image)
                            <img src="{{ $article->cover_image }}" alt="{{ $article->cover_alt ?? $article->title }}" class="w-full h-48 object-cover" loading="lazy" />
                        @else
                            <div class="w-full h-48" style="background: linear-gradient(135deg, var(--color-mint-100), var(--color-mint-200));"></div>
                        @endif
                        <div class="p-6 flex-1 flex flex-col">
                            <p class="text-xs uppercase tracking-wide font-semibold mb-2" style="color: var(--color-teal-fayeku);">
                                {{ str_replace('-', ' ', $article->category) }} · {{ $article->reading_time_minutes }} min
                            </p>
                            <h2 class="text-lg font-bold mb-2" style="color: var(--color-marketing-ink);">
                                <a href="{{ route('marketing.blog.show', $article) }}" class="hover:underline">{{ $article->title }}</a>
                            </h2>
                            <p class="text-sm leading-relaxed flex-1" style="color: var(--color-marketing-slate);">{{ $article->excerpt }}</p>
                            <div class="mt-4 text-xs" style="color: var(--color-marketing-slate);">
                                {{ optional($article->published_at)->isoFormat('LL') }}
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-10">
                {{ $articles->links() }}
            </div>
        @endif
    </div>
</section>

</x-layouts.marketing>
