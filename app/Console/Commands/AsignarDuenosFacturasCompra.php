<?php

namespace App\Console\Commands;

use App\Models\FacturaCompra;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pone como dueña de cada factura de compra SIN dueño a la persona que la subió, para que en un
 * área privada (Cabildo) solo ella la vea. Busca en este orden y usa la primera pista que apunte
 * a UNA sola persona:
 *
 *  1. La bitácora: quién dio de alta la factura.
 *  2. La bitácora: quién armó paquetes con ella.
 *  3. Los apoyos: quién capturó apoyos con sus artículos.
 *
 * Nunca cambia una factura que ya tiene dueño (las nuevas lo guardan al subirse, y un Admin puede
 * corregirlo en Facturas de compra). La factura toma también el área de su dueño si no tenía.
 * Las que no tienen pista, o cuyas pistas apuntan a varias personas, solo se reportan.
 */
#[Signature('facturas-compra:asignar-duenos {--pretend : Solo muestra lo que haría, sin cambiar nada}')]
#[Description('Asigna cada factura de compra sin dueño a quien la subió (no cambia las que ya tienen)')]
class AsignarDuenosFacturasCompra extends Command
{
    public function handle(): int
    {
        $pretend = (bool) $this->option('pretend');
        $usuarios = User::get(['id', 'name', 'area_id'])->keyBy('id');
        $asignadas = [];
        $pendientes = [];

        foreach (FacturaCompra::whereNull('user_id')->orderBy('id')->get() as $factura) {
            [$userId, $segun, $motivo] = $this->dueno($factura);
            $usuario = $userId === null ? null : $usuarios->get($userId);

            $etiqueta = ($factura->emisor ?? 'Factura de compra').($factura->fecha ? ' · '.Carbon::parse($factura->fecha)->format('d/m/Y') : '');

            if ($usuario === null) {
                $pendientes[] = [$factura->id, $etiqueta, $motivo ?? 'El usuario que la subió ya no existe'];

                continue;
            }

            $asignadas[] = [$factura->id, $etiqueta, $usuario->name, $segun];

            if (! $pretend) {
                $factura->update([
                    'user_id' => $usuario->id,
                    'area_id' => $factura->area_id ?? $usuario->area_id,
                ]);
            }
        }

        if ($asignadas === [] && $pendientes === []) {
            $this->info('Todas las facturas de compra ya tienen dueño. No hay nada que hacer.');

            return self::SUCCESS;
        }

        if ($asignadas !== []) {
            $this->info(($pretend ? 'Se asignarían ' : 'Se asignaron ').count($asignadas).' factura(s):');
            $this->table(['ID', 'Factura', 'Dueño', 'Según'], $asignadas);
        }

        if ($pendientes !== []) {
            $this->warn(count($pendientes).' factura(s) sin dueño claro: asígnalas a mano en Facturas de compra (como Admin).');
            $this->table(['ID', 'Factura', 'Por qué'], $pendientes);
        }

        if ($pretend) {
            $this->comment('Modo --pretend: no se cambió nada.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: int|null, 1: string, 2: string|null} [usuario, de dónde salió, por qué no hay]
     */
    protected function dueno(FacturaCompra $factura): array
    {
        $pistas = [
            'bitácora: la subió' => DB::table('auditoria')
                ->where('tabla', 'facturas_compra')
                ->where('accion', 'alta')
                ->where('auditable_id', (string) $factura->id)
                ->whereNotNull('user_id')
                ->distinct()
                ->pluck('user_id'),
            'bitácora: armó paquetes con ella' => DB::table('auditoria')
                ->where('tabla', 'paquetes')
                ->where('accion', 'alta')
                ->whereIn('auditable_id', DB::table('paquetes')->where('factura_compra_id', $factura->id)->selectRaw('CAST(id AS CHAR)'))
                ->whereNotNull('user_id')
                ->distinct()
                ->pluck('user_id'),
            'capturó apoyos con sus artículos' => DB::table('apoyos')
                ->join('detalle_apoyos', 'detalle_apoyos.apoyo_id', '=', 'apoyos.id')
                ->join('articulos_factura_compra', 'articulos_factura_compra.id', '=', 'detalle_apoyos.articulo_factura_compra_id')
                ->where('articulos_factura_compra.factura_compra_id', $factura->id)
                ->whereNotNull('apoyos.user_id')
                ->distinct()
                ->pluck('apoyos.user_id'),
        ];

        $varios = false;
        foreach ($pistas as $segun => $ids) {
            if ($ids->count() === 1) {
                return [(int) $ids->first(), $segun, null];
            }
            $varios = $varios || $ids->count() > 1;
        }

        return [null, '', $varios ? 'La usaron varias personas' : 'No hay registro de quién la subió'];
    }
}
