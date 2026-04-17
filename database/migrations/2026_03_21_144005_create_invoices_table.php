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
        if (Schema::hasTable('invoices')) {
            return;
        }

        Schema::create('invoices', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('company_id');
            $table->string('client_id')->nullable();
            $table->string('quote_id')->nullable();
            $table->string('reference')->nullable();
            $table->string('status')->default('draft');
            $table->string('currency', 3)->default('XOF');
            $table->date('issued_at')->nullable();
            $table->date('due_at')->nullable();
            $table->datetime('paid_at')->nullable();
            $table->integer('subtotal')->default(0);
            $table->integer('tax_amount')->default(0);
            $table->integer('total')->default(0);
            $table->integer('discount')->default(0);
            $table->string('discount_type', 10)->default('percent');
            $table->integer('amount_paid')->default(0);
            $table->text('notes')->nullable();
            $table->text('payment_terms')->nullable();
            $table->text('payment_instructions')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_details')->nullable();
            $table->boolean('reminders_enabled')->default(true);
            $table->string('certification_authority')->nullable();
            $table->jsonb('certification_data')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('companies');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
