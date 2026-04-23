<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quota_usage', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->string('quota_type', 32);
            $table->date('period_start')->nullable();
            $table->unsignedInteger('used')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'quota_type', 'period_start']);
            $table->index(['company_id', 'quota_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quota_usage');
    }
};
