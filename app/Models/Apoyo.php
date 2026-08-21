<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Apoyo extends Model
{
    protected $fillable = [
        'fecha',
        'beneficiario_id',
        'persona_apoya_id',
        'sub_total',
        'iva',
        'monto_total',
        'preparado',
        'facturado',
        'solicitud_recibo_path',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'sub_total' => 'decimal:2',
            'iva' => 'decimal:2',
            'monto_total' => 'decimal:2',
            'preparado' => 'boolean',
            'facturado' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Beneficiario, $this>
     */
    public function beneficiario(): BelongsTo
    {
        return $this->belongsTo(Beneficiario::class);
    }

    /**
     * @return BelongsTo<PersonaApoya, $this>
     */
    public function personaApoya(): BelongsTo
    {
        return $this->belongsTo(PersonaApoya::class);
    }

    /**
     * @return HasMany<DetalleApoyo, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleApoyo::class);
    }
}
