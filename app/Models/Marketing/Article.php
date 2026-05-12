<?php

namespace App\Models\Marketing;

use Database\Factories\Marketing\ArticleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use League\CommonMark\CommonMarkConverter;

/**
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $excerpt
 * @property string|null $cover_image
 * @property string|null $cover_alt
 * @property string $author_name
 * @property string $category
 * @property array<int, string>|null $tags
 * @property string $body
 * @property int $reading_time_minutes
 * @property bool $is_published
 * @property Carbon|null $published_at
 */
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'tags' => 'array',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected static function newFactory(): ArticleFactory
    {
        return ArticleFactory::new();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function getBodyHtmlAttribute(): string
    {
        return (new CommonMarkConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]))->convert($this->body)->getContent();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getUrlAttribute(): string
    {
        return rtrim((string) config('marketing.site.url'), '/').'/blog/'.$this->slug;
    }
}
