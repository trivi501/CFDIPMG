<?php

namespace App\Http\Controllers;

use App\Models\Apoyo;
use App\Models\ArticuloFacturaCompra;
use App\Models\Beneficiario;
use App\Models\Customer;
use App\Models\DetalleApoyo;
use App\Models\FacturaCompra;
use App\Models\PersonaApoya;
use App\Services\InvoiceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class ApoyoController extends Controller
{
    public function index(Request $request): Response
    {
        $apoyos = $this->visibleApoyos($request)
            ->with('beneficiario', 'personaApoya')
            ->when($request->filled('estatus'), fn (Builder $query) => $query->where('estatus', $request->string('estatus')))
            ->latest('fecha')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('apoyos/index', [
            'apoyos' => $apoyos,
            'filters' => $request->only('estatus'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('apoyos/create', [
            'beneficiarios' => Beneficiario::orderBy('nombre')->get(['id', 'nombre']),
            'personasApoya' => PersonaApoya::orderBy('nombre')->get(['id', 'nombre']),
            'facturasDisponibles' => $this->facturasDisponibles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $request) {
            $this->reserveStock($data['detalles']);

            $apoyo = Apoyo::create([
                'fecha' => $data['fecha'],
                'beneficiario_id' => $data['beneficiario_id'],
                'persona_apoya_id' => $data['persona_apoya_id'],
                'user_id' => $request->user()->id,
                'estatus' => Apoyo::ESTATUS_PREPARADO,
                'curp' => $data['curp'] ?? null,
                'rfc' => $data['rfc'] ?? null,
                ...$this->computeTotals($data['detalles']),
                'solicitud_recibo_path' => $request->hasFile('solicitud_recibo')
                    ? $request->file('solicitud_recibo')->store('apoyos', 'public')
                    : null,
                ...$this->storedDocumentPaths($request),
            ]);

            foreach ($data['detalles'] as $detalle) {
                $apoyo->detalles()->create($this->lineWithTotals($detalle));
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Apoyo creado.']);

        return to_route('apoyos.index');
    }

    public function show(Request $request, Apoyo $apoyo): Response
    {
        $this->authorizeAccess($request, $apoyo);

        $apoyo->load('detalles', 'beneficiario', 'personaApoya', 'user', 'invoice');

        return Inertia::render('apoyos/show', [
            'apoyo' => $apoyo,
        ]);
    }

    public function edit(Request $request, Apoyo $apoyo): Response
    {
        $this->authorizeAccess($request, $apoyo);

        $apoyo->load('detalles');

        return Inertia::render('apoyos/edit', [
            'apoyo' => $apoyo,
            'beneficiarios' => Beneficiario::orderBy('nombre')->get(['id', 'nombre']),
            'personasApoya' => PersonaApoya::orderBy('nombre')->get(['id', 'nombre']),
            'facturasDisponibles' => $this->facturasDisponibles(),
        ]);
    }

    public function update(Request $request, Apoyo $apoyo): RedirectResponse
    {
        $this->authorizeAccess($request, $apoyo);

        $data = $this->validated($request);

        DB::transaction(function () use ($data, $request, $apoyo) {
            $this->releaseStock($apoyo->detalles);
            $apoyo->detalles()->delete();

            $this->reserveStock($data['detalles']);

            $apoyo->update([
                'fecha' => $data['fecha'],
                'beneficiario_id' => $data['beneficiario_id'],
                'persona_apoya_id' => $data['persona_apoya_id'],
                'curp' => $data['curp'] ?? null,
                'rfc' => $data['rfc'] ?? null,
                ...$this->computeTotals($data['detalles']),
                'solicitud_recibo_path' => $request->hasFile('solicitud_recibo')
                    ? $request->file('solicitud_recibo')->store('apoyos', 'public')
                    : $apoyo->solicitud_recibo_path,
                ...$this->storedDocumentPaths($request, $apoyo),
            ]);

            foreach ($data['detalles'] as $detalle) {
                $apoyo->detalles()->create($this->lineWithTotals($detalle));
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Apoyo actualizado.']);

        return to_route('apoyos.index');
    }

    public function destroy(Request $request, Apoyo $apoyo): RedirectResponse
    {
        $this->authorizeAccess($request, $apoyo);

        DB::transaction(function () use ($apoyo) {
            $this->releaseStock($apoyo->detalles);
            $apoyo->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Apoyo eliminado.']);

        return to_route('apoyos.index');
    }

    public function aprobar(Request $request, Apoyo $apoyo): RedirectResponse
    {
        if ($apoyo->estatus !== Apoyo::ESTATUS_PREPARADO) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Solo un apoyo preparado se puede aprobar.']);

            return back();
        }

        $apoyo->update(['estatus' => Apoyo::ESTATUS_APROBADO]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Apoyo aprobado.']);

        return back();
    }

    public function rechazar(Request $request, Apoyo $apoyo): RedirectResponse
    {
        if ($apoyo->estatus !== Apoyo::ESTATUS_PREPARADO) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Solo un apoyo preparado se puede rechazar.']);

            return back();
        }

        DB::transaction(function () use ($apoyo) {
            $this->releaseStock($apoyo->detalles);
            $apoyo->update(['estatus' => Apoyo::ESTATUS_RECHAZADO]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Apoyo rechazado.']);

        return back();
    }

    public function facturar(Request $request, Apoyo $apoyo, InvoiceService $invoiceService): RedirectResponse
    {
        $this->authorizeAccess($request, $apoyo);

        if ($apoyo->estatus !== Apoyo::ESTATUS_APROBADO) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Solo un apoyo aprobado se puede facturar.']);

            return back();
        }

        $data = $request->validate($this->facturarValidationRules());

        $customer = Customer::firstOrCreate(['rfc' => $data['rfc']], [
            'legal_name' => $data['legal_name'],
            'tax_system' => $data['tax_system'],
            'zip' => $data['zip'],
            'email' => $data['email'] ?? null,
        ]);

        $invoice = $invoiceService->generate(null, $customer, $this->itemsFromDetalles($apoyo), [
            'payment_form' => $data['payment_form'],
            'payment_method' => $data['payment_method'],
            'use' => $data['use'],
        ]);

        if ($invoice->status === 'failed') {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Error al facturar: '.$invoice->error_message]);

            return back();
        }

        $apoyo->update(['estatus' => Apoyo::ESTATUS_FACTURADO, 'invoice_id' => $invoice->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Apoyo facturado.']);

        return back();
    }

    public function facturarTodo(Request $request, InvoiceService $invoiceService): RedirectResponse
    {
        $data = $request->validate([
            'payment_form' => ['required', 'string', 'max:2'],
            'payment_method' => ['required', 'in:PUE,PPD'],
            'use' => ['required', 'string', 'max:4'],
        ]);

        $apoyos = $this->visibleApoyos($request)
            ->where('estatus', Apoyo::ESTATUS_APROBADO)
            ->with('beneficiario', 'detalles')
            ->get();

        $facturados = 0;
        $fallidos = 0;

        foreach ($apoyos as $apoyo) {
            $customer = $apoyo->rfc !== null
                ? Customer::firstOrCreate(['rfc' => $apoyo->rfc], [
                    'legal_name' => $apoyo->beneficiario->nombre,
                    'tax_system' => '616',
                    'zip' => '01000',
                ])
                : Customer::firstOrCreate(['rfc' => Customer::RFC_PUBLICO_GENERAL], [
                    'legal_name' => 'PUBLICO EN GENERAL',
                    'tax_system' => '616',
                    'zip' => '01000',
                ]);

            $invoice = $invoiceService->generate(null, $customer, $this->itemsFromDetalles($apoyo), [
                'payment_form' => $data['payment_form'],
                'payment_method' => $data['payment_method'],
                'use' => $data['use'],
            ]);

            if ($invoice->status === 'failed') {
                $fallidos++;

                continue;
            }

            $apoyo->update(['estatus' => Apoyo::ESTATUS_FACTURADO, 'invoice_id' => $invoice->id]);
            $facturados++;
        }

        $message = match (true) {
            $facturados === 0 && $fallidos === 0 => 'No había apoyos aprobados por facturar.',
            $fallidos === 0 => "{$facturados} apoyo(s) facturado(s).",
            default => "{$facturados} apoyo(s) facturado(s), {$fallidos} con error.",
        };

        Inertia::flash('toast', ['type' => $fallidos > 0 && $facturados === 0 ? 'error' : 'success', 'message' => $message]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    protected function facturarValidationRules(): array
    {
        return [
            'rfc' => ['required', 'string', 'max:13'],
            'legal_name' => ['required', 'string', 'max:255'],
            'tax_system' => ['required', 'string', 'max:3'],
            'zip' => ['required', 'string', 'max:5'],
            'email' => ['nullable', 'email'],
            'payment_form' => ['required', 'string', 'max:2'],
            'payment_method' => ['required', 'in:PUE,PPD'],
            'use' => ['required', 'string', 'max:4'],
        ];
    }

    /**
     * @return list<array{description: string, sat_product_key: string, sat_unit_key: string, quantity: float, unit_price: float}>
     */
    protected function itemsFromDetalles(Apoyo $apoyo): array
    {
        return array_values($apoyo->detalles->map(fn (DetalleApoyo $detalle) => [
            'description' => $detalle->articulo,
            'sat_product_key' => '84111506',
            'sat_unit_key' => 'E48',
            'quantity' => (float) $detalle->cantidad,
            'unit_price' => (float) $detalle->costo_unidad,
        ])->all());
    }

    public function descargarXmls(Request $request): BinaryFileResponse|HttpResponse
    {
        $facturas = FacturaCompra::query()
            ->whereHas('articulos.detalleApoyos.apoyo', function (Builder $query) use ($request) {
                $query->where('estatus', Apoyo::ESTATUS_FACTURADO);

                if (! $request->user()->can('apoyos.view-all')) {
                    $query->where('user_id', $request->user()->id);
                }
            })
            ->get();

        if ($facturas->isEmpty()) {
            return response('No hay XMLs de apoyos facturados para descargar.', 404);
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'apoyos-xml');

        if ($zipPath === false) {
            abort(500, 'No se pudo preparar el archivo ZIP.');
        }

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::OVERWRITE);

        foreach ($facturas as $factura) {
            if (Storage::disk('public')->exists($factura->xml_path)) {
                $zip->addFile(Storage::disk('public')->path($factura->xml_path), basename($factura->xml_path));
            }
        }

        $zip->close();

        return response()->download($zipPath, 'facturas-apoyos-facturados.zip')->deleteFileAfterSend();
    }

    /**
     * @return Builder<Apoyo>
     */
    protected function visibleApoyos(Request $request): Builder
    {
        if ($request->user()->can('apoyos.view-all')) {
            return Apoyo::query();
        }

        return Apoyo::where('user_id', $request->user()->id);
    }

    protected function authorizeAccess(Request $request, Apoyo $apoyo): void
    {
        abort_unless(
            $request->user()->can('apoyos.view-all') || $apoyo->user_id === $request->user()->id,
            403
        );
    }

    /**
     * @return Collection<int, FacturaCompra>
     */
    protected function facturasDisponibles(): Collection
    {
        return FacturaCompra::with(['articulos' => fn ($query) => $query->orderBy('descripcion')])
            ->latest('fecha')
            ->get();
    }

    /**
     * Locks and decrements the available stock for each detalle line that references
     * an articulo_factura_compra, throwing a validation error if there isn't enough left.
     *
     * @param  list<array<string, mixed>>  $detalles
     */
    protected function reserveStock(array $detalles): void
    {
        foreach ($detalles as $detalle) {
            $articuloId = $detalle['articulo_factura_compra_id'] ?? null;

            if ($articuloId === null) {
                continue;
            }

            $articulo = ArticuloFacturaCompra::whereKey($articuloId)->lockForUpdate()->firstOrFail();
            $cantidad = (float) $detalle['cantidad'];

            if ($cantidad > (float) $articulo->cantidad_disponible) {
                throw ValidationException::withMessages([
                    'detalles' => "Solo quedan {$articulo->cantidad_disponible} disponibles de \"{$articulo->descripcion}\".",
                ]);
            }

            $articulo->decrement('cantidad_disponible', $cantidad);
        }
    }

    /**
     * Restores the available stock reserved by the given detalle rows.
     *
     * @param  Collection<int, DetalleApoyo>  $detalles
     */
    protected function releaseStock(Collection $detalles): void
    {
        foreach ($detalles as $detalle) {
            if ($detalle->articulo_factura_compra_id === null) {
                continue;
            }

            ArticuloFacturaCompra::whereKey($detalle->articulo_factura_compra_id)
                ->lockForUpdate()
                ->increment('cantidad_disponible', (float) $detalle->cantidad);
        }
    }

    /**
     * @return array{curp_path: ?string, rfc_path: ?string, ine_path: ?string, comprobante_domicilio_path: ?string}
     */
    protected function storedDocumentPaths(Request $request, ?Apoyo $apoyo = null): array
    {
        $store = function (string $field) use ($request): ?string {
            if (! $request->hasFile($field)) {
                return null;
            }

            $path = $request->file($field)->store('apoyos', 'public');

            return $path === false ? null : $path;
        };

        return [
            'curp_path' => $store('curp_archivo') ?? $apoyo?->curp_path,
            'rfc_path' => $store('rfc_archivo') ?? $apoyo?->rfc_path,
            'ine_path' => $store('ine') ?? $apoyo?->ine_path,
            'comprobante_domicilio_path' => $store('comprobante_domicilio') ?? $apoyo?->comprobante_domicilio_path,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'fecha' => ['required', 'date'],
            'beneficiario_id' => ['required', 'exists:beneficiarios,id'],
            'persona_apoya_id' => ['required', 'exists:personas_apoya,id'],
            'solicitud_recibo' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'curp' => ['nullable', 'string', 'max:18'],
            'curp_archivo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'rfc' => ['nullable', 'string', 'max:13'],
            'rfc_archivo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'ine' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'comprobante_domicilio' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.articulo_factura_compra_id' => ['nullable', 'integer', 'exists:articulos_factura_compra,id'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'detalles.*.articulo' => ['required', 'string', 'max:255'],
            'detalles.*.costo_unidad' => ['required', 'numeric', 'min:0'],
            'detalles.*.iva' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    /**
     * @param  array{articulo_factura_compra_id?: int|null, cantidad: float, articulo: string, costo_unidad: float, iva?: float|null}  $detalle
     * @return array<string, mixed>
     */
    protected function lineWithTotals(array $detalle): array
    {
        $subTotal = round($detalle['cantidad'] * $detalle['costo_unidad'], 2);
        $iva = round($detalle['iva'] ?? 0, 2);

        return [
            'articulo_factura_compra_id' => $detalle['articulo_factura_compra_id'] ?? null,
            'cantidad' => $detalle['cantidad'],
            'articulo' => $detalle['articulo'],
            'costo_unidad' => $detalle['costo_unidad'],
            'iva' => $iva,
            'sub_total' => $subTotal,
            'total' => $subTotal + $iva,
        ];
    }

    /**
     * @param  list<array{cantidad: float, articulo: string, costo_unidad: float, iva?: float|null}>  $detalles
     * @return array{sub_total: float, iva: float, monto_total: float}
     */
    protected function computeTotals(array $detalles): array
    {
        $lines = collect($detalles)->map(fn (array $detalle) => $this->lineWithTotals($detalle));

        return [
            'sub_total' => $lines->sum('sub_total'),
            'iva' => $lines->sum('iva'),
            'monto_total' => $lines->sum('total'),
        ];
    }
}
