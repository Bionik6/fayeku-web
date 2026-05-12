<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 255)->nullable();
            $table->string('excerpt', 320)->nullable();
            $table->string('cover_image')->nullable();
            $table->string('cover_alt')->nullable();
            $table->string('author_name')->default('Équipe Fayeku');
            $table->string('category')->index();
            $table->json('tags')->nullable();
            $table->mediumText('body');
            $table->unsignedInteger('reading_time_minutes')->default(5);
            $table->boolean('is_published')->default(false)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
