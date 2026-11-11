/**
 * Fecha de calendario ("2026-11-10", "2026-11-10T00:00:00.000000Z", "2026-11-10 09:22:57") como
 * DD/MM/AAAA. Se toma el día tal cual: convertir de zona horaria movería al día anterior.
 */
export function fechaMx(valor: string | null | undefined): string {
    const partes = valor?.slice(0, 10).split('-');

    if (!partes || partes.length !== 3) {
        return valor ?? '—';
    }

    const [anio, mes, dia] = partes;

    return `${dia}/${mes}/${anio}`;
}

/** Momento guardado en UTC (p. ej. la emisión de una factura) como DD/MM/AAAA en Ciudad de México. */
export function fechaMxDeMomento(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleDateString('es-MX', {
        timeZone: 'America/Mexico_City',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}
