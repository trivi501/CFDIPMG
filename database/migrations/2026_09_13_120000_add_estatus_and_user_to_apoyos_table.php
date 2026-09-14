<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apoyos', function (Blueprint $table) {
            $table->string('estatus', 20)->default('preparado')->after('persona_apoya_id');
            $table->foreignId('user_id')->nullable()->after('estatus')->constrained()->nullOnDelete();
        });

        DB::table('apoyos')->where('facturado', true)->update(['estatus' => 'facturado']);
        DB::table('apoyos')->where('facturado', false)->where('preparado', true)->update(['estatus' => 'aprobado']);

        Schema::table('apoyos', function (Blueprint $table) {
            $table->dropColumn(['preparado', 'facturado']);
        });
    }

    public function down(): void
    {
        Schema::table('apoyos', function (Blueprint $table) {
            $table->boolean('preparado')->default(false);
            $table->boolean('facturado')->default(false);
        });

        DB::table('apoyos')->where('estatus', 'facturado')->update(['facturado' => true, 'preparado' => true]);
        DB::table('apoyos')->where('estatus', 'aprobado')->update(['preparado' => true]);

        Schema::table('apoyos', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['estatus', 'user_id']);
        });
    }
};
