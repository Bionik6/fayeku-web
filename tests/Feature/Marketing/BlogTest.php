<?php

declare(strict_types=1);

use App\Models\Marketing\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the blog index with published articles', function () {
    Article::factory()->count(3)->create();
    Article::factory()->unpublished()->create();

    $response = $this->get('/blog');

    $response->assertOk();
    $response->assertSee('Blog Fayeku', false);
});

it('shows a published article', function () {
    $article = Article::factory()->create([
        'title' => 'Mon titre unique',
        'slug' => 'mon-titre-unique',
    ]);

    $response = $this->get('/blog/'.$article->slug);

    $response->assertOk();
    $response->assertSee('Mon titre unique', false);
    expect($response->getContent())->toContain('"@type":"Article"');
});

it('returns 404 for an unpublished article', function () {
    $article = Article::factory()->unpublished()->create([
        'slug' => 'brouillon-non-publie',
    ]);

    $this->get('/blog/'.$article->slug)->assertNotFound();
});

it('filters the blog by category', function () {
    Article::factory()->create(['category' => 'facturation', 'title' => 'A facturation']);
    Article::factory()->create(['category' => 'tresorerie', 'title' => 'B tresorerie']);

    $response = $this->get('/blog?category=facturation');

    $response->assertOk();
    $response->assertSee('A facturation', false);
    $response->assertDontSee('B tresorerie', false);
});

it('paginates the blog index', function () {
    Article::factory()->count(11)->create();

    $response = $this->get('/blog');

    $response->assertOk();
    expect($response->getContent())->toContain('page=2');
});
