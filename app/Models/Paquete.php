<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Paquete extends Model
{
    protected $fillable = ['factura_compra_id', 'nombre'];

    /**
     * @return BelongsTo<FacturaCompra, $this>
     */
    public function facturaCompra(): BelongsTo
    {
        return $this->belongsTo(FacturaCompra::class);
    }

    /**
     * @return HasMany<PaqueteArticulo, $this>
     */
    public function articulos(): HasMany
    {
        return $this->hasMany(PaqueteArticulo::class);
    }

    /**
     * Paquetes completos que alcanzan con el inventario actual: lo limita el artículo más escaso.
     * Misma regla que paquetesDisponibles() en resources/js/lib/paquetes.ts.
     */
    public function disponibles(): int
    {
        $this->loadMissing('articulos.articuloFacturaCompra');

        if ($this->articulos->isEmpty()) {
            return 0;
        }

        return (int) $this->articulos->min(function (PaqueteArticulo $componente): int {
            $porPaquete = (float) $componente->cantidad;
            $existencia = (float) ($componente->articuloFacturaCompra->cantidad_disponible ?? 0);

            // El margen evita que 0.3 / 0.1 se redondee a 2 por el punto flotante.
            return $porPaquete > 0 ? (int) floor($existencia / $porPaquete + 1e-9) : PHP_INT_MAX;
        });
    }
}
