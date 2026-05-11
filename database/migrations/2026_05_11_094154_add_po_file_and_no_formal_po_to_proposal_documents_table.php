<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposal_documents', function (Blueprint $table) {
            $table->string('po_file_path')->nullable()->after('po_notes');
            $table->boolean('has_no_formal_po')->default(false)->after('po_file_path');
        });
    }

    public function down(): void
    {
        Schema::table('proposal_documents', function (Blueprint $table) {
            $table->dropColumn(['po_file_path', 'has_no_formal_po']);
        });
    }
};
