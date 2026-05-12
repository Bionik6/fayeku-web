<?php

namespace Database\Seeders\Marketing;

use App\Models\Marketing\Article;
use Illuminate\Database\Seeder;

class BlogSeeder extends Seeder
{
    public function run(): void
    {
        $articles = require database_path('seeders/Marketing/pillar-articles.php');

        foreach ($articles as $index => $article) {
            Article::updateOrCreate(
                ['slug' => $article['slug']],
                array_merge($article, [
                    'published_at' => now()->subDays(count($articles) - $index)->setTime(9, 0),
                    'is_published' => true,
                    'author_name' => $article['author_name'] ?? 'Équipe Fayeku',
                ])
            );
        }
    }
}
