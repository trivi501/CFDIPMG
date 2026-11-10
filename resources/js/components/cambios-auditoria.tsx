import { Badge } from '@/components/ui/badge';

type Valores = Record<string, unknown> | null;

const VARIANTES: Record<
    string,
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    alta: 'default',
    modificación: 'secondary',
    eliminación: 'destructive',
    'cambio masivo': 'secondary',
    descarga: 'outline',
    consulta: 'outline',
    'inicio de sesión': 'outline',
    'cierre de sesión': 'outline',
    'acceso fallido': 'destructive',
};

export function AccionBadge({ accion }: { accion: string }) {
    return (
        <Badge variant={VARIANTES[accion] ?? 'outline'} className="capitalize">
            {accion}
        </Badge>
    );
}

const texto = (valor: unknown) => {
    if (valor === null || valor === undefined || valor === '') {
        return '—';
    }

    if (typeof valor === 'boolean') {
        return valor ? 'Sí' : 'No';
    }

    if (typeof valor === 'object') {
        return JSON.stringify(valor);
    }

    return String(valor);
};

/** Campos que cambiaron: valor anterior → valor nuevo. Un cambio masivo muestra la instrucción ejecutada. */
export default function CambiosAuditoria({
    antes,
    despues,
}: {
    antes: Valores;
    despues: Valores;
}) {
    const instruccion = despues?.['instrucción'];

    if (typeof instruccion === 'string') {
        return (
            <code className="block max-w-xl rounded bg-muted px-2 py-1 font-mono text-xs break-all whitespace-pre-wrap">
                {instruccion}
            </code>
        );
    }

    const campos = Array.from(
        new Set([...Object.keys(antes ?? {}), ...Object.keys(despues ?? {})]),
    );

    if (campos.length === 0) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <dl className="grid grid-cols-[max-content_1fr] gap-x-3 gap-y-0.5 text-xs">
            {campos.map((campo) => (
                <div key={campo} className="contents">
                    <dt className="text-muted-foreground">{campo}</dt>
                    <dd className="break-words whitespace-normal">
                        {antes && (
                            <>
                                <span className="text-muted-foreground line-through">
                                    {texto(antes[campo])}
                                </span>
                                {despues && ' → '}
                            </>
                        )}
                        {despues && (
                            <span className="font-medium">
                                {texto(despues[campo])}
                            </span>
                        )}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
