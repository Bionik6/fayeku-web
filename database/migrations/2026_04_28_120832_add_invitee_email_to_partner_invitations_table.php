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
        Schema::table('partner_invitations', function (Blueprint $table) {
            $table->string('invitee_email')->nullable()->after('invitee_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('partner_invitations', function (Blueprint $table) {
            $table->dropColumn('invitee_email');
        });
    }
};
