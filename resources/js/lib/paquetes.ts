export type ArticuloStock = {
    id: number;
    descripcion: string;
    cantidad_total: string;
    costo_unidad: string;
    iva: string;
    cantidad_disponible: string;
};

export type FacturaStock = { id: number; articulos: ArticuloStock[] };

export type PaqueteArticulo = {
    id: number;
    articulo_factura_compra_id: number;
    descripcion: string;
    cantidad: string;
};

export type Paquete = {
    id: number;
    factura_compra_id: number;
    nombre: string;
    articulos: PaqueteArticulo[];
};

export type LineaPaquete = {
    articulo_factura_compra_id: number;
    articulo: string;
    cantidad: string;
    costo_unidad: string;
    iva: string;
};

const redondear = (valor: number) => Math.round(valor * 100) / 100;

// Descuenta solo de la factura del paquete; si falta algún artículo no aparta nada.
export function asignarPaquete<F extends FacturaStock>(
    paquete: Paquete,
    veces: number,
    facturas: F[],
): { lineas: LineaPaquete[]; facturas: F[]; faltantes: string[] } {
    const articulos =
        facturas.find((f) => f.id === paquete.factura_compra_id)?.articulos ??
        [];
    const lineas: LineaPaquete[] = [];
    const faltantes: string[] = [];
    const usados = new Map<number, number>();

    for (const componente of paquete.articulos) {
        const cantidad = redondear(Number(componente.cantidad) * veces);
        const articulo = articulos.find(
            (a) => a.id === componente.articulo_factura_compra_id,
        );
        const disponible = Number(articulo?.cantidad_disponible ?? 0);

        if (!articulo || cantidad > disponible) {
            faltantes.push(
                `${componente.descripcion}: faltan ${redondear(cantidad - disponible)}`,
            );
            continue;
        }

        const ivaPorUnidad =
            Number(articulo.iva) / (Number(articulo.cantidad_total) || 1);

        lineas.push({
            articulo_factura_compra_id: articulo.id,
            articulo: articulo.descripcion,
            cantidad: String(cantidad),
            costo_unidad: articulo.costo_unidad,
            iva: String(redondear(ivaPorUnidad * cantidad)),
        });
        usados.set(articulo.id, cantidad);
    }

    if (faltantes.length > 0) {
        return { lineas: [], facturas, faltantes };
    }

    return {
        lineas,
        faltantes,
        facturas: facturas.map((factura) => ({
            ...factura,
            articulos: factura.articulos.map((articulo) => ({
                ...articulo,
                cantidad_disponible: String(
                    redondear(
                        Number(articulo.cantidad_disponible) -
                            (usados.get(articulo.id) ?? 0),
                    ),
                ),
            })),
        })),
    };
}
