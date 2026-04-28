<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * partner_invitations and commissions originally referenced companies without
 * a cascade rule. When a SME company was deleted (PME closing their account),
 * the matching invitation/commission rows stayed orphaned and the cabinet
 * kept seeing the PME in /compta/invitations and /compta/commissions.
 *
 * This migration replaces both foreign keys by their cascadeOnDelete variants
 * so a Company deletion now cleans up the related rows automatically.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_invitations', function (Blueprint $table) {
            $table->dropForeign(['accountant_firm_id']);
            $table->foreign('accountant_firm_id')
                ->references('id')->on('companies')
                ->cascadeOnDelete();

            // sme_company_id was originally a plain string column with no FK.
            // Bind it now with cascade so PME account deletion cleans up the
            // cabinet's invitations dashboard.
            $table->foreign('sme_company_id')
                ->references('id')->on('companies')
                ->cascadeOnDelete();
        });

        Schema::table('commissions', function (Blueprint $table) {
            $table->dropForeign(['accountant_firm_id']);
            $table->dropForeign(['sme_company_id']);

            $table->foreign('accountant_firm_id')
                ->references('id')->on('companies')
                ->cascadeOnDelete();
            $table->foreign('sme_company_id')
                ->references('id')->on('companies')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('partner_invitations', function (Blueprint $table) {
            $table->dropForeign(['accountant_firm_id']);
            $table->dropForeign(['sme_company_id']);
            $table->foreign('accountant_firm_id')->references('id')->on('companies');
        });

        Schema::table('commissions', function (Blueprint $table) {
            $table->dropForeign(['accountant_firm_id']);
            $table->dropForeign(['sme_company_id']);
            $table->foreign('accountant_firm_id')->references('id')->on('companies');
            $table->foreign('sme_company_id')->references('id')->on('companies');
        });
    }
};
