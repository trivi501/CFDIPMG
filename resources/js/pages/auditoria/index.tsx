import { Head, Link, router } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useState } from 'react';
import CambiosAuditoria, { AccionBadge } from '@/components/cambios-auditoria';
import Heading from '@/components/heading';
import { Pagination } from '@/components/pagination';
import type { Paginator } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes';
import auditoria from '@/routes/auditoria';

type Movimiento = {
    id: number;
    fecha: string;
    usuario: string | null;
    accion: string;
    tabla: string | null;
    modulo: string;
    registro: string | null;
    enlace: string | null;
    descripcion: string | null;
    origen: string | null;
    lote: string | null;
    antes: Record<string, unknown> | null;
    despues: Record<string, unknown> | null;
    ip: string | null;
};

type Filtros = {
    tabla?: string;
    usuario?: string;
    accion?: string;
    desde?: string;
    hasta?: string;
    lote?: string;
    registro?: string;
    buscar?: string;
};

type PageProps = {
    registros: Paginator<Movimiento>;
    filtros: Filtros;
    tablas: { valor: string; etiqueta: string }[];
    usuarios: { id: number; name: string }[];
    acciones: string[];
};

const fechaHora = (iso: string) =>
    new Date(iso).toLocaleString('es-MX', {
        timeZone: 'America/Mexico_City',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });

export default function AuditoriaIndex({
    registros,
    filtros,
    tablas,
    usuarios,
    acciones,
}: PageProps) {
    const [valores, setValores] = useState<Filtros>(filtros ?? {});

    const aplicar = (cambios: Filtros) => {
        const siguientes = { ...valores, ...cambios };
        setValores(siguientes);
        router.get(
            auditoria.index().url,
            Object.fromEntries(
                Object.entries(siguientes).filter(([, valor]) => valor),
            ),
            { preserveState: true, replace: true },
        );
    };

    const hayFiltros = Object.values(filtros ?? {}).some(Boolean);

    return (
        <>
            <Head title="Bitácora de auditoría" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    title="Bitácora de auditoría"
                    description="Quién dio de alta, modificó o eliminó cada registro (con el valor anterior y el nuevo), los cambios masivos, las descargas de PDF y XML y los accesos al sistema. Los movimientos de una misma operación se ven juntos con el enlace de la operación."
                />

                <div className="flex flex-wrap items-center gap-2">
                    <NativeSelect
                        aria-label="Módulo"
                        value={valores.tabla ?? ''}
                        onChange={(e) =>
                            aplicar({ tabla: e.target.value, registro: '' })
                        }
                        className="w-auto"
                    >
                        <option value="">Todos los módulos</option>
                        {tablas.map((tabla) => (
                            <option key={tabla.valor} value={tabla.valor}>
                                {tabla.etiqueta}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect
                        aria-label="Usuario"
                        value={valores.usuario ?? ''}
                        onChange={(e) => aplicar({ usuario: e.target.value })}
                        className="w-auto"
                    >
                        <option value="">Todos los usuarios</option>
                        <option value="sistema">Sistema (sin usuario)</option>
                        {usuarios.map((usuario) => (
                            <option key={usuario.id} value={usuario.id}>
                                {usuario.name}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect
                        aria-label="Acción"
                        value={valores.accion ?? ''}
                        onChange={(e) => aplicar({ accion: e.target.value })}
                        className="w-auto capitalize"
                    >
                        <option value="">Todas las acciones</option>
                        {acciones.map((accion) => (
                            <option key={accion} value={accion}>
                                {accion}
                            </option>
                        ))}
                    </NativeSelect>
                    <label className="flex items-center gap-1 text-sm">
                        Desde
                        <Input
                            type="date"
                            value={valores.desde ?? ''}
                            onChange={(e) => aplicar({ desde: e.target.value })}
                            className="w-auto"
                        />
                    </label>
                    <label className="flex items-center gap-1 text-sm">
                        Hasta
                        <Input
                            type="date"
                            value={valores.hasta ?? ''}
                            onChange={(e) => aplicar({ hasta: e.target.value })}
                            className="w-auto"
                        />
                    </label>
                    <Input
                        type="search"
                        placeholder="Buscar folio, valor u operación… (Enter)"
                        defaultValue={valores.buscar ?? ''}
                        onKeyDown={(e) =>
                            e.key === 'Enter' &&
                            aplicar({ buscar: e.currentTarget.value })
                        }
                        className="w-72"
                    />
                    {hayFiltros && (
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => {
                                setValores({});
                                router.get(
                                    auditoria.index().url,
                                    {},
                                    { replace: true },
                                );
                            }}
                        >
                            <X /> Quitar filtros
                        </Button>
                    )}
                    <span className="ml-auto text-sm text-muted-foreground">
                        {registros.total.toLocaleString('es-MX')} movimientos
                    </span>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Fecha</TableHead>
                                <TableHead>Usuario</TableHead>
                                <TableHead>Acción</TableHead>
                                <TableHead>Módulo</TableHead>
                                <TableHead>Cambios</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {registros.data.length === 0 && (
                                <TableEmpty colSpan={5}>
                                    No hay movimientos.
                                </TableEmpty>
                            )}
                            {registros.data.map((m) => (
                                <TableRow key={m.id} className="align-top">
                                    <TableCell className="whitespace-nowrap">
                                        {fechaHora(m.fecha)}
                                    </TableCell>
                                    <TableCell className="whitespace-nowrap">
                                        {m.usuario ?? 'Sistema'}
                                        {m.ip && (
                                            <div className="text-xs text-muted-foreground">
                                                {m.ip}
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <AccionBadge accion={m.accion} />
                                    </TableCell>
                                    <TableCell className="min-w-48 whitespace-normal">
                                        {m.enlace ? (
                                            <Link
                                                href={m.enlace}
                                                className="underline-offset-2 hover:underline"
                                            >
                                                {m.modulo}
                                            </Link>
                                        ) : (
                                            m.modulo
                                        )}
                                        {m.registro && m.tabla && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    aplicar({
                                                        tabla: m.tabla ?? '',
                                                        registro:
                                                            m.registro ?? '',
                                                    })
                                                }
                                                className="block text-xs text-muted-foreground hover:underline"
                                                title="Ver todos los movimientos de este registro"
                                            >
                                                Registro {m.registro}
                                            </button>
                                        )}
                                        {m.descripcion && (
                                            <span className="block text-xs text-muted-foreground">
                                                {m.descripcion}
                                            </span>
                                        )}
                                        {m.lote && m.origen && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    aplicar({
                                                        lote: m.lote ?? '',
                                                    })
                                                }
                                                className="block max-w-xs truncate text-left text-xs text-muted-foreground hover:underline"
                                                title={`Ver todos los movimientos de esta operación: ${m.origen}`}
                                            >
                                                {m.origen}
                                            </button>
                                        )}
                                    </TableCell>
                                    <TableCell className="min-w-64 whitespace-normal">
                                        <CambiosAuditoria
                                            antes={m.antes}
                                            despues={m.despues}
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <Pagination paginator={registros} />
            </div>
        </>
    );
}

AuditoriaIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Bitácora de auditoría', href: auditoria.index() },
    ],
};
