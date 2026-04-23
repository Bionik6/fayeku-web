<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['invoices', 'quotes'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('public_code', 8)->nullable()->after('id');
            });

            $this->backfill($table);

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('public_code', 8)->nullable(false)->change();
                $blueprint->unique('public_code');
            });
        }
    }

    public function down(): void
    {
        foreach (['invoices', 'quotes'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropUnique($table.'_public_code_unique');
                $blueprint->dropColumn('public_code');
            });
        }
    }

    private function backfill(string $table): void
    {
        $used = array_flip(
            DB::table($table)->whereNotNull('public_code')->pluck('public_code')->all()
        );

        DB::table($table)->whereNull('public_code')->orderBy('id')->each(function ($row) use ($table, &$used) {
            do {
                $code = Str::random(8);
            } while (isset($used[$code]));

            $used[$code] = true;

            DB::table($table)->where('id', $row->id)->update(['public_code' => $code]);
        });
    }
};
