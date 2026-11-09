<?php

namespace App\Http\Controllers;

use App\Models\FacturaCompra;
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
    public function index(): Response
    {
        return Inertia::render('facturas-compra/index', [
            'facturas' => FacturaCompra::with('articulos')->latest('fecha')->get(),
        ]);
    }

    // Se descarga, no se abre: un XML subido podría ejecutar código en el navegador.
    public function xml(FacturaCompra $factura): StreamedResponse
    {
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
            $factura = DB::transaction(function () use ($parsed, $path, $hash) {
                $factura = FacturaCompra::create([
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

    protected function uploadError(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->withErrors(['xml' => $message]);
    }
}
