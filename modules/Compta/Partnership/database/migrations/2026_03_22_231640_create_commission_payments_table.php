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
        Schema::create('commission_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('accountant_firm_id');
            $table->date('period_month');
            $table->unsignedInteger('active_clients_count');
            $table->unsignedInteger('amount');
            $table->date('paid_at')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->foreign('accountant_firm_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->index(['accountant_firm_id', 'period_month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_payments');
    }
};
