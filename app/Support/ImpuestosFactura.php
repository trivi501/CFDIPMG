<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Lo que se elige en el formulario sobre IVA y retención de ISR, listo para InvoiceService.
 */
class ImpuestosFactura
{
    /**
     * @return array<string, list<string>>
     */
    public static function reglas(): array
    {
        return [
            'iva' => ['sometimes', 'boolean'],
            'isr_rate' => ['nullable', 'numeric', 'gt:0', 'max:35'],
        ];
    }

    /**
     * @return array{iva: bool, isr_rate: float|null}
     */
    public static function desde(Request $request, bool $ivaPorDefecto): array
    {
        $isr = $request->input('isr_rate');

        return [
            'iva' => $request->boolean('iva', $ivaPorDefecto),
            'isr_rate' => is_numeric($isr) && (float) $isr > 0 ? round((float) $isr / 100, 6) : null,
        ];
    }
}
