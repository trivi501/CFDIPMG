<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleApoyo extends Model
{
    protected $fillable = [
        'apoyo_id',
        'cantidad',
        'articulo',
        'costo_unidad',
        'iva',
        'sub_total',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'costo_unidad' => 'decimal:2',
            'iva' => 'decimal:2',
            'sub_total' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Apoyo, $this>
     */
    public function apoyo(): BelongsTo
    {
        return $this->belongsTo(Apoyo::class);
    }
}
