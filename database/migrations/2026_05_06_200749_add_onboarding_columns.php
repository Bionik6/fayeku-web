<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('legal_form', 32)->nullable()->after('sector');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('onboarding_intent', 32)->nullable()->after('country_code');
            $table->timestamp('onboarding_checklist_dismissed_at')->nullable()->after('onboarding_intent');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('legal_form');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['onboarding_intent', 'onboarding_checklist_dismissed_at']);
        });
    }
};
