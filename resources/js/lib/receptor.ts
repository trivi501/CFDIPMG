/**
 * Nombre con el que se emitió una factura. Las de público en general (RFC genérico) comparten un
 * mismo cliente, así que cada una guarda su propio nombre en receptor_nombre; las anteriores a ese
 * campo lo tienen vacío y se muestran con el nombre del cliente.
 */
export function nombreReceptor(invoice: {
    receptor_nombre?: string | null;
    customer?: { legal_name: string } | null;
}): string | undefined {
    return invoice.receptor_nombre ?? invoice.customer?.legal_name;
}
