<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Apoyo extends Model
{
    public const ESTATUS_PREPARADO = 'preparado';

    public const ESTATUS_APROBADO = 'aprobado';

    public const ESTATUS_FACTURADO = 'facturado';

    public const ESTATUS_RECHAZADO = 'rechazado';

    protected $fillable = [
        'fecha',
        'beneficiario_id',
        'persona_apoya_id',
        'user_id',
        'estatus',
        'invoice_id',
        'sub_total',
        'iva',
        'monto_total',
        'solicitud_recibo_path',
        'curp',
        'curp_path',
        'rfc',
        'rfc_path',
        'ine_path',
        'comprobante_domicilio_path',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'sub_total' => 'decimal:2',
            'iva' => 'decimal:2',
            'monto_total' => 'decimal:2',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<DetalleApoyo, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleApoyo::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
