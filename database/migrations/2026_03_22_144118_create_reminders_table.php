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
        if (Schema::hasTable('reminders')) {
            return;
        }

        Schema::create('reminders', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('invoice_id');
            $table->string('channel')->nullable();
            $table->boolean('is_manual')->default(true);
            $table->timestamp('sent_at')->nullable();
            $table->text('message_body')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->string('recipient_email')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
