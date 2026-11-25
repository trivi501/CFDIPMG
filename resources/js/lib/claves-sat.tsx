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

/** Los conceptos del catálogo para una clave: una misma clave puede tener varios. */
export function conceptosDeClave(
    claves: ClaveSat[],
    clave: string,
): ClaveSat[] {
    const buscada = clave.trim();

    return buscada === '' ? [] : claves.filter((c) => c.clave === buscada);
}

/**
 * Cambios a una línea de factura al escribir una clave. Si la clave está en el catálogo se llenan
 * su unidad y su concepto; el concepto solo si estaba vacío o seguía siendo el de otra clave del
 * catálogo, para no pisar lo que el usuario escribió a mano. Si la clave tiene varios conceptos y
 * la descripción ya es uno de ellos, se queda ese; si no, se toma el primero (otro se elige
 * escribiendo en la descripción).
 */
export function cambiosPorClave(
    claves: ClaveSat[],
    linea: Linea,
    clave: string,
): Partial<Linea> {
    const cambios: Partial<Linea> = { sat_product_key: clave };
    const opciones = conceptosDeClave(claves, clave);

    if (opciones.length === 0) {
        return cambios;
    }

    const elegida =
        opciones.find((c) => c.concepto === linea.description) ?? opciones[0];
    const eraDelCatalogo = conceptosDeClave(claves, linea.sat_product_key).some(
        (c) => c.concepto === linea.description,
    );
    cambios.sat_unit_key = elegida.unidad;

    if (linea.description.trim() === '' || eraDelCatalogo) {
        cambios.description = elegida.concepto;
    }

    return cambios;
}

/** Aviso bajo el campo de clave cuando la clave está en el catálogo. */
export function AvisoClaveDelCatalogo({
    claves,
    clave,
}: {
    claves: ClaveSat[];
    clave: string;
}) {
    if (conceptosDeClave(claves, clave).length === 0) {
        return null;
    }

    return (
        <p className="text-xs text-green-600 dark:text-green-400">
            Unidad y concepto tomados del catálogo de claves SAT.
        </p>
    );
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
