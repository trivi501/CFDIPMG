<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RFC y CURP del beneficiario, para que al capturarle otro apoyo se llenen solos. Se toman del
     * apoyo más reciente que los traiga.
     */
    public function up(): void
    {
        Schema::table('beneficiarios', function (Blueprint $table) {
            $table->string('rfc', 13)->nullable()->after('nombre');
            $table->string('curp', 18)->nullable()->after('rfc');
        });

        foreach (['rfc', 'curp'] as $campo) {
            DB::table('apoyos')
                ->whereNotNull($campo)
                ->where($campo, '!=', '')
                ->orderBy('fecha')
                ->orderBy('id')
                ->get(['beneficiario_id', $campo])
                // El último de cada beneficiario gana.
                ->keyBy('beneficiario_id')
                ->each(fn (object $apoyo) => DB::table('beneficiarios')
                    ->where('id', $apoyo->beneficiario_id)
                    ->update([$campo => mb_strtoupper(trim((string) $apoyo->{$campo}))]));
        }
    }

    public function down(): void
    {
        Schema::table('beneficiarios', function (Blueprint $table) {
            $table->dropColumn(['rfc', 'curp']);
        });
    }
};
