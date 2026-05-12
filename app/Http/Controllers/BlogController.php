<?php

namespace App\Http\Controllers;

use App\Models\Marketing\Article;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index(Request $request): View
    {
        $category = $request->string('category')->trim()->toString();

        $articles = Article::published()
            ->when($category !== '', fn ($q) => $q->where('category', $category))
            ->orderByDesc('published_at')
            ->paginate(9)
            ->withQueryString();

        $categories = Article::published()
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('marketing.blog.index', [
            'articles' => $articles,
            'categories' => $categories,
            'activeCategory' => $category,
            'metaTitle' => 'Blog Fayeku — Facturation, cabinet comptable & conformité au Sénégal',
            'metaDescription' => 'Le blog Fayeku : guides pratiques pour les PME et cabinets comptables au Sénégal — facturation, recouvrement WhatsApp, conformité DGID et trésorerie.',
            'canonicalUrl' => rtrim((string) config('marketing.site.url'), '/').'/blog',
            'pageType' => 'blog',
            'breadcrumbs' => [
                ['name' => 'Accueil', 'url' => config('marketing.site.url')],
                ['name' => 'Blog', 'url' => config('marketing.site.url').'/blog'],
            ],
            'navigation' => config('marketing.navigation'),
            'legalLinks' => config('marketing.legal_links'),
            'site' => config('marketing.site'),
        ]);
    }

    public function show(Article $article): View
    {
        abort_unless($article->is_published && $article->published_at && $article->published_at->isPast(), 404);

        $related = Article::published()
            ->where('id', '!=', $article->id)
            ->where('category', $article->category)
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return view('marketing.blog.show', [
            'article' => $article,
            'related' => $related,
            'metaTitle' => $article->meta_title ?: $article->title.' | Fayeku',
            'metaDescription' => $article->meta_description ?: $article->excerpt,
            'canonicalUrl' => $article->url,
            'pageType' => 'article',
            'breadcrumbs' => [
                ['name' => 'Accueil', 'url' => config('marketing.site.url')],
                ['name' => 'Blog', 'url' => config('marketing.site.url').'/blog'],
                ['name' => $article->title, 'url' => $article->url],
            ],
            'navigation' => config('marketing.navigation'),
            'legalLinks' => config('marketing.legal_links'),
            'site' => config('marketing.site'),
        ]);
    }
}
