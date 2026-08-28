<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArticuloFacturaCompra extends Model
{
    protected $table = 'articulos_factura_compra';

    protected $fillable = [
        'factura_compra_id',
        'descripcion',
        'cantidad_total',
        'costo_unidad',
        'iva',
        'cantidad_disponible',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_total' => 'decimal:2',
            'costo_unidad' => 'decimal:2',
            'iva' => 'decimal:2',
            'cantidad_disponible' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<FacturaCompra, $this>
     */
    public function facturaCompra(): BelongsTo
    {
        return $this->belongsTo(FacturaCompra::class);
    }

    /**
     * @return HasMany<DetalleApoyo, $this>
     */
    public function detalleApoyos(): HasMany
    {
        return $this->hasMany(DetalleApoyo::class);
    }
}
