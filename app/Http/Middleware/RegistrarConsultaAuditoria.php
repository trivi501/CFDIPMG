<?php

namespace App\Http\Middleware;

use App\Models\Auditoria;
use App\Models\Invoice;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registra en la bitácora las consultas sensibles que no cambian datos: descargas de PDF y XML.
 * Los cambios de datos ya los registra App\Support\RegistroAuditoria.
 */
class RegistrarConsultaAuditoria
{
    /** Ruta => [acción, descripción, tabla, parámetro de ruta con el id del registro]. */
    private const RUTAS = [
        'invoices.pdf' => ['descarga', 'PDF de factura', 'invoices', 'invoice'],
        'invoices.xml' => ['descarga', 'XML de factura', 'invoices', 'invoice'],
        'facturas-compra.xml' => ['descarga', 'XML de factura de compra', 'facturas_compra', 'factura'],
        'apoyos.documento' => ['descarga', 'Documento de apoyo', 'apoyos', 'apoyo'],
        'apoyos.descargar-xmls' => ['descarga', 'ZIP con los XML de apoyos', 'apoyos', null],
    ];

    private const OMITIR = ['_token', '_method', 'password', 'password_confirmation'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $ruta = $request->route()?->getName();
        if ($ruta !== null && isset(self::RUTAS[$ruta]) && $response->getStatusCode() < 400) {
            $this->registrar($request, ...self::RUTAS[$ruta]);
        }

        return $response;
    }

    private function registrar(Request $request, string $accion, string $descripcion, ?string $tabla, ?string $parametro): void
    {
        $id = $parametro === null ? null : $request->route($parametro);
        if ($id instanceof Model) {
            $id = $id->getKey();
        }

        if ($tabla === 'invoices' && $id !== null && ($factura = Invoice::find($id)) !== null) {
            $descripcion .= ' '.trim(($factura->series ?? '').' '.($factura->folio ?? $factura->uuid ?? ''));
        }

        if ($request->route('tipo') !== null) {
            $descripcion .= ' ('.$request->route('tipo').')';
        }

        $parametros = collect($request->query())
            ->except(self::OMITIR)
            ->reject(fn (mixed $v) => $v === null || $v === '' || $v === [])
            ->map(fn (mixed $v) => is_array($v) ? (string) json_encode($v, JSON_UNESCAPED_UNICODE) : (string) $v)
            ->all();

        Auditoria::escribir([
            'accion' => $accion,
            'tabla' => $tabla,
            'auditable_id' => $id,
            'descripcion' => $descripcion,
            'despues' => $parametros ?: null,
        ]);
    }
}
