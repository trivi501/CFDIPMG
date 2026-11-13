<?php

namespace App\Console\Commands;

use App\Support\CatalogoPermisos;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Pone al día los permisos de un servidor en uso con los de CatalogoPermisos, sin borrar nada:
 *
 *  - Crea los permisos y roles de fábrica que falten.
 *  - Un permiso RECIÉN CREADO se asigna a los roles que lo traen de fábrica (Admin, todos). Los que
 *    ya existían no se tocan: si alguien se lo quitó a un rol desde la pantalla de Roles, así se queda.
 *  - Nunca quita permisos, roles ni asignaciones. Los permisos de la base que ya no están en el
 *    catálogo solo se reportan.
 *
 * Se puede correr cuantas veces sea: la segunda vez ya no hay nada que hacer. Lo corre deploy.sh.
 */
#[Signature('permisos:sincronizar {--pretend : Solo muestra lo que haría, sin cambiar nada}')]
#[Description('Agrega los permisos y roles nuevos del código sin quitar nada de lo que ya hay')]
class SincronizarPermisos extends Command
{
    public function handle(PermissionRegistrar $registrar): int
    {
        $pretend = (bool) $this->option('pretend');
        $guard = CatalogoPermisos::GUARD;

        $existentes = Permission::where('guard_name', $guard)->pluck('name')->all();
        $nuevos = array_values(array_diff(CatalogoPermisos::PERMISOS, $existentes));
        $rolesExistentes = Role::where('guard_name', $guard)->pluck('name')->all();
        $rolesNuevos = array_values(array_diff(array_keys(CatalogoPermisos::ROLES), $rolesExistentes));

        // Permisos nuevos que recibe cada rol (o todos los de fábrica, si el rol también es nuevo).
        $asignaciones = [];
        foreach (array_keys(CatalogoPermisos::ROLES) as $rol) {
            $deFabrica = CatalogoPermisos::permisosDe($rol);
            $recibe = in_array($rol, $rolesNuevos, true) ? $deFabrica : array_values(array_intersect($deFabrica, $nuevos));

            if ($recibe !== []) {
                $asignaciones[$rol] = $recibe;
            }
        }

        if ($nuevos === [] && $rolesNuevos === [] && $asignaciones === []) {
            $this->info('Los permisos ya están al día.');
        } else {
            foreach ($nuevos as $permiso) {
                $this->line("  + permiso {$permiso}");
            }
            foreach ($rolesNuevos as $rol) {
                $this->line("  + rol {$rol}");
            }
            foreach ($asignaciones as $rol => $permisos) {
                $this->line("  + {$rol} recibe: ".implode(', ', $permisos));
            }

            if (! $pretend) {
                DB::transaction(function () use ($nuevos, $rolesNuevos, $asignaciones, $guard, $registrar) {
                    foreach ($nuevos as $permiso) {
                        Permission::create(['name' => $permiso, 'guard_name' => $guard]);
                    }
                    foreach ($rolesNuevos as $rol) {
                        Role::create(['name' => $rol, 'guard_name' => $guard]);
                    }

                    $registrar->forgetCachedPermissions();

                    foreach ($asignaciones as $rol => $permisos) {
                        // givePermissionTo solo agrega; a diferencia de syncPermissions, no quita nada.
                        Role::findByName($rol, $guard)->givePermissionTo($permisos);
                    }
                });

                $registrar->forgetCachedPermissions();
                $this->info('Permisos sincronizados.');
            } else {
                $this->warn('--pretend: no se cambió nada.');
            }
        }

        // Siempre se refresca la caché de permisos: así también toman efecto los cambios que hagan
        // las migraciones de datos que corren antes en el deploy.
        if (! $pretend) {
            $registrar->forgetCachedPermissions();
        }

        $sobrantes = array_values(array_diff($existentes, CatalogoPermisos::PERMISOS));
        if ($sobrantes !== []) {
            $this->warn('En la base hay permisos que ya no usa el código (no se borran): '.implode(', ', $sobrantes));
        }

        return self::SUCCESS;
    }
}
