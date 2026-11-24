<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claves_sat', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 8)->unique()->comment('Clave SAT c_ClaveProdServ');
            $table->string('unidad', 3)->comment('Clave SAT c_ClaveUnidad');
            $table->string('concepto')->comment('Descripción que se pone en la línea de la factura');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claves_sat');
    }
};
