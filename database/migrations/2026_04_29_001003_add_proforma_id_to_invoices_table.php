<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('invoices', 'proforma_id')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('proforma_id')->nullable()->after('quote_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('proforma_id');
        });
    }
};
