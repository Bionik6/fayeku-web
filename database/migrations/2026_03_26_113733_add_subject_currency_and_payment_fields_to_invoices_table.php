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
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('subject')->nullable()->after('reference');
            $table->string('currency', 3)->default('XOF')->after('subject');
            $table->text('payment_terms')->nullable()->after('notes');
            $table->text('payment_instructions')->nullable()->after('payment_terms');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['subject', 'currency', 'payment_terms', 'payment_instructions']);
        });
    }
};
