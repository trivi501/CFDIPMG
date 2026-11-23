<?php

namespace App\Console\Commands;

use App\Models\Area;
use App\Models\FacturaCompra;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Solo lectura. Explica, por área, quién comparte facturas de compra y apoyos con quién:
 * si el área es privada (cada quien lo suyo), qué usuarios tiene, y cuáles ven todo de todos
 * por tener apoyos.view-all (Admin, Facturación) sin importar el área.
 */
#[Signature('areas:revisar')]
#[Description('Muestra qué áreas son privadas y qué usuarios ven las facturas de compra de otros (no cambia nada)')]
class RevisarAreas extends Command
{
    public function handle(): int
    {
        $usuarios = User::with('roles')->orderBy('name')->get();

        foreach (Area::orderBy('nombre')->get() as $area) {
            $this->newLine();
            $this->line(sprintf(
                '<options=bold>%s</> — %s',
                $area->nombre,
                $area->privada
                    ? '<fg=green>privada: cada usuario ve solo lo que él sube</>'
                    : '<fg=yellow>compartida: sus integrantes ven lo de todos</>',
            ));

            $integrantes = $usuarios->where('area_id', $area->id);

            if ($integrantes->isEmpty()) {
                $this->line('  (sin usuarios)');

                continue;
            }

            $this->table(['Usuario', 'Rol', 'Facturas de compra que ve', '¿Ve las de todos?'], $integrantes->map(fn (User $user) => [
                $user->name,
                $user->roles->pluck('name')->join(', ') ?: '—',
                FacturaCompra::query()->visiblePara($user)->count(),
                $user->can('apoyos.view-all') ? 'SÍ, por su rol' : 'no',
            ])->values()->all());
        }

        $sinArea = $usuarios->whereNull('area_id');
        if ($sinArea->isNotEmpty()) {
            $this->newLine();
            $this->line('<options=bold>Usuarios sin área</> — solo ven lo que ellos suben: '.$sinArea->pluck('name')->join(', '));
        }

        return self::SUCCESS;
    }
}
