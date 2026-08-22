<?php

namespace App\Http\Controllers;

use App\Models\Apoyo;
use App\Models\Beneficiario;
use App\Models\PersonaApoya;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ApoyoController extends Controller
{
    public function index(Request $request): Response
    {
        $apoyos = Apoyo::with('beneficiario', 'personaApoya')
            ->when($request->filled('facturado'), fn ($query) => $query->where('facturado', $request->boolean('facturado')))
            ->latest('fecha')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('apoyos/index', [
            'apoyos' => $apoyos,
            'filters' => $request->only('facturado'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('apoyos/create', [
            'beneficiarios' => Beneficiario::orderBy('nombre')->get(['id', 'nombre']),
            'personasApoya' => PersonaApoya::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $request) {
            $apoyo = Apoyo::create([
                'fecha' => $data['fecha'],
                'beneficiario_id' => $data['beneficiario_id'],
                'persona_apoya_id' => $data['persona_apoya_id'],
                'preparado' => $data['preparado'],
                'facturado' => $data['facturado'],
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

    public function edit(Apoyo $apoyo): Response
    {
        return Inertia::render('apoyos/edit', [
            'apoyo' => $apoyo->load('detalles'),
            'beneficiarios' => Beneficiario::orderBy('nombre')->get(['id', 'nombre']),
            'personasApoya' => PersonaApoya::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function update(Request $request, Apoyo $apoyo): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $request, $apoyo) {
            $apoyo->update([
                'fecha' => $data['fecha'],
                'beneficiario_id' => $data['beneficiario_id'],
                'persona_apoya_id' => $data['persona_apoya_id'],
                'preparado' => $data['preparado'],
                'facturado' => $data['facturado'],
                'curp' => $data['curp'] ?? null,
                'rfc' => $data['rfc'] ?? null,
                ...$this->computeTotals($data['detalles']),
                'solicitud_recibo_path' => $request->hasFile('solicitud_recibo')
                    ? $request->file('solicitud_recibo')->store('apoyos', 'public')
                    : $apoyo->solicitud_recibo_path,
                ...$this->storedDocumentPaths($request, $apoyo),
            ]);

            $apoyo->detalles()->delete();

            foreach ($data['detalles'] as $detalle) {
                $apoyo->detalles()->create($this->lineWithTotals($detalle));
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Apoyo actualizado.']);

        return to_route('apoyos.index');
    }

    public function destroy(Apoyo $apoyo): RedirectResponse
    {
        $apoyo->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Apoyo eliminado.']);

        return to_route('apoyos.index');
    }

    /**
     * @return array{curp_path: ?string, rfc_path: ?string, ine_path: ?string, comprobante_domicilio_path: ?string}
     */
    protected function storedDocumentPaths(Request $request, ?Apoyo $apoyo = null): array
    {
        $store = fn (string $field) => $request->hasFile($field)
            ? $request->file($field)->store('apoyos', 'public')
            : null;

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
            'preparado' => ['required', 'boolean'],
            'facturado' => ['required', 'boolean'],
            'solicitud_recibo' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'curp' => ['nullable', 'string', 'max:18'],
            'curp_archivo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'rfc' => ['nullable', 'string', 'max:13'],
            'rfc_archivo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'ine' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'comprobante_domicilio' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'detalles.*.articulo' => ['required', 'string', 'max:255'],
            'detalles.*.costo_unidad' => ['required', 'numeric', 'min:0'],
            'detalles.*.iva' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    /**
     * @param  array{cantidad: float, articulo: string, costo_unidad: float, iva?: float|null}  $detalle
     * @return array<string, mixed>
     */
    protected function lineWithTotals(array $detalle): array
    {
        $subTotal = round($detalle['cantidad'] * $detalle['costo_unidad'], 2);
        $iva = round($detalle['iva'] ?? 0, 2);

        return [
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
