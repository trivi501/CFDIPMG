/** Clave de producto o servicio del catálogo "Claves SAT" (App\Models\ClaveSat). */
export type ClaveSat = {
    id: number;
    clave: string;
    unidad: string;
    concepto: string;
};

/** id del <datalist> con las sugerencias; se pinta una vez por página con ClavesSatSugerencias. */
export const LISTA_CLAVES_SAT = 'claves-sat-sugerencias';

type Linea = {
    description: string;
    sat_product_key: string;
    sat_unit_key: string;
};

/**
 * Cambios a una línea de factura al escribir una clave. Si la clave está en el catálogo se llenan
 * su unidad y su concepto; el concepto solo si estaba vacío o seguía siendo el de otra clave del
 * catálogo, para no pisar lo que el usuario escribió a mano.
 */
export function cambiosPorClave(
    claves: ClaveSat[],
    linea: Linea,
    clave: string,
): Partial<Linea> {
    const cambios: Partial<Linea> = { sat_product_key: clave };
    const nueva = claves.find((c) => c.clave === clave.trim());

    if (!nueva) {
        return cambios;
    }

    const anterior = claves.find((c) => c.clave === linea.sat_product_key);
    cambios.sat_unit_key = nueva.unidad;

    if (
        linea.description.trim() === '' ||
        linea.description === anterior?.concepto
    ) {
        cambios.description = nueva.concepto;
    }

    return cambios;
}

export function claveDelCatalogo(
    claves: ClaveSat[],
    clave: string,
): ClaveSat | undefined {
    return claves.find((c) => c.clave === clave.trim());
}

/** Sugerencias para el campo de clave: se puede buscar por número o por concepto. */
export function ClavesSatSugerencias({ claves }: { claves: ClaveSat[] }) {
    return (
        <datalist id={LISTA_CLAVES_SAT}>
            {claves.map((c) => (
                <option key={c.id} value={c.clave}>
                    {`${c.concepto} (${c.unidad})`}
                </option>
            ))}
        </datalist>
    );
}
