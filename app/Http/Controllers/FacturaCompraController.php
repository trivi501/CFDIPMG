<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\FacturaCompra;
use App\Models\User;
use App\Services\Cfdi\CfdiXmlParser;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FacturaCompraController extends Controller
{
    /** Cada regidor ve las facturas de su área; Admin y Facturación (apoyos.view-all), todas. */
    public function index(Request $request): Response
    {
        $puedeAsignar = $request->user()->can('apoyos.view-all');

        return Inertia::render('facturas-compra/index', [
            'facturas' => FacturaCompra::visiblePara($request->user())
                ->with('articulos', 'area:id,nombre', 'user:id,name')
                ->latest('fecha')
                ->get(),
            'areas' => $puedeAsignar ? Area::orderBy('nombre')->get(['id', 'nombre', 'privada']) : [],
            // Para elegir de quién es cada factura de un área privada (Cabildo).
            'usuarios' => $puedeAsignar ? User::whereNotNull('area_id')->orderBy('name')->get(['id', 'name', 'area_id']) : [],
            'puedeAsignarArea' => $puedeAsignar,
        ]);
    }

    // Se descarga, no se abre: un XML subido podría ejecutar código en el navegador.
    public function xml(Request $request, FacturaCompra $factura): StreamedResponse
    {
        abort_unless($factura->esVisiblePara($request->user()), 403);
        abort_unless(Storage::disk('local')->exists($factura->xml_path), 404);

        return Storage::disk('local')->download(
            $factura->xml_path,
            "factura-compra-{$factura->id}.xml",
            ['X-Content-Type-Options' => 'nosniff'],
        );
    }

    public function store(Request $request, CfdiXmlParser $parser): RedirectResponse|JsonResponse
    {
        $request->validate([
            'xml' => ['required', 'file', 'max:10240'],
        ]);

        $contents = $request->file('xml')?->get();

        if (! is_string($contents)) {
            return $this->uploadError($request, 'No se pudo leer el archivo.');
        }

        try {
            $parsed = $parser->parse($contents);
        } catch (RuntimeException $e) {
            return $this->uploadError($request, $e->getMessage());
        }

        $hash = hash('sha256', $contents);

        $duplicateQuery = FacturaCompra::where('xml_hash', $hash);

        if ($parsed['uuid'] !== null) {
            $duplicateQuery->orWhere('uuid', $parsed['uuid']);
        }

        $duplicate = $duplicateQuery->first();

        if ($duplicate !== null) {
            $fecha = $duplicate->fecha !== null ? Carbon::parse($duplicate->fecha)->format('d/m/Y') : 'sin fecha';

            return $this->uploadError($request, "Esta factura ya fue registrada ({$duplicate->emisor}, {$fecha}).");
        }

        $path = $request->file('xml')->store('facturas-compra', 'local');

        try {
            $factura = DB::transaction(function () use ($request, $parsed, $path, $hash) {
                $factura = FacturaCompra::create([
                    // De quien la sube y de su área: solo la ven (y usan su inventario) ellos.
                    'user_id' => $request->user()->id,
                    'area_id' => $request->user()->area_id,
                    'xml_path' => $path,
                    'uuid' => $parsed['uuid'],
                    'xml_hash' => $hash,
                    'emisor' => $parsed['emisor'],
                    'fecha' => $parsed['fecha'] !== null ? Carbon::parse($parsed['fecha']) : now(),
                    'total' => collect($parsed['conceptos'])->sum(
                        fn (array $concepto) => round($concepto['cantidad'] * $concepto['costo_unidad'], 2) + $concepto['iva']
                    ),
                ]);

                foreach ($parsed['conceptos'] as $concepto) {
                    $factura->articulos()->create([
                        'descripcion' => $concepto['articulo'],
                        'cantidad_total' => $concepto['cantidad'],
                        'costo_unidad' => $concepto['costo_unidad'],
                        'iva' => $concepto['iva'],
                        'cantidad_disponible' => $concepto['cantidad'],
                    ]);
                }

                return $factura;
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return $this->uploadError($request, 'Esta factura ya fue registrada.');
            }

            throw $e;
        }

        if ($request->wantsJson()) {
            return response()->json(['facturaCompra' => $factura->load('articulos')]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Factura de compra registrada.']);

        return to_route('facturas-compra.index');
    }

    /**
     * Asigna (o cambia) el área dueña de una factura: sobre todo para las subidas antes de que las
     * facturas tuvieran dueño, que mientras tanto solo ven Admin y Facturación.
     */
    public function asignarArea(Request $request, FacturaCompra $factura): RedirectResponse
    {
        $data = $request->validate([
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
        ]);

        $factura->update(['area_id' => $data['area_id'] ?? null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $factura->area_id === null
            ? 'La factura quedó sin área: solo la ven Admin y Facturación.'
            : 'Área asignada a la factura.']);

        return back();
    }

    /**
     * En un área privada (Cabildo) cada quien ve solo sus facturas: si la subió un asistente a
     * nombre de su regidor, aquí se pasa al regidor. La factura toma el área de su nuevo dueño.
     */
    public function asignarUsuario(Request $request, FacturaCompra $factura): RedirectResponse
    {
        $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $usuario = User::whereKey($request->integer('user_id'))->firstOrFail();
        $factura->update(['user_id' => $usuario->id, 'area_id' => $usuario->area_id ?? $factura->area_id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "La factura ahora es de {$usuario->name}."]);

        return back();
    }

    protected function uploadError(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->withErrors(['xml' => $message]);
    }
}
