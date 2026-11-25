<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Beneficiario extends Model
{
    protected $fillable = [
        'nombre',
        'rfc',
        'curp',
    ];

    /** RFC y CURP se guardan en mayúsculas y sin espacios; vacío queda como null. */
    public static function normalizarClave(mixed $valor): ?string
    {
        if (! is_string($valor)) {
            return null;
        }

        $valor = mb_strtoupper(preg_replace('/\s+/', '', $valor) ?? '');

        return $valor === '' ? null : $valor;
    }

    /**
     * Guarda el RFC y la CURP con que se capturó un apoyo para que la próxima vez se llenen solos.
     * Solo cambia los que vienen con valor: un apoyo sin RFC no borra el que ya se tenía.
     */
    public function recordarDatos(?string $rfc, ?string $curp): void
    {
        $cambios = array_filter([
            'rfc' => self::normalizarClave($rfc),
            'curp' => self::normalizarClave($curp),
        ]);

        if ($cambios !== []) {
            $this->fill($cambios);

            if ($this->isDirty()) {
                $this->save();
            }
        }
    }

    /**
     * @return HasMany<Apoyo, $this>
     */
    public function apoyos(): HasMany
    {
        return $this->hasMany(Apoyo::class);
    }
}
