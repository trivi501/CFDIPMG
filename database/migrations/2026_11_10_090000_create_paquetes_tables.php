<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paquetes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factura_compra_id')->constrained('facturas_compra')->cascadeOnDelete();
            $table->string('nombre')->unique();
            $table->timestamps();
        });

        Schema::create('paquete_articulos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paquete_id')->constrained('paquetes')->cascadeOnDelete();
            $table->foreignId('articulo_factura_compra_id')->constrained('articulos_factura_compra')->cascadeOnDelete();
            $table->string('descripcion');
            $table->decimal('cantidad', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paquete_articulos');
        Schema::dropIfExists('paquetes');
    }
};
