<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('quote_id')->nullable()->after('client_id');
            $table->foreign('quote_id')->references('id')->on('quotes')->nullOnDelete();
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->string('currency', 3)->default('XOF')->after('reference');
            $table->integer('discount')->default(0)->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['quote_id']);
            $table->dropColumn('quote_id');
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn(['currency', 'discount']);
        });
    }
};
