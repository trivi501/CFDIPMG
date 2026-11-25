<?php

namespace App\Http\Controllers;

use App\Models\Beneficiario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BeneficiarioController extends Controller
{
    public function index(Request $request): Response
    {
        $beneficiarios = Beneficiario::query()
            ->when($request->string('search')->toString(), fn ($query, $search) => $query->where(fn ($q) => $q
                ->where('nombre', 'like', "%{$search}%")
                ->orWhere('rfc', 'like', "%{$search}%")
                ->orWhere('curp', 'like', "%{$search}%")))
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('beneficiarios/index', [
            'beneficiarios' => $beneficiarios,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('beneficiarios/create');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $beneficiario = Beneficiario::create($this->validated($request));

        if ($request->wantsJson()) {
            return response()->json($beneficiario);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Beneficiario creado.']);

        return to_route('beneficiarios.index');
    }

    public function edit(Beneficiario $beneficiario): Response
    {
        return Inertia::render('beneficiarios/edit', ['beneficiario' => $beneficiario]);
    }

    public function update(Request $request, Beneficiario $beneficiario): RedirectResponse
    {
        $beneficiario->update($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Beneficiario actualizado.']);

        return to_route('beneficiarios.index');
    }

    public function destroy(Beneficiario $beneficiario): RedirectResponse
    {
        // Los apoyos lo referencian (llave foránea): borrarlo rompería su historial.
        $apoyos = $beneficiario->apoyos()->count();

        if ($apoyos > 0) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => "No se puede eliminar a {$beneficiario->nombre}: tiene {$apoyos} apoyo(s) registrado(s). Puedes editar sus datos.",
            ]);

            return back();
        }

        $beneficiario->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Beneficiario eliminado.']);

        return to_route('beneficiarios.index');
    }

    /**
     * @return array{nombre: string, rfc: ?string, curp: ?string}
     */
    protected function validated(Request $request): array
    {
        $request->merge([
            'rfc' => Beneficiario::normalizarClave($request->input('rfc')),
            'curp' => Beneficiario::normalizarClave($request->input('curp')),
        ]);

        /** @var array{nombre: string, rfc: ?string, curp: ?string} */
        return $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'rfc' => ['nullable', 'string', 'max:13'],
            'curp' => ['nullable', 'string', 'max:18'],
        ]);
    }
}
