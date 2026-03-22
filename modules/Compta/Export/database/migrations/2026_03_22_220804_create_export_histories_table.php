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
        Schema::create('export_histories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('firm_id');
            $table->string('user_id');
            $table->string('period');
            $table->string('format');
            $table->string('scope')->default('all');
            $table->json('client_ids');
            $table->unsignedInteger('clients_count');
            $table->string('file_path')->nullable();
            $table->timestamps();

            $table->foreign('firm_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['firm_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('export_histories');
    }
};
