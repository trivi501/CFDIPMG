<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * Regla c_UsoCFDI del SAT. Debe coincidir con usesForRegime() en resources/js/lib/sat-catalogs.ts.
 */
class UsoCfdi
{
    private const REGIMENES_GASTOS = ['601', '603', '606', '612', '620', '621', '622', '623', '624', '625', '626'];

    private const REGIMENES_DEDUCCIONES = ['605', '606', '607', '608', '611', '612', '614', '615', '625'];

    private const REGIMENES_SIN_EFECTOS = [
        '601', '603', '605', '606', '607', '608', '610', '611', '612', '614',
        '615', '616', '620', '621', '622', '623', '624', '625', '626',
    ];

    public static function permitido(string $uso, string $regimen): bool
    {
        $regimenes = match (true) {
            $uso === 'S01' => self::REGIMENES_SIN_EFECTOS,
            preg_match('/^(G0[1-3]|I0[1-8])$/', $uso) === 1 => self::REGIMENES_GASTOS,
            preg_match('/^D(0[1-9]|10)$/', $uso) === 1 => self::REGIMENES_DEDUCCIONES,
            default => [],
        };

        return in_array($regimen, $regimenes, true);
    }

    public static function validar(string $uso, string $regimen): void
    {
        if (! self::permitido($uso, $regimen)) {
            throw ValidationException::withMessages([
                'use' => "El uso de CFDI {$uso} no está permitido para el régimen fiscal {$regimen}.",
            ]);
        }
    }
}
