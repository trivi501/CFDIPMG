<?php

namespace App\Http\Controllers;

use App\Support\CatalogoPermisos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Permisos de la base en el orden del catálogo (agrupados como se muestran); los que no están en
     * el catálogo, al final.
     *
     * @return list<string>
     */
    protected static function permisosOrdenados(): array
    {
        $orden = array_flip(CatalogoPermisos::PERMISOS);

        $nombres = [];
        foreach (Permission::all() as $permiso) {
            $nombres[] = $permiso->name;
        }

        usort($nombres, fn (string $a, string $b) => [$orden[$a] ?? PHP_INT_MAX, $a] <=> [$orden[$b] ?? PHP_INT_MAX, $b]);

        return $nombres;
    }

    public function index(): Response
    {
        return Inertia::render('roles/index', [
            'roles' => Role::withCount('permissions', 'users')->orderBy('name')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('roles/create', [
            'permissions' => CatalogoPermisos::paraPantalla(self::permisosOrdenados()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rol creado.']);

        return to_route('roles.index');
    }

    public function edit(Role $role): Response
    {
        return Inertia::render('roles/edit', [
            'role' => $role->load('permissions:id,name'),
            'permissions' => CatalogoPermisos::paraPantalla(self::permisosOrdenados()),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name,'.$role->id],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rol actualizado.']);

        return to_route('roles.index');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $role->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rol eliminado.']);

        return to_route('roles.index');
    }
}
