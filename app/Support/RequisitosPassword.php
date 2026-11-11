<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * Las reglas de contraseña que se aplican de verdad (Password::defaults(), ver AppServiceProvider),
 * para que la lista de requisitos que ve el usuario coincida con lo que valida el servidor.
 */
class RequisitosPassword
{
    /**
     * @return array{min: int, mixedCase: bool, letters: bool, numbers: bool, symbols: bool, uncompromised: bool}
     */
    public static function actuales(): array
    {
        $reglas = Password::defaults()->appliedRules();

        return [
            'min' => (int) $reglas['min'],
            'mixedCase' => (bool) $reglas['mixedCase'],
            'letters' => (bool) $reglas['letters'],
            'numbers' => (bool) $reglas['numbers'],
            'symbols' => (bool) $reglas['symbols'],
            'uncompromised' => (bool) $reglas['uncompromised'],
        ];
    }
}
