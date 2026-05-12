<?php

namespace Database\Factories\Marketing;

use App\Models\Marketing\Article;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    protected $model = Article::class;

    public function definition(): array
    {
        $title = $this->faker->sentence(6);

        return [
            'slug' => Str::slug($title).'-'.$this->faker->unique()->numberBetween(1000, 9999),
            'title' => $title,
            'meta_title' => $title.' | Fayeku',
            'meta_description' => $this->faker->sentence(20),
            'excerpt' => $this->faker->paragraph(2),
            'cover_image' => null,
            'cover_alt' => $title,
            'author_name' => 'Équipe Fayeku',
            'category' => $this->faker->randomElement(['facturation', 'cabinet-comptable', 'conformite', 'tresorerie']),
            'tags' => [],
            'body' => "## Introduction\n\n".$this->faker->paragraphs(5, true),
            'reading_time_minutes' => 5,
            'is_published' => true,
            'published_at' => now()->subDays($this->faker->numberBetween(1, 60)),
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn () => ['is_published' => false, 'published_at' => null]);
    }
}
