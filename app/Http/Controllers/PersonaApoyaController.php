<?php

namespace App\Http\Controllers;

use App\Models\PersonaApoya;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PersonaApoyaController extends Controller
{
    public function index(Request $request): Response
    {
        $personasApoya = PersonaApoya::query()
            ->when($request->string('search')->toString(), fn ($query, $search) => $query->where('nombre', 'like', "%{$search}%"))
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('personas-apoya/index', [
            'personasApoya' => $personasApoya,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('personas-apoya/create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
        ]);

        PersonaApoya::create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Persona apoya creada.']);

        return to_route('personas-apoya.index');
    }

    public function edit(PersonaApoya $personaApoya): Response
    {
        return Inertia::render('personas-apoya/edit', ['personaApoya' => $personaApoya]);
    }

    public function update(Request $request, PersonaApoya $personaApoya): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
        ]);

        $personaApoya->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Persona apoya actualizada.']);

        return to_route('personas-apoya.index');
    }

    public function destroy(PersonaApoya $personaApoya): RedirectResponse
    {
        $personaApoya->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Persona apoya eliminada.']);

        return to_route('personas-apoya.index');
    }
}
