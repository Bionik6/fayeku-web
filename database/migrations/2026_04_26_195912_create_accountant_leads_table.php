<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accountant_leads', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('firm', 200);
            $table->string('email');
            $table->char('country_code', 2);
            $table->string('phone', 30);
            $table->string('region', 50);
            $table->string('portfolio_size', 50);
            $table->text('message');

            $table->string('source', 50)->default('organic');
            $table->string('status', 30)->default('new');
            $table->text('notes')->nullable();
            $table->timestamp('contacted_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejected_reason')->nullable();

            $table->string('user_id')->nullable();
            $table->foreignUlid('company_id')->nullable()->constrained('companies')->nullOnDelete();

            $table->string('activation_token_hash', 64)->nullable();
            $table->timestamp('activation_token_expires_at')->nullable();

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('status');
            $table->index('email');
            $table->index('activation_token_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accountant_leads');
    }
};
