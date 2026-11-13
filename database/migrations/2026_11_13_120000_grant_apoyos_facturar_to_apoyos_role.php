<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El rol Apoyos ahora factura sus apoyos aprobados. permisos:sincronizar no agrega permisos que ya
 * existían a roles que ya existían (para respetar lo editado desde la pantalla de Roles), así que
 * este cambio puntual llega a los servidores en uso con esta migración. Solo agrega; no borra nada.
 *
 * Usa el query builder (no los modelos de spatie) para que `migrate --pretend` no falle cuando el
 * rol o el permiso todavía no existen. permisos:sincronizar, que corre justo después en deploy.sh,
 * refresca la caché de permisos.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rol = DB::table('roles')->where('name', 'Apoyos')->where('guard_name', 'web')->value('id');
        $permiso = DB::table('permissions')->where('name', 'apoyos.facturar')->where('guard_name', 'web')->value('id');

        if ($rol !== null && $permiso !== null) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permiso,
                'role_id' => $rol,
            ]);
        }
    }

    public function down(): void
    {
        // Quitar el permiso se hace, si se quiere, desde la pantalla de Roles.
    }
};
