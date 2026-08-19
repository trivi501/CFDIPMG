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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('description');
            $table->string('sat_product_key', 8)->comment('Clave SAT c_ClaveProdServ');
            $table->string('sat_unit_key', 3)->comment('Clave SAT c_ClaveUnidad');
            $table->decimal('unit_price', 12, 2);
            $table->json('taxes')->nullable()->comment('Impuestos por defecto, ej. IVA 16%');
            $table->string('facturapi_product_id')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
