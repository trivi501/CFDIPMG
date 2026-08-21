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
        Schema::create('detalle_apoyos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apoyo_id')->constrained()->cascadeOnDelete();
            $table->decimal('cantidad', 10, 2)->default(1);
            $table->string('articulo');
            $table->decimal('costo_unidad', 12, 2);
            $table->decimal('iva', 12, 2)->default(0);
            $table->decimal('sub_total', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_apoyos');
    }
};
