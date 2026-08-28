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
        Schema::table('detalle_apoyos', function (Blueprint $table) {
            $table->foreignId('articulo_factura_compra_id')
                ->nullable()
                ->after('apoyo_id')
                ->constrained('articulos_factura_compra')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detalle_apoyos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('articulo_factura_compra_id');
        });
    }
};
