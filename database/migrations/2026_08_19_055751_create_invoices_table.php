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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_receipt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained();
            $table->string('facturapi_invoice_id')->nullable()->index();
            $table->string('uuid')->nullable()->index()->comment('Folio fiscal UUID del CFDI');
            $table->string('series')->nullable();
            $table->string('folio')->nullable();
            $table->enum('status', ['valid', 'canceled', 'failed'])->default('failed');
            $table->decimal('total', 12, 2);
            $table->string('pdf_url')->nullable();
            $table->string('xml_url')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();
        });

        Schema::table('payment_receipts', function (Blueprint $table) {
            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_receipts', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
        });

        Schema::dropIfExists('invoices');
    }
};
