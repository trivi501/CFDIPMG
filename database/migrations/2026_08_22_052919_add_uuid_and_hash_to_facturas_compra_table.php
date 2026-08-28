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
        Schema::table('facturas_compra', function (Blueprint $table) {
            $table->string('uuid')->nullable()->unique()->after('xml_path');
            $table->string('xml_hash', 64)->nullable()->unique()->after('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facturas_compra', function (Blueprint $table) {
            $table->dropColumn(['uuid', 'xml_hash']);
        });
    }
};
