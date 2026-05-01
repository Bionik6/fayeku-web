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
        if (Schema::hasTable('commissions')) {
            return;
        }

        Schema::create('commissions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('accountant_firm_id');
            $table->string('sme_company_id');
            $table->string('subscription_id')->nullable();
            $table->integer('amount')->default(0);
            $table->date('period_month');
            $table->string('status')->default('pending');
            $table->datetime('paid_at')->nullable();
            $table->timestamps();

            // Cascade : la suppression d'un cabinet ou d'une PME nettoie ses commissions.
            $table->foreign('accountant_firm_id')
                ->references('id')->on('companies')
                ->cascadeOnDelete();
            $table->foreign('sme_company_id')
                ->references('id')->on('companies')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
