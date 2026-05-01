<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proforma_lines', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('proforma_id');
            $table->string('description');
            $table->integer('quantity')->default(1);
            $table->integer('unit_price')->default(0);
            $table->integer('tax_rate')->default(0);
            $table->integer('discount')->default(0);
            $table->integer('total')->default(0);
            $table->timestamps();

            $table->foreign('proforma_id')->references('id')->on('proformas')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proforma_lines');
    }
};
