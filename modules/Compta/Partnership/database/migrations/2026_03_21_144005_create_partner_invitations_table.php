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
            $table->string('token')->unique();
            $table->string('invitee_phone')->nullable();
            $table->string('invitee_name')->nullable();
            $table->string('recommended_plan')->nullable();
            $table->string('status')->default('pending');
            $table->datetime('expires_at')->nullable();
            $table->datetime('accepted_at')->nullable();
            $table->string('sme_company_id')->nullable();
            $table->timestamps();

            $table->foreign('accountant_firm_id')->references('id')->on('companies');
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
