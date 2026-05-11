<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('invoice_id');
            $table->integer('amount');
            $table->boolean('is_deposit')->default(false);
            $table->datetime('paid_at');
            $table->string('method');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->string('proof_file_path')->nullable();
            $table->string('recorded_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->foreign('recorded_by')->references('id')->on('users')->nullOnDelete();

            $table->index('invoice_id');
            $table->index('is_deposit');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
