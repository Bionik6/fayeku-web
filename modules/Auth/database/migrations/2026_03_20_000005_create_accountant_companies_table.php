<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accountant_companies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('accountant_firm_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('sme_company_id')->constrained('companies')->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('ended_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accountant_companies');
    }
};
