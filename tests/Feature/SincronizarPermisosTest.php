<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\CatalogoPermisos;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SincronizarPermisosTest extends TestCase
{
    use RefreshDatabase;

    public function test_en_una_base_vacia_crea_todo_igual_que_el_seeder(): void
    {
        $this->artisan('permisos:sincronizar')->assertSuccessful();

        $this->assertEqualsCanonicalizing(CatalogoPermisos::PERMISOS, Permission::pluck('name')->all());

        foreach (array_keys(CatalogoPermisos::ROLES) as $rol) {
            $this->assertEqualsCanonicalizing(
                CatalogoPermisos::permisosDe($rol),
                Role::findByName($rol)->permissions->pluck('name')->all(),
            );
        }
    }

    public function test_un_permiso_nuevo_llega_a_sus_roles_sin_deshacer_lo_quitado_desde_la_pantalla_de_roles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        // Simula un servidor en uso: a Consulta le quitaron una descarga desde la pantalla de Roles,
        // y el código trae un permiso que este servidor todavía no tiene.
        Role::findByName('Consulta')->revokePermissionTo('invoices.download');
        Permission::findByName('apoyos.view')->delete();

        $this->artisan('permisos:sincronizar')
            ->expectsOutputToContain('+ permiso apoyos.view')
            ->assertSuccessful();

        $this->assertTrue(Role::findByName('Consulta')->hasPermissionTo('apoyos.view'));
        $this->assertTrue(Role::findByName('Apoyos')->hasPermissionTo('apoyos.view'));
        $this->assertTrue(Role::findByName('Admin')->hasPermissionTo('apoyos.view'));
        $this->assertFalse(Role::findByName('Facturación')->hasPermissionTo('apoyos.manage'));
        // Lo que se quitó a mano sigue quitado.
        $this->assertFalse(Role::findByName('Consulta')->hasPermissionTo('invoices.download'));
    }

    public function test_nunca_quita_permisos_roles_ni_asignaciones(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $extra = Permission::create(['name' => 'reportes.ver', 'guard_name' => 'web']);
        $rolPropio = Role::create(['name' => 'Tesorería', 'guard_name' => 'web']);
        $rolPropio->givePermissionTo($extra);
        Role::findByName('Apoyos')->givePermissionTo('invoices.view');
        $usuario = User::factory()->create();
        $usuario->assignRole('Apoyos');
        $usuario->givePermissionTo('customers.view');

        $antes = [Permission::count(), Role::count()];

        $this->artisan('permisos:sincronizar')
            ->expectsOutputToContain('Los permisos ya están al día.')
            ->expectsOutputToContain('reportes.ver')
            ->assertSuccessful();

        $this->assertSame($antes, [Permission::count(), Role::count()]);
        $this->assertTrue($rolPropio->fresh()->hasPermissionTo('reportes.ver'));
        $this->assertTrue(Role::findByName('Apoyos')->hasPermissionTo('invoices.view'));
        $this->assertTrue($usuario->fresh()->hasRole('Apoyos'));
        $this->assertTrue($usuario->fresh()->hasDirectPermission('customers.view'));
    }

    public function test_pretend_no_cambia_nada(): void
    {
        $this->artisan('permisos:sincronizar --pretend')
            ->expectsOutputToContain('+ rol Admin')
            ->expectsOutputToContain('no se cambió nada')
            ->assertSuccessful();

        $this->assertSame(0, Permission::count());
        $this->assertSame(0, Role::count());
    }
}
