<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bitácora de auditoría: altas, modificaciones y eliminaciones (con valores anteriores y nuevos),
     * cambios masivos, descargas y accesos. Los movimientos de una misma operación comparten `lote`.
     */
    public function up(): void
    {
        if (Schema::hasTable('auditoria')) {
            return;
        }

        Schema::create('auditoria', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('accion', 30);
            $table->string('tabla', 100)->nullable()->index();
            $table->string('auditable_type', 150)->nullable();
            $table->string('auditable_id', 60)->nullable();
            $table->string('descripcion', 300)->nullable();
            $table->json('antes')->nullable();
            $table->json('despues')->nullable();
            $table->uuid('lote')->nullable()->index();
            $table->string('origen', 255)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['tabla', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria');
    }
};
