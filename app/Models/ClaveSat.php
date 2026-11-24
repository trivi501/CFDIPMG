<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Clave de producto o servicio del SAT con su unidad y concepto, para llenar solos los conceptos
 * al generar una factura.
 *
 * @property int $id
 * @property string $clave
 * @property string $unidad
 * @property string $concepto
 */
class ClaveSat extends Model
{
    protected $table = 'claves_sat';

    protected $fillable = [
        'clave',
        'unidad',
        'concepto',
    ];
}
