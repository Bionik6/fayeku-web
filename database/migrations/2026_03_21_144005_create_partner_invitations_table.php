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
        if (Schema::hasTable('partner_invitations')) {
            return;
        }

        Schema::create('partner_invitations', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('accountant_firm_id');
            $table->string('created_by_user_id')->nullable();
            $table->string('token')->unique();
            $table->string('invitee_phone')->nullable();
            $table->string('invitee_email')->nullable();
            $table->string('invitee_name')->nullable();
            $table->string('invitee_company_name')->nullable();
            $table->string('recommended_plan')->nullable();
            $table->string('channel')->default('whatsapp');
            $table->string('status')->default('pending');
            $table->datetime('expires_at')->nullable();
            $table->datetime('accepted_at')->nullable();
            $table->datetime('link_opened_at')->nullable();
            $table->datetime('last_reminder_at')->nullable();
            $table->unsignedInteger('reminder_count')->default(0);
            $table->string('sme_company_id')->nullable();
            $table->timestamps();

            // Cascade : la suppression d'un cabinet ou d'une PME nettoie ses invitations.
            $table->foreign('accountant_firm_id')
                ->references('id')->on('companies')
                ->cascadeOnDelete();
            $table->foreign('sme_company_id')
                ->references('id')->on('companies')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_invitations');
    }
};
