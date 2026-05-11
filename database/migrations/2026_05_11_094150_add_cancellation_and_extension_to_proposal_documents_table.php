<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposal_documents', function (Blueprint $table) {
            $table->datetime('cancelled_at')->nullable()->after('declined_at');
            $table->text('cancellation_reason')->nullable()->after('cancelled_at');
            $table->datetime('archived_at')->nullable()->after('cancellation_reason');
            $table->datetime('validity_extended_at')->nullable()->after('archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('proposal_documents', function (Blueprint $table) {
            $table->dropColumn([
                'cancelled_at',
                'cancellation_reason',
                'archived_at',
                'validity_extended_at',
            ]);
        });
    }
};
