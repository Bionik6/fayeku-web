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
        if (Schema::hasTable('invoice_lines')) {
            return;
        }

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('invoice_id');
            $table->string('description');
            $table->integer('quantity')->default(1);
            $table->integer('unit_price')->default(0);
            $table->integer('tax_rate')->default(0);  // en % (ex: 18 = 18 %)
            $table->integer('discount')->default(0);  // en %
            $table->integer('total')->default(0);     // HT après remise
            $table->timestamps();

            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
    }
};
