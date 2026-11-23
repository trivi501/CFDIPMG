<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevisarAreasTest extends TestCase
{
    use RefreshDatabase;

    public function test_muestra_que_areas_son_privadas_y_quien_ve_todo(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $cabildo = Area::create(['nombre' => 'Cabildo', 'tipo' => Area::TIPO_REGIDURIA, 'privada' => true]);
        Area::create(['nombre' => 'Salud', 'tipo' => Area::TIPO_SECRETARIA]);
        User::factory()->create(['name' => 'Regidora Ana', 'area_id' => $cabildo->id])->assignRole('Apoyos');
        User::factory()->create(['name' => 'Asistente Luis', 'area_id' => $cabildo->id])->assignRole('Facturación');

        $this->artisan('areas:revisar')
            ->expectsOutputToContain('privada: cada usuario ve solo lo que él sube')
            ->expectsOutputToContain('compartida: sus integrantes ven lo de todos')
            ->expectsTable(['Usuario', 'Rol', 'Facturas de compra que ve', '¿Ve las de todos?'], [
                ['Asistente Luis', 'Facturación', 0, 'SÍ, por su rol'],
                ['Regidora Ana', 'Apoyos', 0, 'no'],
            ])
            ->assertSuccessful();
    }
}
