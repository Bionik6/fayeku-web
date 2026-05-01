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
        Schema::table('proformas', function (Blueprint $table) {
            // Référence du Bon de Commande émis par le client après la proforma.
            $table->string('po_reference')->nullable()->after('delivery_terms');
            $table->date('po_received_at')->nullable()->after('po_reference');
            $table->text('po_notes')->nullable()->after('po_received_at');
        });
    }

    public function down(): void
    {
        Schema::table('proformas', function (Blueprint $table) {
            $table->dropColumn(['po_reference', 'po_received_at', 'po_notes']);
        });
    }
};
