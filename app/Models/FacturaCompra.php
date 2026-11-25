<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacturaCompra extends Model
{
    protected $table = 'facturas_compra';

    protected $fillable = [
        'user_id',
        'area_id',
        'xml_path',
        'uuid',
        'xml_hash',
        'emisor',
        'fecha',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'total' => 'decimal:2',
        ];
    }

    /**
     * @return HasMany<ArticuloFacturaCompra, $this>
     */
    public function articulos(): HasMany
    {
        return $this->hasMany(ArticuloFacturaCompra::class);
    }

    /**
     * @return BelongsTo<Area, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Facturas que el usuario puede ver y cuyo inventario puede usar: las que subió él y las de su
     * área (regiduría o secretaría), igual que los apoyos; en un área privada, solo las suyas. Con
     * apoyos.view-all, todas. Las que no tienen dueño ni área solo las ve quien tiene apoyos.view-all.
     *
     * @param  Builder<FacturaCompra>  $query
     */
    public function scopeVisiblePara(Builder $query, User $user): void
    {
        if ($user->can('apoyos.view-all')) {
            return;
        }

        // En un área privada (como Cabildo) cada quien ve solo las facturas que subió.
        $area = $user->areaCompartida();

        $query->where(function (Builder $query) use ($user, $area) {
            $query->where('user_id', $user->id);

            if ($area !== null) {
                $query->orWhere('area_id', $area);
            }
        });
    }

    /**
     * Cuánto de la factura ya se repartió en apoyos y cuánto falta, según el inventario que queda
     * (un apoyo rechazado ya devolvió lo suyo). El IVA de cada artículo se reparte en proporción a
     * la cantidad. Requiere los artículos cargados.
     *
     * @return array{monto_total: float, monto_asignado: float, monto_por_asignar: float}
     */
    public function resumenDeUso(): array
    {
        $total = 0.0;
        $porAsignar = 0.0;

        foreach ($this->articulos as $articulo) {
            $cantidad = (float) $articulo->cantidad_total;
            $disponible = (float) $articulo->cantidad_disponible;
            $iva = (float) $articulo->iva;
            $costo = (float) $articulo->costo_unidad;

            $total += $cantidad * $costo + $iva;
            $porAsignar += $disponible * $costo + ($cantidad > 0 ? $iva * $disponible / $cantidad : 0);
        }

        $total = round($total, 2);
        $porAsignar = round($porAsignar, 2);

        return [
            'monto_total' => $total,
            'monto_asignado' => round($total - $porAsignar, 2),
            'monto_por_asignar' => $porAsignar,
        ];
    }

    public function esVisiblePara(User $user): bool
    {
        return self::query()->visiblePara($user)->whereKey($this->getKey())->exists();
    }
}
