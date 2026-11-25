<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Los apoyos capturados antes de que su usuario tuviera área se quedaron sin área, y por eso no
     * salían al filtrar por área ni en las gráficas por área. Toman el área actual de quien los capturó.
     * Solo llena los vacíos: no cambia el área de ningún apoyo que ya la tenga.
     */
    public function up(): void
    {
        DB::table('apoyos')
            ->whereNull('area_id')
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id')
            ->each(function (int $userId) {
                $areaId = DB::table('users')->where('id', $userId)->value('area_id');

                if ($areaId !== null) {
                    DB::table('apoyos')
                        ->whereNull('area_id')
                        ->where('user_id', $userId)
                        ->update(['area_id' => $areaId]);
                }
            });
    }

    public function down(): void
    {
        // Sin vuelta atrás: no se sabe cuáles estaban vacíos.
    }
};
