<?php

namespace App\Http\Middleware;

use App\Models\Auditoria;
use App\Models\Invoice;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Opcodes\LogViewer\Facades\LogViewer;
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
        'invoices.xmls' => ['descarga', 'ZIP con XML de facturas seleccionadas', 'invoices', null],
        'facturas-compra.xml' => ['descarga', 'XML de factura de compra', 'facturas_compra', 'factura'],
        'apoyos.documento' => ['descarga', 'Documento de apoyo', 'apoyos', 'apoyo'],
        'apoyos.descargar-xmls' => ['descarga', 'ZIP con los XML de apoyos', 'apoyos', null],
        'apoyos.factura' => ['descarga', 'Factura de apoyo', 'apoyos', 'apoyo'],
        'apoyos.facturas-xml' => ['descarga', 'ZIP con XML de facturas de apoyos seleccionados', 'apoyos', null],
        'log-viewer.files.download' => ['descarga', 'Log del sistema', null, null],
        'log-viewer.folders.download' => ['descarga', 'Carpeta de logs del sistema', null, null],
    ];

    /** La firma de los enlaces de descarga de logs no aporta nada a la bitácora. */
    private const OMITIR = ['_token', '_method', 'password', 'password_confirmation', 'signature', 'expires'];

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

        foreach (['tipo', 'formato'] as $detalle) {
            if (is_string($request->route($detalle))) {
                $descripcion .= ' ('.$request->route($detalle).')';
            }
        }

        if (is_string($archivo = $request->route('fileIdentifier'))) {
            $descripcion .= ' '.(LogViewer::getFile($archivo)->name ?? $archivo);
        } elseif (is_string($carpeta = $request->route('folderIdentifier'))) {
            $descripcion .= ' '.(LogViewer::getFolder($carpeta)?->cleanPath() ?? $carpeta);
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
