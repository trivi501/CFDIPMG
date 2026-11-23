<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\CatalogoPermisos;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RolesEnEspanolTest extends TestCase
{
    use RefreshDatabase;

    public function test_cada_permiso_del_catalogo_tiene_su_texto_en_espanol(): void
    {
        $this->assertEqualsCanonicalizing(CatalogoPermisos::PERMISOS, array_keys(CatalogoPermisos::ETIQUETAS));
    }

    public function test_la_pantalla_de_roles_muestra_los_permisos_agrupados_y_en_espanol(): void
    {
        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
        Permission::create(['name' => 'reportes.ver', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)->get('/roles/create')->assertOk()->assertInertia(function ($page) {
            $permisos = collect($page->toArray()['props']['permissions']);

            // En el orden del catálogo, con grupo y texto; los que no están en el catálogo, al final en "Otros".
            $this->assertSame(['name' => 'receipts.view', 'grupo' => 'Recibos', 'etiqueta' => 'Ver recibos de pago'], $permisos->first());
            $this->assertSame(['name' => 'reportes.ver', 'grupo' => 'Otros', 'etiqueta' => 'reportes.ver'], $permisos->last());
            $this->assertSame('Aprobar y rechazar apoyos', $permisos->firstWhere('name', 'apoyos.approve')['etiqueta']);
        });
    }
}
