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
        Schema::create('payment_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('external_id');
            $table->string('source_system');
            $table->json('customer_payload')->comment('Snapshot de los datos de cliente enviados por el sistema externo');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('MXN');
            $table->string('concept')->nullable();
            $table->string('payment_method')->nullable();
            $table->date('payment_date')->nullable();
            $table->enum('status', ['pending', 'invoiced', 'canceled', 'failed'])->default('pending');
            $table->unsignedBigInteger('invoice_id')->nullable()->index()->comment('FK a invoices, agregada en su propia migración por orden de creación');
            $table->timestamp('received_at')->useCurrent();
            $table->timestamps();

            $table->unique(['source_system', 'external_id']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_receipts');
    }
};
