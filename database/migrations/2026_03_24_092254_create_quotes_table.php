<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('company_id');
            $table->string('client_id')->nullable();
            $table->string('reference')->nullable();
            $table->string('currency', 3)->default('XOF');
            $table->string('status')->default('draft');
            $table->date('issued_at')->nullable();
            $table->date('valid_until')->nullable();
            $table->integer('subtotal')->default(0);
            $table->integer('tax_amount')->default(0);
            $table->integer('total')->default(0);
            $table->integer('discount')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('companies');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
