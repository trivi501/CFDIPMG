<?php

namespace App\Http\Controllers;

use App\Models\ArticuloFacturaCompra;
use App\Models\FacturaCompra;
use App\Models\Paquete;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PaqueteController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('paquetes/index', [
            'paquetes' => Paquete::with('articulos', 'facturaCompra')->orderBy('nombre')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('paquetes/create', [
            'facturas' => $this->facturas(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data) {
            $paquete = Paquete::create([
                'factura_compra_id' => $data['factura_compra_id'],
                'nombre' => $data['nombre'],
            ]);

            $this->guardarArticulos($paquete, $data['articulos']);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Paquete creado.']);

        return to_route('paquetes.index');
    }

    public function edit(Paquete $paquete): Response
    {
        return Inertia::render('paquetes/edit', [
            'paquete' => $paquete->load('articulos'),
            'facturas' => $this->facturas(),
        ]);
    }

    public function update(Request $request, Paquete $paquete): RedirectResponse
    {
        $data = $this->validated($request, $paquete);

        DB::transaction(function () use ($data, $paquete) {
            $paquete->update([
                'factura_compra_id' => $data['factura_compra_id'],
                'nombre' => $data['nombre'],
            ]);
            $paquete->articulos()->delete();

            $this->guardarArticulos($paquete, $data['articulos']);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Paquete actualizado.']);

        return to_route('paquetes.index');
    }

    public function destroy(Paquete $paquete): RedirectResponse
    {
        $paquete->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Paquete eliminado.']);

        return to_route('paquetes.index');
    }

    /**
     * @return Collection<int, FacturaCompra>
     */
    protected function facturas(): Collection
    {
        return FacturaCompra::with(['articulos' => fn ($query) => $query->orderBy('descripcion')])
            ->latest('fecha')
            ->get();
    }

    /**
     * @param  list<array{articulo_factura_compra_id: int, cantidad: float}>  $articulos
     */
    protected function guardarArticulos(Paquete $paquete, array $articulos): void
    {
        $descripciones = ArticuloFacturaCompra::whereKey(array_column($articulos, 'articulo_factura_compra_id'))
            ->pluck('descripcion', 'id');

        foreach ($articulos as $articulo) {
            $paquete->articulos()->create([
                'articulo_factura_compra_id' => $articulo['articulo_factura_compra_id'],
                'descripcion' => $descripciones[$articulo['articulo_factura_compra_id']],
                'cantidad' => $articulo['cantidad'],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?Paquete $paquete = null): array
    {
        return $request->validate([
            'factura_compra_id' => ['required', 'integer', 'exists:facturas_compra,id'],
            'nombre' => ['required', 'string', 'max:255', Rule::unique('paquetes', 'nombre')->ignore($paquete)],
            'articulos' => ['required', 'array', 'min:1'],
            'articulos.*.articulo_factura_compra_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('articulos_factura_compra', 'id')->where('factura_compra_id', $request->integer('factura_compra_id')),
            ],
            'articulos.*.cantidad' => ['required', 'numeric', 'min:0.01'],
        ], [
            'factura_compra_id.required' => 'Selecciona la factura del paquete.',
            'nombre.unique' => 'Ya existe un paquete con ese nombre.',
            'articulos.required' => 'Agrega al menos un artículo al paquete.',
            'articulos.*.articulo_factura_compra_id.distinct' => 'Un artículo no se puede repetir en el paquete.',
            'articulos.*.articulo_factura_compra_id.exists' => 'Todos los artículos deben ser de la factura del paquete.',
        ]);
    }
}
