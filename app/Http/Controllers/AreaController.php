<?php

namespace App\Http\Controllers;

use App\Models\Area;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AreaController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('areas/index', [
            'areas' => Area::withCount('users')->orderBy('tipo')->orderBy('nombre')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('areas/create');
    }

    public function store(Request $request): RedirectResponse
    {
        Area::create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Área creada.']);

        return to_route('areas.index');
    }

    public function edit(Area $area): Response
    {
        return Inertia::render('areas/edit', ['area' => $area]);
    }

    public function update(Request $request, Area $area): RedirectResponse
    {
        $area->update($this->validated($request, $area));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Área actualizada.']);

        return to_route('areas.index');
    }

    public function destroy(Area $area): RedirectResponse
    {
        $area->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Área eliminada.']);

        return to_route('areas.index');
    }

    /**
     * @return array{nombre: string, tipo: string}
     */
    protected function validated(Request $request, ?Area $area = null): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:255', Rule::unique('areas', 'nombre')->ignore($area)],
            'tipo' => ['required', Rule::in([Area::TIPO_SECRETARIA, Area::TIPO_REGIDURIA])],
        ], [
            'nombre.unique' => 'Ya existe un área con ese nombre.',
        ]);
    }
}
