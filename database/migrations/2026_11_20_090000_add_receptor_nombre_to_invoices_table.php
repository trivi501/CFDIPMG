<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nombre con el que se emitió cada factura. Con el RFC genérico XAXX010101000 todas comparten el
 * mismo Customer, así que su legal_name no dice a quién se facturó: cada apoyo a público en general
 * lleva el nombre de su beneficiario. Solo agrega una columna; las facturas anteriores la dejan en
 * null y se siguen mostrando con el nombre del cliente, que es con el que quedaron timbradas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('receptor_nombre', 300)->nullable()->after('customer_id');
        });
    }

    public function down(): void
    {
        // Quitar la columna perdería con qué nombre se emitió cada factura: no se hace.
    }
};
