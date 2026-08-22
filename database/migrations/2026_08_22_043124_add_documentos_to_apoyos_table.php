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
        Schema::table('apoyos', function (Blueprint $table) {
            $table->string('curp')->nullable();
            $table->string('curp_path')->nullable();
            $table->string('rfc')->nullable();
            $table->string('rfc_path')->nullable();
            $table->string('ine_path')->nullable();
            $table->string('comprobante_domicilio_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('apoyos', function (Blueprint $table) {
            $table->dropColumn([
                'curp',
                'curp_path',
                'rfc',
                'rfc_path',
                'ine_path',
                'comprobante_domicilio_path',
            ]);
        });
    }
};
