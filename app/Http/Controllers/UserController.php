<?php

namespace App\Http\Controllers;

use App\Models\Apoyo;
use App\Models\Area;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->with('roles:id,name', 'area:id,nombre,tipo')
            ->when($request->string('search')->toString(), function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('users/index', [
            'users' => $users,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('users/create', [
            'roles' => Role::orderBy('name')->pluck('name'),
            'areas' => $this->areas(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'titulo' => ['nullable', 'string', 'max:255'],
            'roles' => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'area_id' => $data['area_id'] ?? null,
            'titulo' => $data['titulo'] ?? null,
        ]);

        $user->syncRoles($data['roles'] ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuario creado.']);

        return to_route('users.index');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('users/edit', [
            'user' => $user->load('roles:id,name'),
            'roles' => Role::orderBy('name')->pluck('name'),
            'areas' => $this->areas(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', 'min:8'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'titulo' => ['nullable', 'string', 'max:255'],
            'roles' => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        DB::transaction(function () use ($data, $user) {
            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'area_id' => $data['area_id'] ?? null,
                'titulo' => $data['titulo'] ?? null,
                ...(! empty($data['password']) ? ['password' => Hash::make($data['password'])] : []),
            ]);

            // Los apoyos que registró antes de tener área pasan a su área; los que ya tenían área se quedan ahí.
            if ($user->area_id !== null) {
                Apoyo::where('user_id', $user->id)->whereNull('area_id')->update(['area_id' => $user->area_id]);
            }

            $user->syncRoles($data['roles'] ?? []);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuario actualizado.']);

        return to_route('users.index');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'No puedes eliminar tu propia cuenta.']);

            return back();
        }

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuario eliminado.']);

        return to_route('users.index');
    }

    /**
     * @return Collection<int, Area>
     */
    protected function areas(): Collection
    {
        return Area::orderBy('nombre')->get(['id', 'nombre', 'tipo']);
    }
}
