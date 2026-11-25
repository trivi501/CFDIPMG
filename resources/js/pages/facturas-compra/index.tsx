import { Head, Link, router, useForm } from '@inertiajs/react';
import { ExternalLink, Search, Upload, X } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { usePermissions } from '@/hooks/use-permissions';
import { fechaMx } from '@/lib/fechas';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import apoyos from '@/routes/apoyos';
import facturasCompra from '@/routes/facturas-compra';

type Articulo = {
    id: number;
    descripcion: string;
    cantidad_total: string;
    costo_unidad: string;
    iva: string;
    cantidad_disponible: string;
};

type Factura = {
    id: number;
    uuid: string | null;
    emisor: string | null;
    fecha: string | null;
    total: string;
    xml_path: string;
    area_id: number | null;
    area: { id: number; nombre: string } | null;
    user_id: number | null;
    user: { id: number; name: string } | null;
    articulos: Articulo[];
    monto_total: number;
    monto_asignado: number;
    monto_por_asignar: number;
    apoyos_count: number;
};

type Estado = 'sin-usar' | 'parcial' | 'liquidada';

/** Liquidada: ya no queda nada por repartir. Sin usar: todavía no sale nada en apoyos. */
function estadoDe(factura: Factura): Estado {
    if (factura.monto_por_asignar <= 0.005) {
        return 'liquidada';
    }

    return factura.monto_asignado <= 0.005 ? 'sin-usar' : 'parcial';
}

const ESTADOS: Record<Estado, { label: string; className: string }> = {
    'sin-usar': {
        label: 'Sin usar',
        className: 'border-transparent bg-muted text-muted-foreground',
    },
    parcial: {
        label: 'Parcial',
        className:
            'border-transparent bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
    },
    liquidada: {
        label: 'Liquidada',
        className:
            'border-transparent bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
    },
};

function normalizar(texto: string): string {
    return texto
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();
}

const currency = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});

export default function FacturasCompraIndex({
    facturas,
    areas,
    usuarios,
    puedeAsignarArea,
}: {
    facturas: Factura[];
    areas: { id: number; nombre: string; privada: boolean }[];
    usuarios: { id: number; name: string; area_id: number }[];
    puedeAsignarArea: boolean;
}) {
    const { can } = usePermissions();
    const sinArea = facturas.filter((factura) => factura.area_id === null);

    const asignarArea = (factura: Factura, areaId: string) =>
        router.patch(
            facturasCompra.area(factura.id).url,
            { area_id: areaId === '' ? null : Number(areaId) },
            { preserveScroll: true },
        );

    const asignarUsuario = (factura: Factura, userId: string) =>
        router.patch(
            facturasCompra.usuario(factura.id).url,
            { user_id: Number(userId) },
            { preserveScroll: true },
        );

    const areasPrivadas = new Set(
        areas.filter((area) => area.privada).map((area) => area.id),
    );

    // En un área privada cada quien ve solo sus facturas: se elige de quién es entre sus integrantes.
    // También en las que aún no tienen área ni dueño, para asignarlas en un solo paso.
    const eligeDueno = (factura: Factura) =>
        factura.area_id === null
            ? factura.user_id === null
            : areasPrivadas.has(factura.area_id);

    const integrantesPara = (factura: Factura) => {
        // Sin área todavía: cualquiera; al elegirlo, la factura toma su área.
        const lista = usuarios.filter(
            (usuario) =>
                factura.area_id === null || usuario.area_id === factura.area_id,
        );

        if (factura.user && !lista.some((u) => u.id === factura.user_id)) {
            lista.unshift({ ...factura.user, area_id: -1 });
        }

        return lista;
    };

    const { data, setData, post, processing, errors, reset } = useForm({
        xml: null as File | null,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(facturasCompra.store().url, {
            forceFormData: true,
            onSuccess: () => reset(),
        });
    };

    const [buscar, setBuscar] = useState('');
    const [filtroArea, setFiltroArea] = useState('');
    const [filtroEstado, setFiltroEstado] = useState('');
    const hayFiltros = Boolean(buscar.trim() || filtroArea || filtroEstado);

    // Se filtra aquí mismo: la pantalla ya trae todas las facturas que el usuario puede ver.
    const termino = normalizar(buscar.trim());
    const facturasAMostrar = facturas.filter(
        (factura) =>
            (!termino ||
                normalizar(
                    [
                        factura.emisor,
                        factura.uuid,
                        factura.fecha ? fechaMx(factura.fecha) : '',
                        factura.user?.name,
                        ...factura.articulos.map((a) => a.descripcion),
                    ]
                        .filter(Boolean)
                        .join(' '),
                ).includes(termino)) &&
            (!filtroArea ||
                (filtroArea === 'sin-area'
                    ? factura.area_id === null
                    : String(factura.area_id) === filtroArea)) &&
            (!filtroEstado || estadoDe(factura) === filtroEstado),
    );

    return (
        <>
            <Head title="Facturas de compra" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    title="Facturas de compra"
                    description="Registra el XML de una factura de compra para repartir sus artículos entre varios apoyos, descontando el inventario disponible conforme se usan."
                />

                {can('apoyos.manage') && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Registrar factura</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={submit}
                                encType="multipart/form-data"
                                className="flex flex-wrap items-end gap-4"
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="xml">Factura XML</Label>
                                    <Input
                                        id="xml"
                                        type="file"
                                        accept=".xml,text/xml,application/xml"
                                        onChange={(e) =>
                                            setData(
                                                'xml',
                                                e.target.files?.[0] ?? null,
                                            )
                                        }
                                    />
                                    <InputError message={errors.xml} />
                                </div>
                                <Button disabled={processing || !data.xml}>
                                    <Upload /> Registrar
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {puedeAsignarArea && sinArea.length > 0 && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                        <strong>{sinArea.length}</strong> factura(s) sin área.
                        Solo las ven Admin y Facturación hasta que les asignes
                        el área (regiduría o secretaría) a la que pertenecen.
                    </div>
                )}

                {facturas.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        No hay facturas de compra registradas.
                    </p>
                )}

                {facturas.length > 0 && (
                    <div className="flex flex-wrap items-end gap-2">
                        <div className="grid w-full gap-1 sm:w-80">
                            <Label htmlFor="buscar_factura" className="text-xs">
                                Buscar
                            </Label>
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    id="buscar_factura"
                                    type="search"
                                    value={buscar}
                                    onChange={(e) => setBuscar(e.target.value)}
                                    placeholder="Proveedor, UUID, artículo o fecha…"
                                    className="pl-8"
                                />
                            </div>
                        </div>
                        {puedeAsignarArea && areas.length > 0 && (
                            <div className="grid w-full gap-1 sm:w-56">
                                <Label
                                    htmlFor="filtro_area_factura"
                                    className="text-xs"
                                >
                                    Área
                                </Label>
                                <NativeSelect
                                    id="filtro_area_factura"
                                    value={filtroArea}
                                    onChange={(e) =>
                                        setFiltroArea(e.target.value)
                                    }
                                >
                                    <option value="">Todas las áreas</option>
                                    <option value="sin-area">Sin área</option>
                                    {areas.map((area) => (
                                        <option key={area.id} value={area.id}>
                                            {area.nombre}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </div>
                        )}
                        <div className="grid w-full gap-1 sm:w-44">
                            <Label
                                htmlFor="filtro_estado_factura"
                                className="text-xs"
                            >
                                Estado
                            </Label>
                            <NativeSelect
                                id="filtro_estado_factura"
                                value={filtroEstado}
                                onChange={(e) =>
                                    setFiltroEstado(e.target.value)
                                }
                            >
                                <option value="">Todas</option>
                                <option value="sin-usar">Sin usar</option>
                                <option value="parcial">
                                    Parcial (falta por asignar)
                                </option>
                                <option value="liquidada">Liquidada</option>
                            </NativeSelect>
                        </div>
                        {hayFiltros && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setBuscar('');
                                    setFiltroArea('');
                                    setFiltroEstado('');
                                }}
                            >
                                <X /> Limpiar filtros
                            </Button>
                        )}
                        <span className="pb-2 text-sm text-muted-foreground">
                            {facturasAMostrar.length} de {facturas.length}{' '}
                            factura(s)
                        </span>
                    </div>
                )}

                {facturas.length > 0 && facturasAMostrar.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        Ninguna factura coincide con los filtros.
                    </p>
                )}

                {facturasAMostrar.map((factura) => (
                    <Card key={factura.id}>
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle>
                                {factura.emisor ?? 'Factura de compra'}
                                {factura.fecha && (
                                    <span className="ml-2 text-sm font-normal text-muted-foreground">
                                        {fechaMx(factura.fecha)}
                                    </span>
                                )}
                                {!puedeAsignarArea && factura.area && (
                                    <Badge variant="outline" className="ml-2">
                                        {factura.area.nombre}
                                    </Badge>
                                )}
                                <Badge
                                    className={cn(
                                        'ml-2',
                                        ESTADOS[estadoDe(factura)].className,
                                    )}
                                >
                                    {ESTADOS[estadoDe(factura)].label}
                                </Badge>
                                {factura.uuid && (
                                    <div className="mt-1 font-mono text-xs font-normal break-all text-muted-foreground">
                                        UUID {factura.uuid}
                                    </div>
                                )}
                            </CardTitle>
                            <div className="flex flex-wrap items-center gap-3">
                                {puedeAsignarArea && (
                                    <div className="w-64">
                                        <NativeSelect
                                            aria-label={`Área de la factura de ${factura.emisor ?? 'compra'}`}
                                            value={String(
                                                factura.area_id ?? '',
                                            )}
                                            onChange={(e) =>
                                                asignarArea(
                                                    factura,
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            <option value="">
                                                Sin área (solo Admin y
                                                Facturación)
                                            </option>
                                            {areas.map((area) => (
                                                <option
                                                    key={area.id}
                                                    value={area.id}
                                                >
                                                    {area.nombre}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </div>
                                )}
                                {puedeAsignarArea && eligeDueno(factura) && (
                                    <div className="w-64">
                                        <NativeSelect
                                            aria-label={`De quién es la factura de ${factura.emisor ?? 'compra'}`}
                                            value={String(
                                                factura.user_id ?? '',
                                            )}
                                            onChange={(e) =>
                                                asignarUsuario(
                                                    factura,
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            {factura.user_id === null && (
                                                <option value="" disabled>
                                                    Elige de quién es
                                                </option>
                                            )}
                                            {integrantesPara(factura).map(
                                                (usuario) => (
                                                    <option
                                                        key={usuario.id}
                                                        value={usuario.id}
                                                    >
                                                        {usuario.name}
                                                    </option>
                                                ),
                                            )}
                                        </NativeSelect>
                                    </div>
                                )}
                                {puedeAsignarArea &&
                                    factura.user &&
                                    !eligeDueno(factura) && (
                                        <span className="text-sm text-muted-foreground">
                                            Subió: {factura.user.name}
                                        </span>
                                    )}
                                <a
                                    href={facturasCompra.xml(factura.id).url}
                                    className="text-sm text-primary underline"
                                >
                                    Descargar XML
                                </a>
                            </div>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <ResumenDeUso factura={factura} />
                            <div className="overflow-x-auto rounded-lg border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Artículo</TableHead>
                                            <TableHead className="text-right">
                                                Comprado
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Disponible
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Costo unidad
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {factura.articulos.length === 0 && (
                                            <TableEmpty colSpan={4}>
                                                Sin artículos.
                                            </TableEmpty>
                                        )}
                                        {factura.articulos.map((articulo) => (
                                            <TableRow key={articulo.id}>
                                                <TableCell className="font-medium">
                                                    {articulo.descripcion}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {articulo.cantidad_total}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <span
                                                        className={
                                                            Number(
                                                                articulo.cantidad_disponible,
                                                            ) === 0
                                                                ? 'text-muted-foreground'
                                                                : 'font-medium text-primary'
                                                        }
                                                    >
                                                        {
                                                            articulo.cantidad_disponible
                                                        }
                                                    </span>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {currency.format(
                                                        Number(
                                                            articulo.costo_unidad,
                                                        ),
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>
        </>
    );
}

FacturasCompraIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Facturas de compra', href: facturasCompra.index() },
    ],
};

/** Cuánto de la factura ya se asignó a apoyos, cuánto falta para liquidarla y enlace a esos apoyos. */
function ResumenDeUso({ factura }: { factura: Factura }) {
    const porcentaje =
        factura.monto_total > 0
            ? Math.min(
                  100,
                  Math.round(
                      (factura.monto_asignado / factura.monto_total) * 100,
                  ),
              )
            : 0;
    const estado = estadoDe(factura);

    return (
        <div className="grid gap-2 rounded-lg border p-3">
            <div className="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 text-sm">
                <div className="flex flex-wrap gap-x-6 gap-y-1">
                    <span>
                        <span className="text-muted-foreground">Total </span>
                        <span className="font-medium tabular-nums">
                            {currency.format(factura.monto_total)}
                        </span>
                    </span>
                    <span>
                        <span className="text-muted-foreground">
                            Asignado a apoyos{' '}
                        </span>
                        <span className="font-medium tabular-nums">
                            {currency.format(factura.monto_asignado)}
                        </span>
                    </span>
                    <span>
                        <span className="text-muted-foreground">
                            Falta para liquidar{' '}
                        </span>
                        <span
                            className={cn(
                                'font-semibold tabular-nums',
                                estado === 'liquidada'
                                    ? 'text-emerald-700 dark:text-emerald-400'
                                    : 'text-amber-700 dark:text-amber-400',
                            )}
                        >
                            {currency.format(factura.monto_por_asignar)}
                        </span>
                    </span>
                </div>
                {factura.apoyos_count > 0 ? (
                    <Link
                        href={apoyos.index({
                            query: { factura: factura.id },
                        })}
                        className="inline-flex items-center gap-1 text-sm font-medium text-primary underline-offset-4 hover:underline"
                    >
                        <ExternalLink className="size-4" />
                        Ver {factura.apoyos_count} apoyo
                        {factura.apoyos_count === 1 ? '' : 's'} con esta factura
                    </Link>
                ) : (
                    <span className="text-sm text-muted-foreground">
                        Aún no está asignada a ningún apoyo
                    </span>
                )}
            </div>
            <div
                className="h-2 overflow-hidden rounded-full bg-muted"
                role="progressbar"
                aria-valuenow={porcentaje}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-label="Porcentaje asignado a apoyos"
            >
                <div
                    className={cn(
                        'h-full rounded-full',
                        estado === 'liquidada'
                            ? 'bg-emerald-500'
                            : 'bg-amber-500',
                    )}
                    style={{ width: `${porcentaje}%` }}
                />
            </div>
            <span className="text-xs text-muted-foreground">
                {porcentaje}% asignado
            </span>
        </div>
    );
}
