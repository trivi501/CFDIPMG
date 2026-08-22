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
            ->when($request->string('search')->toString(), fn ($query, $search) => $query->where('nombre', 'like', "%{$search}%"))
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
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
        ]);

        $beneficiario = Beneficiario::create($data);

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
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
        ]);

        $beneficiario->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Beneficiario actualizado.']);

        return to_route('beneficiarios.index');
    }

    public function destroy(Beneficiario $beneficiario): RedirectResponse
    {
        $beneficiario->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Beneficiario eliminado.']);

        return to_route('beneficiarios.index');
    }
}
