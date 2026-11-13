<?php

namespace Database\Seeders;

use App\Support\CatalogoPermisos;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Deja los roles de fábrica exactamente como en CatalogoPermisos: solo para una instalación nueva.
 * En un servidor en uso, `php artisan permisos:sincronizar` agrega lo nuevo sin deshacer los
 * cambios hechos desde la pantalla de Roles.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (CatalogoPermisos::PERMISOS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => CatalogoPermisos::GUARD]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_keys(CatalogoPermisos::ROLES) as $roleName) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => CatalogoPermisos::GUARD]);

            $role->syncPermissions(CatalogoPermisos::permisosDe($roleName));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
