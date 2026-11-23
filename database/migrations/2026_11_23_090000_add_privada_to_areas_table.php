<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Áreas donde cada usuario ve solo lo suyo. Normalmente los integrantes de un área comparten sus
 * apoyos y facturas de compra; en Cabildo están todos los regidores y sus asistentes juntos, y
 * cada uno debe ver solo lo que él captura. Se activa para el área llamada "Cabildo" y se puede
 * cambiar en la pantalla de Áreas. Solo agrega una columna; no borra nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->boolean('privada')->default(false)->after('tipo');
        });

        DB::table('areas')->whereRaw('LOWER(TRIM(nombre)) = ?', ['cabildo'])->update(['privada' => true]);
    }

    public function down(): void
    {
        // Quitar la columna haría que en Cabildo todos volvieran a ver lo de todos: no se hace.
    }
};
