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
        Schema::create('articulos_factura_compra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factura_compra_id')->constrained('facturas_compra')->cascadeOnDelete();
            $table->string('descripcion');
            $table->decimal('cantidad_total', 12, 2);
            $table->decimal('costo_unidad', 12, 2);
            $table->decimal('iva', 12, 2)->default(0);
            $table->decimal('cantidad_disponible', 12, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articulos_factura_compra');
    }
};
