/** RFC y CURP guardados de un beneficiario (se recuerdan del último apoyo que se le capturó). */
export type DatosBeneficiario = {
    rfc?: string | null;
    curp?: string | null;
};

type Campos = { rfc: string; curp: string };

/**
 * RFC y CURP que deben quedar en el formulario del apoyo al cambiar de beneficiario: los del
 * nuevo beneficiario si los tiene; si no, se borran los que se habían cargado del anterior y se
 * conservan los que se escribieron a mano.
 */
export function camposAlCambiarBeneficiario(
    actuales: Campos,
    anterior: DatosBeneficiario | undefined,
    nuevo: DatosBeneficiario | undefined,
): Campos {
    const campo = (clave: keyof Campos): string => {
        const guardado = nuevo?.[clave];

        if (guardado) {
            return guardado;
        }

        return actuales[clave] === (anterior?.[clave] ?? '')
            ? ''
            : actuales[clave];
    };

    return { rfc: campo('rfc'), curp: campo('curp') };
}
