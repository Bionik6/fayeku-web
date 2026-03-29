<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->string('type')->default('service')->after('description');
        });

        Schema::table('quote_lines', function (Blueprint $table) {
            $table->string('type')->default('service')->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('quote_lines', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
