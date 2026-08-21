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
        Schema::create('apoyos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->foreignId('beneficiario_id')->constrained();
            $table->foreignId('persona_apoya_id')->constrained('personas_apoya');
            $table->decimal('sub_total', 12, 2)->default(0);
            $table->decimal('iva', 12, 2)->default(0);
            $table->decimal('monto_total', 12, 2)->default(0);
            $table->boolean('preparado')->default(false);
            $table->boolean('facturado')->default(false);
            $table->string('solicitud_recibo_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apoyos');
    }
};
