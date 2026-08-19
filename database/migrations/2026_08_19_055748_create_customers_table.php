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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('legal_name');
            $table->string('rfc', 13);
            $table->string('tax_system', 3)->comment('Clave SAT c_RegimenFiscal');
            $table->string('email')->nullable();
            $table->string('zip', 5)->comment('Código postal fiscal (c_CodigoPostal)');
            $table->json('address')->nullable();
            $table->string('facturapi_customer_id')->nullable()->index();
            $table->timestamps();

            $table->unique('rfc');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
