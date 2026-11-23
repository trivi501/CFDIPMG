<?php

namespace App\Http\Controllers;

use App\Models\Apoyo;
use App\Models\Area;
use App\Models\Invoice;
use App\Models\PaymentReceipt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public const PERIODOS = ['mes', 'anio', '12m', 'todo'];

    /** Las fechas se guardan en UTC; los meses se cuentan en hora local. */
    public const ZONA = 'America/Mexico_City';

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $periodo = in_array($request->query('periodo'), self::PERIODOS, true) ? (string) $request->query('periodo') : 'anio';

        return Inertia::render('dashboard', [
            'stats' => [
                'pendingReceipts' => PaymentReceipt::where('status', 'pending')->count(),
                'invoicedThisMonth' => Invoice::where('status', 'valid')
                    ->whereMonth('issued_at', now()->month)
                    ->whereYear('issued_at', now()->year)
                    ->count(),
                'failedReceipts' => PaymentReceipt::where('status', 'failed')->count(),
                'totalAmountThisMonth' => Invoice::where('status', 'valid')
                    ->whereMonth('issued_at', now()->month)
                    ->whereYear('issued_at', now()->year)
                    ->sum('total'),
            ],
            'recentInvoices' => Invoice::with('customer')
                ->latest('issued_at')
                ->take(5)
                ->get(),
            'graficas' => $user->can('apoyos.view') ? $this->graficas($user, $periodo) : null,
        ]);
    }

    /**
     * Facturas de apoyos (timbradas y vigentes) por área, por usuario en las áreas privadas
     * (Cabildo) y por mes. Cada quien ve solo lo que puede ver en Apoyos.
     *
     * @return array<string, mixed>
     */
    protected function graficas(User $user, string $periodo): array
    {
        $ahora = CarbonImmutable::now(self::ZONA);
        $desde = match ($periodo) {
            'mes' => $ahora->startOfMonth(),
            'anio' => $ahora->startOfYear(),
            '12m' => $ahora->startOfMonth()->subMonths(11),
            default => null,
        };

        $filas = Apoyo::query()
            ->visiblePara($user)
            ->join('invoices', 'invoices.id', '=', 'apoyos.invoice_id')
            ->where('invoices.status', 'valid')
            ->whereNotNull('invoices.issued_at')
            ->get(['apoyos.area_id', 'apoyos.user_id', 'apoyos.invoice_id', 'invoices.total', 'invoices.issued_at'])
            ->map(fn (Apoyo $fila) => [
                'area_id' => $fila->area_id === null ? null : (int) $fila->area_id,
                'user_id' => $fila->user_id === null ? null : (int) $fila->user_id,
                'invoice_id' => (int) $fila->invoice_id,
                'total' => (float) $fila->getAttribute('total'),
                'fecha' => CarbonImmutable::parse((string) $fila->getAttribute('issued_at'), 'UTC')->setTimezone(self::ZONA),
            ])
            // Una factura puede venir de varios apoyos; se cuenta una vez.
            ->unique('invoice_id')
            ->values();

        $enPeriodo = $desde === null ? $filas : $filas->filter(fn (array $fila) => $fila['fecha']->greaterThanOrEqualTo($desde))->values();

        $areas = Area::whereIn('id', $filas->pluck('area_id')->filter()->unique())->get(['id', 'nombre', 'privada'])->keyBy('id');
        $usuarios = User::whereIn('id', $filas->pluck('user_id')->filter()->unique())->pluck('name', 'id');

        $porArea = $enPeriodo
            ->groupBy(fn (array $fila) => $fila['area_id'] ?? 0)
            ->map(fn (Collection $grupo, int $areaId) => [
                'id' => $areaId,
                'nombre' => $areas->get($areaId)->nombre ?? 'Sin área',
                ...$this->resumen($grupo),
            ])
            ->sortByDesc('monto')
            ->values();

        $porUsuario = $areas
            ->filter(fn (Area $area) => $area->privada)
            ->map(fn (Area $area) => [
                'id' => $area->id,
                'nombre' => $area->nombre,
                'usuarios' => $enPeriodo
                    ->where('area_id', $area->id)
                    ->groupBy(fn (array $fila) => $fila['user_id'] ?? 0)
                    ->map(fn (Collection $grupo, int $userId) => [
                        'id' => $userId,
                        'nombre' => $usuarios->get($userId) ?? 'Sin usuario',
                        ...$this->resumen($grupo),
                    ])
                    ->sortByDesc('monto')
                    ->values(),
            ])
            ->filter(fn (array $area) => $area['usuarios']->isNotEmpty())
            ->sortBy('nombre')
            ->values();

        // Tendencia: siempre los últimos 12 meses, sin importar el periodo elegido.
        $porMes = collect(range(11, 0))->map(function (int $atras) use ($ahora, $filas) {
            $mes = $ahora->startOfMonth()->subMonths($atras);
            $grupo = $filas->filter(fn (array $fila) => $fila['fecha']->format('Y-m') === $mes->format('Y-m'));

            return ['mes' => $mes->format('Y-m'), ...$this->resumen($grupo)];
        });

        return [
            'periodo' => $periodo,
            'totales' => $this->resumen($enPeriodo),
            'porArea' => $porArea,
            'porUsuario' => $porUsuario,
            'porMes' => $porMes,
        ];
    }

    /**
     * @param  Collection<int, covariant array{total: float}>  $grupo
     * @return array{facturas: int, monto: float}
     */
    protected function resumen(Collection $grupo): array
    {
        return ['facturas' => $grupo->count(), 'monto' => round($grupo->sum('total'), 2)];
    }
}
