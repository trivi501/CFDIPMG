<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacturaCompra extends Model
{
    protected $table = 'facturas_compra';

    protected $fillable = [
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
}
