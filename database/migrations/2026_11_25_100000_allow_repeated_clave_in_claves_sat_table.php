<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una misma clave SAT puede tener varios conceptos (p. ej. 84111506 para distintos servicios): la
 * clave deja de ser única. Lo que no se repite es la misma clave con el mismo concepto, y eso lo
 * valida ClaveSatController.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claves_sat', function (Blueprint $table) {
            $table->dropUnique(['clave']);
            $table->index('clave');
        });
    }

    public function down(): void
    {
        Schema::table('claves_sat', function (Blueprint $table) {
            $table->dropIndex(['clave']);
            $table->unique('clave');
        });
    }
};
