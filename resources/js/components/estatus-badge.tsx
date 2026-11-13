import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

/**
 * Un color por significado, igual en todo el sistema:
 * ámbar = falta algo, azul = en proceso, verde = terminado bien, rojo = falló o se rechazó,
 * gris = cancelado.
 */
const TONOS = {
    ambar: 'border-amber-200 bg-amber-100 text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300',
    azul: 'border-blue-200 bg-blue-100 text-blue-800 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-300',
    verde: 'border-emerald-200 bg-emerald-100 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300',
    rojo: 'border-red-200 bg-red-100 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-300',
    gris: 'border-zinc-200 bg-zinc-100 text-zinc-700 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400',
} as const;

type Tono = keyof typeof TONOS;

const ESTATUS = {
    apoyo: {
        preparado: ['Preparado', 'ambar'],
        aprobado: ['Aprobado', 'azul'],
        facturado: ['Facturado', 'verde'],
        rechazado: ['Rechazado', 'rojo'],
    },
    factura: {
        valid: ['Vigente', 'verde'],
        canceled: ['Cancelada', 'gris'],
        failed: ['Fallida', 'rojo'],
    },
    recibo: {
        pending: ['Pendiente', 'ambar'],
        invoiced: ['Facturado', 'verde'],
        canceled: ['Cancelado', 'gris'],
        failed: ['Fallido', 'rojo'],
    },
} as const satisfies Record<string, Record<string, readonly [string, Tono]>>;

type TipoEstatus = keyof typeof ESTATUS;
export type EstatusDe<T extends TipoEstatus> = keyof (typeof ESTATUS)[T];

export function etiquetaEstatus<T extends TipoEstatus>(
    tipo: T,
    estatus: EstatusDe<T>,
): string {
    const entrada = (ESTATUS[tipo] as Record<string, readonly [string, Tono]>)[
        estatus as string
    ];

    return entrada?.[0] ?? String(estatus);
}

export function EstatusBadge<T extends TipoEstatus>({
    tipo,
    estatus,
    children,
    className,
}: {
    tipo: T;
    estatus: EstatusDe<T> | string;
    /** Texto a mostrar en vez de la etiqueta del estatus. */
    children?: ReactNode;
    className?: string;
}) {
    const entrada = (ESTATUS[tipo] as Record<string, readonly [string, Tono]>)[
        estatus as string
    ];
    const [etiqueta, tono] = entrada ?? [String(estatus), 'gris'];

    return (
        <Badge variant="outline" className={cn(TONOS[tono], className)}>
            {children ?? etiqueta}
        </Badge>
    );
}
