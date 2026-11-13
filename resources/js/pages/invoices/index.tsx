import { Head, Link, router } from '@inertiajs/react';
import { FileDown, Plus, Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { EstatusBadge, etiquetaEstatus } from '@/components/estatus-badge';
import Heading from '@/components/heading';
import { Pagination } from '@/components/pagination';
import type { Paginator } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
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
import { fechaMxDeMomento } from '@/lib/fechas';
import { dashboard } from '@/routes';
import invoices from '@/routes/invoices';
import globalInvoice from '@/routes/invoices/global';

type Invoice = {
    id: number;
    uuid: string | null;
    series: string | null;
    folio: string | null;
    status: 'valid' | 'canceled' | 'failed';
    total: string;
    issued_at: string | null;
    is_global: boolean;
    customer: { legal_name: string; rfc: string } | null;
};

type PageProps = {
    invoices: Paginator<Invoice>;
    filters: { status?: string; search?: string };
    maxXmls: number;
};

/** Solo las facturas timbradas tienen XML. */
const tieneXml = (invoice: Invoice) =>
    invoice.uuid !== null && invoice.status !== 'failed';

const nombreFactura = (invoice: Invoice) =>
    `${invoice.series ?? ''}${invoice.folio ?? ''} ${invoice.customer?.legal_name ?? ''}`.trim();

const currency = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});

export default function InvoicesIndex({
    invoices: paginator,
    filters,
    maxXmls,
}: PageProps) {
    const { can } = usePermissions();
    const puedeDescargar = can('invoices.download');

    // Facturas marcadas para bajar su XML. Se conservan al buscar, filtrar o cambiar de página,
    // así se pueden juntar facturas de varias búsquedas en un solo ZIP.
    const [seleccion, setSeleccion] = useState<Map<number, string>>(
        () => new Map(),
    );
    const conXml = paginator.data.filter(tieneXml);
    const todasMarcadas =
        conXml.length > 0 && conXml.every((i) => seleccion.has(i.id));
    const algunaMarcada = conXml.some((i) => seleccion.has(i.id));

    const alternar = (invoice: Invoice, marcada: boolean) =>
        setSeleccion((actual) => {
            const nueva = new Map(actual);

            if (marcada) {
                nueva.set(invoice.id, nombreFactura(invoice));
            } else {
                nueva.delete(invoice.id);
            }

            return nueva;
        });

    const alternarPagina = (marcada: boolean) =>
        setSeleccion((actual) => {
            const nueva = new Map(actual);
            conXml.forEach((invoice) =>
                marcada
                    ? nueva.set(invoice.id, nombreFactura(invoice))
                    : nueva.delete(invoice.id),
            );

            return nueva;
        });

    const excedido = seleccion.size > maxXmls;
    const urlZip = invoices.xmls.url({
        query: { ids: [...seleccion.keys()] },
    });

    // Búsqueda en el servidor, con una pausa para no consultar en cada tecla.
    const [busqueda, setBusqueda] = useState(filters.search ?? '');
    const primeraVez = useRef(true);

    useEffect(() => {
        if (primeraVez.current) {
            primeraVez.current = false;

            return;
        }

        const t = setTimeout(
            () =>
                router.get(
                    invoices.index().url,
                    {
                        ...(filters.status ? { status: filters.status } : {}),
                        ...(busqueda.trim() ? { search: busqueda.trim() } : {}),
                    },
                    {
                        preserveState: true,
                        preserveScroll: true,
                        replace: true,
                    },
                ),
            350,
        );

        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [busqueda]);

    return (
        <>
            <Head title="Facturas" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Facturas"
                        description="CFDI generados a partir de los recibos de pago."
                    />
                    {can('invoices.create') && (
                        <div className="flex gap-2">
                            <Button variant="outline" asChild>
                                <Link href={globalInvoice.create()}>
                                    Factura global
                                </Link>
                            </Button>
                            <Button asChild>
                                <Link href={invoices.create()}>
                                    <Plus /> Factura abierta
                                </Link>
                            </Button>
                        </div>
                    )}
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative w-full max-w-sm">
                        <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            value={busqueda}
                            onChange={(e) => setBusqueda(e.target.value)}
                            placeholder="Buscar por cliente, RFC, folio o UUID…"
                            aria-label="Buscar facturas"
                            className="pl-8"
                        />
                    </div>
                    <div className="flex gap-1">
                        {[undefined, 'valid', 'canceled', 'failed'].map(
                            (status) => (
                                <Button
                                    key={status ?? 'all'}
                                    variant={
                                        filters.status === status
                                            ? 'default'
                                            : 'outline'
                                    }
                                    size="sm"
                                    onClick={() =>
                                        router.get(
                                            invoices.index().url,
                                            {
                                                ...(status ? { status } : {}),
                                                ...(filters.search
                                                    ? { search: filters.search }
                                                    : {}),
                                            },
                                            { preserveState: true },
                                        )
                                    }
                                >
                                    {status
                                        ? etiquetaEstatus(
                                              'factura',
                                              status as Invoice['status'],
                                          )
                                        : 'Todas'}
                                </Button>
                            ),
                        )}
                    </div>
                </div>

                {puedeDescargar && seleccion.size > 0 && (
                    <div className="flex flex-wrap items-center gap-2 rounded-xl border bg-muted/40 p-3">
                        <span className="text-sm font-medium">
                            {seleccion.size} factura(s) seleccionada(s)
                        </span>
                        <div className="order-last flex max-h-24 basis-full flex-wrap gap-1 overflow-y-auto">
                            {[...seleccion.entries()].map(([id, nombre]) => (
                                <Badge
                                    key={id}
                                    variant="secondary"
                                    className="gap-1 pr-1"
                                >
                                    {nombre}
                                    <button
                                        type="button"
                                        aria-label={`Quitar ${nombre}`}
                                        className="rounded-sm hover:bg-background/60"
                                        onClick={() =>
                                            setSeleccion((actual) => {
                                                const nueva = new Map(actual);
                                                nueva.delete(id);

                                                return nueva;
                                            })
                                        }
                                    >
                                        <X className="size-3" />
                                    </button>
                                </Badge>
                            ))}
                        </div>
                        <div className="ml-auto flex gap-2">
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setSeleccion(new Map())}
                            >
                                Quitar todas
                            </Button>
                            {excedido ? (
                                <Button size="sm" disabled>
                                    Máximo {maxXmls} por descarga
                                </Button>
                            ) : (
                                <Button size="sm" asChild>
                                    <a href={urlZip}>
                                        <FileDown /> Descargar XML (
                                        {seleccion.size})
                                    </a>
                                </Button>
                            )}
                        </div>
                    </div>
                )}

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                {puedeDescargar && (
                                    <TableHead className="w-10">
                                        <Checkbox
                                            aria-label="Seleccionar las facturas de esta página"
                                            disabled={conXml.length === 0}
                                            checked={
                                                todasMarcadas
                                                    ? true
                                                    : algunaMarcada
                                                      ? 'indeterminate'
                                                      : false
                                            }
                                            onCheckedChange={(marcada) =>
                                                alternarPagina(marcada === true)
                                            }
                                        />
                                    </TableHead>
                                )}
                                <TableHead>Cliente</TableHead>
                                <TableHead>Folio</TableHead>
                                <TableHead>UUID</TableHead>
                                <TableHead>Total</TableHead>
                                <TableHead>Emitida</TableHead>
                                <TableHead>Estado</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {paginator.data.length === 0 && (
                                <TableEmpty colSpan={puedeDescargar ? 7 : 6}>
                                    {filters.search
                                        ? 'Ninguna factura coincide con la búsqueda.'
                                        : 'No hay facturas generadas.'}
                                </TableEmpty>
                            )}
                            {paginator.data.map((invoice) => (
                                <TableRow
                                    key={invoice.id}
                                    className="cursor-pointer"
                                    data-state={
                                        seleccion.has(invoice.id)
                                            ? 'selected'
                                            : undefined
                                    }
                                    onClick={() =>
                                        router.get(invoices.show(invoice).url)
                                    }
                                >
                                    {puedeDescargar && (
                                        <TableCell
                                            onClick={(e) => e.stopPropagation()}
                                        >
                                            {tieneXml(invoice) && (
                                                <Checkbox
                                                    aria-label={`Seleccionar la factura ${nombreFactura(invoice)}`}
                                                    checked={seleccion.has(
                                                        invoice.id,
                                                    )}
                                                    onCheckedChange={(
                                                        marcada,
                                                    ) =>
                                                        alternar(
                                                            invoice,
                                                            marcada === true,
                                                        )
                                                    }
                                                />
                                            )}
                                        </TableCell>
                                    )}
                                    <TableCell className="font-medium">
                                        <Link
                                            href={invoices.show(invoice)}
                                            className="hover:underline"
                                        >
                                            {invoice.customer?.legal_name ??
                                                '—'}
                                        </Link>
                                        {invoice.customer?.rfc && (
                                            <span className="block font-mono text-xs font-normal text-muted-foreground">
                                                {invoice.customer.rfc}
                                            </span>
                                        )}
                                        {invoice.is_global && (
                                            <Badge
                                                variant="secondary"
                                                className="ml-2"
                                            >
                                                Global
                                            </Badge>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {invoice.series ?? ''}
                                        {invoice.folio ?? '—'}
                                    </TableCell>
                                    <TableCell className="font-mono text-xs">
                                        {invoice.uuid ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        {currency.format(Number(invoice.total))}
                                    </TableCell>
                                    <TableCell>
                                        {fechaMxDeMomento(invoice.issued_at)}
                                    </TableCell>
                                    <TableCell>
                                        <EstatusBadge
                                            tipo="factura"
                                            estatus={invoice.status}
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <Pagination paginator={paginator} preserveState />
            </div>
        </>
    );
}

InvoicesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Facturas', href: invoices.index() },
    ],
};
