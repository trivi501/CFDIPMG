<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las facturas de compra no guardaban de quién eran, así que cada regidor veía (y podía gastar el
 * inventario de) las de todos. Ahora pertenecen a quien las sube y a su área, igual que los apoyos.
 *
 * Las ya existentes se recuperan de la bitácora (el "alta" de cada factura dice qué usuario la
 * subió). Las que no aparecen ahí quedan sin dueño: solo las ve quien tiene apoyos.view-all hasta
 * que un Admin les asigne área. Solo agrega columnas y llena las vacías; no borra nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas_compra', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('area_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        $this->rellenarDesdeLaBitacora();
    }

    /** Dueño y área de cada factura sin dueño, según quién la dio de alta en la bitácora. */
    public function rellenarDesdeLaBitacora(): void
    {
        $altas = DB::table('auditoria')
            ->where('tabla', 'facturas_compra')
            ->where('accion', 'alta')
            ->whereNotNull('user_id')
            ->whereNotNull('auditable_id')
            ->orderBy('id')
            ->get(['auditable_id', 'user_id']);

        foreach ($altas as $alta) {
            $areaId = DB::table('users')->where('id', $alta->user_id)->value('area_id');

            DB::table('facturas_compra')
                ->where('id', (int) $alta->auditable_id)
                ->whereNull('user_id')
                ->update(['user_id' => $alta->user_id, 'area_id' => $areaId]);
        }
    }

    public function down(): void
    {
        // Quitar las columnas perdería de quién es cada factura: no se hace.
    }
};
