<?php

namespace App\Http\Controllers;

use App\Models\ClaveSat;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClaveSatController extends Controller
{
    public function index(Request $request): Response
    {
        $claves = ClaveSat::query()
            ->when($request->string('search')->trim()->toString(), function ($query, $search) {
                $query->where(fn ($query) => $query
                    ->where('clave', 'like', "%{$search}%")
                    ->orWhere('unidad', 'like', "%{$search}%")
                    ->orWhere('concepto', 'like', "%{$search}%"));
            })
            ->orderBy('clave')
            ->orderBy('concepto')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('claves-sat/index', [
            'claves' => $claves,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('claves-sat/create');
    }

    public function store(Request $request): RedirectResponse
    {
        ClaveSat::create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Clave SAT guardada.']);

        return to_route('claves-sat.index');
    }

    public function edit(ClaveSat $claveSat): Response
    {
        return Inertia::render('claves-sat/edit', ['clave' => $claveSat]);
    }

    public function update(Request $request, ClaveSat $claveSat): RedirectResponse
    {
        $claveSat->update($this->validated($request, $claveSat));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Clave SAT actualizada.']);

        return to_route('claves-sat.index');
    }

    public function destroy(ClaveSat $claveSat): RedirectResponse
    {
        $claveSat->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Clave SAT eliminada.']);

        return to_route('claves-sat.index');
    }

    /**
     * Para los formularios de factura: al escribir una clave se llenan solos su unidad y concepto.
     * Una clave puede venir varias veces, una por concepto.
     *
     * @return Collection<int, ClaveSat>
     */
    public static function paraFacturar(): Collection
    {
        return ClaveSat::orderBy('clave')->orderBy('concepto')->get(['id', 'clave', 'unidad', 'concepto']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?ClaveSat $claveSat = null): array
    {
        $request->merge([
            'clave' => trim((string) $request->input('clave')),
            'unidad' => mb_strtoupper(trim((string) $request->input('unidad'))),
            'concepto' => trim((string) $request->input('concepto')),
        ]);

        return $request->validate([
            'clave' => ['required', 'digits:8'],
            'unidad' => ['required', 'string', 'max:3'],
            // La clave se puede repetir con otro concepto, no con el mismo.
            'concepto' => [
                'required',
                'string',
                'max:255',
                Rule::unique('claves_sat', 'concepto')
                    ->where('clave', $request->string('clave')->toString())
                    ->ignore($claveSat),
            ],
        ], [
            'clave.digits' => 'La clave de producto o servicio del SAT tiene 8 dígitos.',
            'concepto.unique' => 'Esa clave ya tiene registrado ese mismo concepto.',
        ]);
    }
}
