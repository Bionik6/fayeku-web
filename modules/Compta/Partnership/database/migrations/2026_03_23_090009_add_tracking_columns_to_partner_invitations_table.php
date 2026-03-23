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
            $table->string('invitee_company_name')->nullable()->after('invitee_name');
            $table->string('channel')->default('whatsapp')->after('recommended_plan');
            $table->datetime('link_opened_at')->nullable()->after('accepted_at');
            $table->datetime('last_reminder_at')->nullable()->after('link_opened_at');
            $table->unsignedInteger('reminder_count')->default(0)->after('last_reminder_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('partner_invitations', function (Blueprint $table) {
            $table->dropColumn(['invitee_company_name', 'channel', 'link_opened_at', 'last_reminder_at', 'reminder_count']);
        });
    }
};
