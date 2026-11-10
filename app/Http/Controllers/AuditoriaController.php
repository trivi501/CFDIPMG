<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Bitácora de auditoría: altas, modificaciones, eliminaciones, cambios masivos, descargas y accesos. */
class AuditoriaController extends Controller
{
    /** Nombre legible de cada tabla; las que no están aquí se muestran con su nombre técnico. */
    private const MODULOS = [
        'invoices' => 'Facturas',
        'payment_receipts' => 'Recibos',
        'customers' => 'Clientes',
        'products' => 'Productos',
        'apoyos' => 'Apoyos',
        'detalle_apoyos' => 'Artículos de apoyos',
        'beneficiarios' => 'Beneficiarios',
        'personas_apoya' => 'Personas de apoyo',
        'facturas_compra' => 'Facturas de compra',
        'articulos_factura_compra' => 'Artículos de facturas de compra',
        'paquetes' => 'Paquetes',
        'paquete_articulos' => 'Artículos de paquetes',
        'areas' => 'Áreas',
        'api_clients' => 'API clients',
        'passkeys' => 'Llaves de acceso (passkeys)',
        'users' => 'Usuarios y accesos',
        'roles' => 'Roles',
        'permissions' => 'Permisos',
        'model_has_roles' => 'Roles de usuarios',
        'role_has_permissions' => 'Permisos de roles',
        'model_has_permissions' => 'Permisos de usuarios',
    ];

    private const ZONA = 'America/Mexico_City';

    public function index(Request $request): Response
    {
        $query = Auditoria::with('user:id,name')->latest('id');

        foreach (['tabla', 'accion', 'lote'] as $campo) {
            if ($valor = $request->string($campo)->toString()) {
                $query->where($campo, $valor);
            }
        }

        if ($usuario = $request->string('usuario')->toString()) {
            $usuario === 'sistema' ? $query->whereNull('user_id') : $query->where('user_id', $usuario);
        }

        if ($registro = $request->string('registro')->toString()) {
            $query->where('auditable_id', $registro);
        }

        // La app guarda en UTC; el filtro se captura en hora de México.
        if ($desde = $request->date('desde', null, self::ZONA)) {
            $query->where('created_at', '>=', $desde->startOfDay()->utc());
        }

        if ($hasta = $request->date('hasta', null, self::ZONA)) {
            $query->where('created_at', '<=', $hasta->endOfDay()->utc());
        }

        if ($texto = trim($request->string('buscar')->toString())) {
            $query->where(fn ($q) => $q
                ->where('origen', 'like', "%{$texto}%")
                ->orWhere('descripcion', 'like', "%{$texto}%")
                ->orWhere('auditable_id', $texto)
                ->orWhere('antes', 'like', "%{$texto}%")
                ->orWhere('despues', 'like', "%{$texto}%"));
        }

        $registros = $query->paginate(50)->withQueryString()->through(fn (Auditoria $a) => [
            'id' => $a->id,
            'fecha' => $a->created_at->toIso8601String(),
            'usuario' => $a->user?->name,
            'accion' => $a->accion,
            'tabla' => $a->tabla,
            'modulo' => self::MODULOS[$a->tabla] ?? $a->tabla ?? '—',
            'registro' => $a->auditable_id,
            'enlace' => $this->enlace($a),
            'descripcion' => $a->descripcion,
            'origen' => $a->origen,
            'lote' => $a->lote,
            'antes' => $a->antes,
            'despues' => $a->despues,
            'ip' => $a->ip,
        ]);

        $tablas = Auditoria::query()->whereNotNull('tabla')->distinct()->pluck('tabla')
            ->map(fn (string $t) => ['valor' => $t, 'etiqueta' => self::MODULOS[$t] ?? $t])
            ->sortBy('etiqueta', SORT_NATURAL | SORT_FLAG_CASE)->values();

        return Inertia::render('auditoria/index', [
            'registros' => $registros,
            'filtros' => $request->only(['tabla', 'usuario', 'accion', 'desde', 'hasta', 'lote', 'registro', 'buscar']),
            'tablas' => $tablas,
            'usuarios' => User::whereIn('id', Auditoria::query()->whereNotNull('user_id')->select('user_id')->distinct())
                ->orderBy('name')->get(['id', 'name']),
            'acciones' => Auditoria::ACCIONES,
        ]);
    }

    /** Pantalla donde se ve el registro afectado, si existe una. */
    private function enlace(Auditoria $a): ?string
    {
        if ($a->accion === 'eliminación' || blank($a->auditable_id)) {
            return null;
        }

        $id = $a->auditable_id;

        return match ($a->tabla) {
            'invoices' => "/invoices/{$id}",
            'apoyos' => "/apoyos/{$id}",
            'customers' => "/customers/{$id}/edit",
            'products' => "/products/{$id}/edit",
            'beneficiarios' => "/beneficiarios/{$id}/edit",
            'personas_apoya' => "/personas-apoya/{$id}/edit",
            'paquetes' => "/paquetes/{$id}/edit",
            'areas' => "/areas/{$id}/edit",
            'users' => "/users/{$id}/edit",
            'roles' => "/roles/{$id}/edit",
            default => null,
        };
    }
}
