<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_documents', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('public_code', 8)->unique();
            $table->string('company_id');
            $table->string('client_id')->nullable();
            $table->string('type', 16);
            $table->string('reference')->nullable();
            $table->string('currency', 3)->default('XOF');
            $table->string('status')->default('draft');
            $table->date('issued_at')->nullable();
            $table->date('valid_until')->nullable();
            $table->integer('subtotal')->default(0);
            $table->integer('tax_amount')->default(0);
            $table->integer('total')->default(0);
            $table->integer('discount')->default(0);
            $table->string('discount_type')->default('percent');
            $table->text('notes')->nullable();
            // Champs spécifiques aux proformas — laissés nullable pour les devis.
            $table->string('dossier_reference')->nullable();
            $table->string('payment_terms')->nullable();
            $table->string('delivery_terms')->nullable();
            $table->string('po_reference')->nullable();
            $table->date('po_received_at')->nullable();
            $table->text('po_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('companies');
            $table->index(['company_id', 'type', 'status']);
        });

        Schema::create('proposal_document_lines', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('proposal_document_id');
            $table->string('description');
            $table->integer('quantity')->default(1);
            $table->integer('unit_price')->default(0);
            $table->integer('tax_rate')->default(0);
            $table->integer('discount')->default(0);
            $table->integer('total')->default(0);
            $table->timestamps();

            $table->foreign('proposal_document_id')
                ->references('id')->on('proposal_documents')
                ->cascadeOnDelete();
        });

        // La colonne `invoices.proposal_document_id` est déclarée dans la migration
        // d'invoices (qui tourne avant celle-ci). On ajoute la contrainte FK ici,
        // une fois que `proposal_documents` existe.
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('proposal_document_id')
                ->references('id')->on('proposal_documents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['proposal_document_id']);
        });
        Schema::dropIfExists('proposal_document_lines');
        Schema::dropIfExists('proposal_documents');
    }
};
