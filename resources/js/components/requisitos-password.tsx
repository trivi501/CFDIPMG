import { Check, Circle, Info } from 'lucide-react';
import { cn } from '@/lib/utils';

/** Reglas de contraseña que aplica el servidor (App\Support\RequisitosPassword). */
export type RequisitosPassword = {
    min: number;
    mixedCase: boolean;
    letters: boolean;
    numbers: boolean;
    symbols: boolean;
    uncompromised: boolean;
};

type Requisito = { texto: string; cumple: boolean };

/** Las mismas expresiones que usa Laravel en Illuminate\Validation\Rules\Password. */
function evaluar(
    requisitos: RequisitosPassword,
    password: string,
    confirmacion: string,
): Requisito[] {
    const lista: Requisito[] = [
        {
            texto: `Al menos ${requisitos.min} caracteres`,
            cumple: [...password].length >= requisitos.min,
        },
    ];

    if (requisitos.mixedCase) {
        lista.push({
            texto: 'Una letra mayúscula y una minúscula',
            cumple: /(\p{Ll}+.*\p{Lu})|(\p{Lu}+.*\p{Ll})/u.test(password),
        });
    } else if (requisitos.letters) {
        lista.push({
            texto: 'Al menos una letra',
            cumple: /\p{L}/u.test(password),
        });
    }

    if (requisitos.numbers) {
        lista.push({
            texto: 'Al menos un número',
            cumple: /\p{N}/u.test(password),
        });
    }

    if (requisitos.symbols) {
        lista.push({
            texto: 'Al menos un símbolo (por ejemplo . , ! @ # -)',
            cumple: /\p{Z}|\p{S}|\p{P}/u.test(password),
        });
    }

    lista.push({
        texto: 'Las dos contraseñas coinciden',
        cumple: password.length > 0 && password === confirmacion,
    });

    return lista;
}

export function passwordCumpleRequisitos(
    requisitos: RequisitosPassword,
    password: string,
    confirmacion: string,
): boolean {
    return evaluar(requisitos, password, confirmacion).every((r) => r.cumple);
}

/**
 * Lista de requisitos que se van marcando en verde mientras se escribe. Lo que solo puede revisar
 * el servidor (filtraciones de datos, que no sea la contraseña actual) se muestra como nota.
 */
export function RequisitosPasswordLista({
    requisitos,
    password,
    confirmacion,
    notas = [],
}: {
    requisitos: RequisitosPassword;
    password: string;
    confirmacion: string;
    notas?: string[];
}) {
    const lista = evaluar(requisitos, password, confirmacion);
    const notasServidor = [
        ...notas,
        ...(requisitos.uncompromised
            ? ['Que no aparezca en filtraciones de datos conocidas']
            : []),
    ];

    return (
        <ul
            className="grid gap-1.5 rounded-lg border bg-muted/30 p-3 text-sm"
            aria-label="Requisitos de la contraseña"
        >
            {lista.map((requisito) => (
                <li
                    key={requisito.texto}
                    className={cn(
                        'flex items-center gap-2 transition-colors',
                        requisito.cumple
                            ? 'text-green-600 dark:text-green-400'
                            : 'text-muted-foreground',
                    )}
                >
                    {requisito.cumple ? (
                        <Check className="size-4 shrink-0" aria-hidden />
                    ) : (
                        <Circle className="size-4 shrink-0" aria-hidden />
                    )}
                    <span>
                        {requisito.texto}
                        <span className="sr-only">
                            {requisito.cumple ? ' (cumple)' : ' (pendiente)'}
                        </span>
                    </span>
                </li>
            ))}
            {notasServidor.map((nota) => (
                <li
                    key={nota}
                    className="flex items-center gap-2 text-muted-foreground"
                >
                    <Info className="size-4 shrink-0" aria-hidden />
                    <span>{nota} (se revisa al guardar)</span>
                </li>
            ))}
        </ul>
    );
}
