<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reminder_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('company_id');
            $table->string('name');
            $table->integer('trigger_days');
            $table->string('channel')->nullable();
            $table->text('template')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->index(['company_id', 'trigger_days']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_rules');
    }
};
