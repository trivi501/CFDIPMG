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
}
