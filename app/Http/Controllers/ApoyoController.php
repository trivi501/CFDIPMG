<?php

namespace App\Http\Controllers;

use App\Models\Apoyo;
use App\Models\Area;
use App\Models\ArticuloFacturaCompra;
use App\Models\Beneficiario;
use App\Models\Customer;
use App\Models\DetalleApoyo;
use App\Models\FacturaCompra;
use App\Models\Paquete;
use App\Models\PersonaApoya;
use App\Services\Facturapi\FacturapiClient;
use App\Services\InvoiceService;
use App\Support\ImpuestosFactura;
use App\Support\UsoCfdi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use ZipArchive;

class ApoyoController extends Controller
{
    /** Datos fijos del CFDI de un apoyo: 93161700 (Administración tributaria), unidad C62, comprobante de Egreso y PUE. */
    private const CLAVE_PRODUCTO = '93161700';

    private const CLAVE_UNIDAD = 'C62';

    private const CFDI_APOYO = ['type' => 'E', 'payment_method' => 'PUE'];

    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'estatus' => ['nullable', 'string', 'in:'.implode(',', [
                Apoyo::ESTATUS_PREPARADO, Apoyo::ESTATUS_APROBADO, Apoyo::ESTATUS_FACTURADO, Apoyo::ESTATUS_RECHAZADO,
            ])],
            'buscar' => ['nullable', 'string', 'max:100'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'area' => ['nullable', 'integer'],
        ]);
        $puedeVerTodo = $request->user()->can('apoyos.view-all');

        $apoyos = $this->visibleApoyos($request)
            ->with('beneficiario', 'personaApoya', 'area:id,nombre', 'detalles.articuloFacturaCompra.facturaCompra:id,uuid,emisor,fecha')
            ->when($filtros['estatus'] ?? null, fn (Builder $query, string $estatus) => $query->where('estatus', $estatus))
            ->when(trim($filtros['buscar'] ?? ''), function (Builder $query, string $buscar) {
                $query->where(function (Builder $query) use ($buscar) {
                    $query->whereHas('beneficiario', fn (Builder $q) => $q->where('nombre', 'like', "%{$buscar}%"))
                        ->orWhereHas('personaApoya', fn (Builder $q) => $q->where('nombre', 'like', "%{$buscar}%"))
                        ->orWhere('rfc', 'like', "%{$buscar}%");
                });
            })
            ->when($filtros['desde'] ?? null, fn (Builder $query, string $desde) => $query->whereDate('fecha', '>=', $desde))
            ->when($filtros['hasta'] ?? null, fn (Builder $query, string $hasta) => $query->whereDate('fecha', '<=', $hasta))
            // Quien solo ve su área no necesita este filtro; visibleApoyos ya lo limita.
            ->when($puedeVerTodo && isset($filtros['area']) ? (int) $filtros['area'] : null, fn (Builder $query, int $area) => $query->where('area_id', $area))
            ->latest('fecha')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Apoyo $apoyo) => [
                ...Arr::except($apoyo->toArray(), ['detalles']),
                // Facturas de compra de las que salieron los artículos del apoyo.
                'facturas_compra' => $apoyo->detalles
                    ->map(fn (DetalleApoyo $detalle) => $detalle->articuloFacturaCompra?->facturaCompra)
                    ->filter()
                    ->unique('id')
                    ->map(fn (FacturaCompra $factura) => $factura->only(['id', 'uuid', 'emisor']))
                    ->values(),
            ]);

        return Inertia::render('apoyos/index', [
            'apoyos' => $apoyos,
            'filters' => Arr::only($filtros, ['estatus', 'buscar', 'desde', 'hasta', 'area']),
            'areas' => $puedeVerTodo ? Area::orderBy('nombre')->get(['id', 'nombre']) : [],
            // Cuántos tomarían "Aprobar todo" y "Facturar todo", sin importar el filtro ni la página.
            'pendientes' => [
                'preparados' => $this->visibleApoyos($request)->where('estatus', Apoyo::ESTATUS_PREPARADO)->count(),
                'aprobados' => $this->visibleApoyos($request)->where('estatus', Apoyo::ESTATUS_APROBADO)->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('apoyos/create', [
            'beneficiarios' => Beneficiario::orderBy('nombre')->get(['id', 'nombre']),
            'personasApoya' => PersonaApoya::orderBy('nombre')->get(['id', 'nombre']),
            'facturasDisponibles' => $this->facturasDisponibles(),
            'paquetes' => Paquete::with('articulos')->orderBy('nombre')->get(),
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
                'area_id' => $request->user()->area_id,
                'estatus' => Apoyo::ESTATUS_PREPARADO,
                'curp' => $data['curp'] ?? null,
                'rfc' => $data['rfc'] ?? null,
                ...$this->computeTotals($data['detalles']),
                'solicitud_recibo_path' => $request->hasFile('solicitud_recibo')
                    ? $request->file('solicitud_recibo')->store('apoyos', 'local')
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

        $apoyo->load('detalles', 'beneficiario', 'personaApoya', 'user', 'invoice', 'area');

        return Inertia::render('apoyos/show', [
            'apoyo' => $apoyo,
        ]);
    }

    public function documento(Request $request, Apoyo $apoyo, string $tipo): StreamedResponse
    {
        $this->authorizeAccess($request, $apoyo);

        $path = match ($tipo) {
            'solicitud_recibo' => $apoyo->solicitud_recibo_path,
            'curp' => $apoyo->curp_path,
            'rfc' => $apoyo->rfc_path,
            'ine' => $apoyo->ine_path,
            'comprobante_domicilio' => $apoyo->comprobante_domicilio_path,
            default => null,
        };

        abort_unless($path !== null && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['X-Content-Type-Options' => 'nosniff']);
    }

    /**
     * PDF o XML de la factura de un apoyo. Usa el mismo acceso que ver el apoyo (quien lo capturó,
     * su área o apoyos.view-all), así el rol Apoyos descarga lo suyo sin invoices.download, que le
     * abriría todas las facturas del sistema.
     */
    public function factura(Request $request, Apoyo $apoyo, string $formato, FacturapiClient $facturapi): HttpResponse
    {
        $this->authorizeAccess($request, $apoyo);

        $invoice = $apoyo->invoice;

        abort_unless(
            $invoice !== null && $invoice->facturapi_invoice_id !== null && $invoice->uuid !== null,
            404,
            'Este apoyo no tiene una factura timbrada.'
        );

        if ($formato === 'pdf') {
            return response($facturapi->downloadPdf($invoice->facturapi_invoice_id)->body(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="factura-'.$invoice->folio.'.pdf"',
            ]);
        }

        return response($facturapi->downloadXml($invoice->facturapi_invoice_id)->body(), 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="factura-'.$invoice->folio.'.xml"',
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
            'paquetes' => Paquete::with('articulos')->orderBy('nombre')->get(),
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
                    ? $request->file('solicitud_recibo')->store('apoyos', 'local')
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

    public function aprobarTodo(Request $request): RedirectResponse
    {
        $request->validate($this->seleccionRules());

        // Uno por uno (no un UPDATE masivo) para que la bitácora guarde cada apoyo aprobado.
        $apoyos = $this->seleccionados($request)->where('estatus', Apoyo::ESTATUS_PREPARADO)->get();
        $apoyos->each->update(['estatus' => Apoyo::ESTATUS_APROBADO]);

        Inertia::flash('toast', $apoyos->isEmpty()
            ? ['type' => 'error', 'message' => 'No había apoyos preparados por aprobar.']
            : ['type' => 'success', 'message' => "{$apoyos->count()} apoyo(s) aprobado(s)."]);

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

        // Un RFC ya registrado factura con su régimen guardado, no con el capturado en el formulario.
        UsoCfdi::validar(
            $data['use'],
            Customer::where('rfc', $data['rfc'])->value('tax_system') ?? $data['tax_system'],
        );

        $customer = Customer::firstOrCreate(['rfc' => $data['rfc']], [
            'legal_name' => $data['legal_name'],
            'tax_system' => $data['tax_system'],
            'zip' => $data['zip'],
            'email' => $data['email'] ?? null,
        ]);

        $invoice = $invoiceService->generate(null, $customer, $this->itemsFromDetalles($apoyo), [
            ...self::CFDI_APOYO,
            'payment_form' => $data['payment_form'],
            'use' => $data['use'],
            ...ImpuestosFactura::desde($request, ivaPorDefecto: false),
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
            ...ImpuestosFactura::reglas(),
            ...$this->seleccionRules(),
        ]);

        $impuestos = ImpuestosFactura::desde($request, ivaPorDefecto: false);

        $apoyos = $this->seleccionados($request)
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
                ...self::CFDI_APOYO,
                'payment_form' => $data['payment_form'],
                'use' => self::usoParaRegimen($customer->tax_system),
                ...$impuestos,
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
            'use' => ['required', 'string', 'max:4'],
            ...ImpuestosFactura::reglas(),
        ];
    }

    /** Gastos en general cuando el régimen del receptor lo admite; si no (616, público en general), Sin efectos fiscales. */
    protected static function usoParaRegimen(string $regimen): string
    {
        return UsoCfdi::permitido('G03', $regimen) ? 'G03' : 'S01';
    }

    /**
     * @return list<array{description: string, sat_product_key: string, sat_unit_key: string, quantity: float, unit_price: float}>
     */
    protected function itemsFromDetalles(Apoyo $apoyo): array
    {
        return array_values($apoyo->detalles->map(fn (DetalleApoyo $detalle) => [
            'description' => $detalle->articulo,
            'sat_product_key' => self::CLAVE_PRODUCTO,
            'sat_unit_key' => self::CLAVE_UNIDAD,
            'quantity' => (float) $detalle->cantidad,
            'unit_price' => (float) $detalle->costo_unidad,
        ])->all());
    }

    /**
     * ZIP con los XML de las facturas emitidas para los apoyos marcados en la tabla. Solo toma los
     * que el usuario puede ver y que ya tienen factura timbrada; los demás ids se ignoran.
     */
    public function facturasXml(Request $request, FacturapiClient $facturapi): BinaryFileResponse|HttpResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.InvoiceController::MAX_XMLS],
            'ids.*' => ['integer'],
        ]);

        $apoyos = $this->visibleApoyos($request)
            ->whereIn('id', $data['ids'])
            ->whereHas('invoice', fn (Builder $query) => $query->whereNotNull('facturapi_invoice_id')->whereNotNull('uuid'))
            ->with('invoice', 'beneficiario:id,nombre')
            ->get();

        if ($apoyos->isEmpty()) {
            return response('Ninguno de los apoyos seleccionados tiene una factura timbrada.', 404);
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'apoyos-facturas-xml');

        if ($zipPath === false) {
            abort(500, 'No se pudo preparar el archivo ZIP.');
        }

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::OVERWRITE);
        $errores = [];

        foreach ($apoyos as $apoyo) {
            $invoice = $apoyo->invoice;
            $nombre = trim(($invoice->series ?? '').($invoice->folio ?? '').'-'.$invoice->uuid, '-').'.xml';

            try {
                $zip->addFromString($nombre, $facturapi->downloadXml((string) $invoice->facturapi_invoice_id)->body());
            } catch (Throwable $e) {
                Log::warning('No se pudo descargar el XML de la factura del apoyo', ['apoyo_id' => $apoyo->id, 'error' => $e->getMessage()]);
                $errores[] = "{$nombre}: {$e->getMessage()}";
            }
        }

        if ($errores !== []) {
            $zip->addFromString('ERRORES.txt', "No se pudieron descargar estos XML:\n\n".implode("\n", $errores)."\n");
        }

        $zip->close();

        return response()->download($zipPath, 'facturas-apoyos-'.now('America/Mexico_City')->format('Y-m-d').'.zip')
            ->deleteFileAfterSend();
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
            if (Storage::disk('local')->exists($factura->xml_path)) {
                $zip->addFile(Storage::disk('local')->path($factura->xml_path), basename($factura->xml_path));
            }
        }

        $zip->close();

        return response()->download($zipPath, 'facturas-apoyos-facturados.zip')->deleteFileAfterSend();
    }

    /**
     * Cada usuario ve los apoyos de su área (secretaría o regiduría) y los que él registró.
     *
     * @return Builder<Apoyo>
     */
    protected function visibleApoyos(Request $request): Builder
    {
        $user = $request->user();

        if ($user->can('apoyos.view-all')) {
            return Apoyo::query();
        }

        return Apoyo::where(function (Builder $query) use ($user) {
            $query->where('user_id', $user->id);

            if ($user->area_id !== null) {
                $query->orWhere('area_id', $user->area_id);
            }
        });
    }

    /**
     * "Aprobar todo" y "Facturar todo" pueden recibir los apoyos marcados en la tabla.
     *
     * @return array<string, mixed>
     */
    protected function seleccionRules(): array
    {
        return [
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer'],
        ];
    }

    /**
     * Los apoyos marcados, o todos los visibles si no se marcó ninguno. Un id que el usuario no
     * puede ver simplemente no se toma en cuenta.
     *
     * @return Builder<Apoyo>
     */
    protected function seleccionados(Request $request): Builder
    {
        $ids = $request->array('ids');

        return $this->visibleApoyos($request)
            ->when($ids !== [], fn (Builder $query) => $query->whereIn('id', $ids));
    }

    protected function authorizeAccess(Request $request, Apoyo $apoyo): void
    {
        $user = $request->user();

        abort_unless(
            $user->can('apoyos.view-all')
                || $apoyo->user_id === $user->id
                || ($user->area_id !== null && $apoyo->area_id === $user->area_id),
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

            $path = $request->file($field)->store('apoyos', 'local');

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
