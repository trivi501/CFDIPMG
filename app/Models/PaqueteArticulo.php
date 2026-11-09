<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaqueteArticulo extends Model
{
    protected $table = 'paquete_articulos';

    protected $fillable = ['paquete_id', 'articulo_factura_compra_id', 'descripcion', 'cantidad'];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Paquete, $this>
     */
    public function paquete(): BelongsTo
    {
        return $this->belongsTo(Paquete::class);
    }

    /**
     * @return BelongsTo<ArticuloFacturaCompra, $this>
     */
    public function articuloFacturaCompra(): BelongsTo
    {
        return $this->belongsTo(ArticuloFacturaCompra::class);
    }
}
