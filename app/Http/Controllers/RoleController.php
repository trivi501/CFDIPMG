<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('roles/index', [
            'roles' => Role::withCount('permissions', 'users')->orderBy('name')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('roles/create', [
            'permissions' => Permission::orderBy('name')->pluck('name'),
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
            'permissions' => Permission::orderBy('name')->pluck('name'),
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
