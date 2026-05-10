<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->integer('deposit_amount')->default(0)->after('amount_paid');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->boolean('is_deposit')->default(false)->after('amount');
            $table->index('is_deposit');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['is_deposit']);
            $table->dropColumn('is_deposit');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('deposit_amount');
        });
    }
};
