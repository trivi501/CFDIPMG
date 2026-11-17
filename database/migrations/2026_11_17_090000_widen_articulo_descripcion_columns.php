<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La Descripcion de un concepto de CFDI admite hasta 1000 caracteres (Anexo 20 del SAT), pero se
 * guardaba en columnas de 255: al subir el XML de una factura de compra con una descripción larga,
 * MySQL respondía "Data too long for column 'descripcion'". El texto viaja de
 * articulos_factura_compra.descripcion a paquete_articulos.descripcion y a detalle_apoyos.articulo,
 * así que se amplían las tres. Ampliar no recorta ni borra datos.
 */
return new class extends Migration
{
    public const LARGO = 1000;

    public function up(): void
    {
        Schema::table('articulos_factura_compra', function (Blueprint $table) {
            $table->string('descripcion', self::LARGO)->change();
        });

        Schema::table('paquete_articulos', function (Blueprint $table) {
            $table->string('descripcion', self::LARGO)->change();
        });

        Schema::table('detalle_apoyos', function (Blueprint $table) {
            $table->string('articulo', self::LARGO)->change();
        });
    }

    public function down(): void
    {
        // Regresar a 255 recortaría las descripciones largas que ya se hayan guardado: no se hace.
    }
};
