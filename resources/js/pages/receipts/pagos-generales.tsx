import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import invoices from '@/routes/invoices';
import receipts from '@/routes/receipts';
import receiptsLookup from '@/routes/receipts/lookup';
import pagosGenerales from '@/routes/receipts/pagos-generales';

type Pago = {
    folio: string;
    fecha: string | null;
    tipo_pago: string | null;
    descripcion: string | null;
    monto: number;
    contribuyente_nombre: string | null;
    receipt_status: 'pending' | 'invoiced' | 'canceled' | 'failed' | null;
    invoice_id: number | null;
};

const currency = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});

export default function PagosGenerales({ pagos }: { pagos: Pago[] }) {
    const [tipoFilter, setTipoFilter] = useState<string | null>(null);

    const tipos = useMemo(
        () =>
            Array.from(
                new Set(pagos.map((pago) => pago.tipo_pago).filter(Boolean)),
            ) as string[],
        [pagos],
    );

    const filteredPagos = tipoFilter
        ? pagos.filter((pago) => pago.tipo_pago === tipoFilter)
        : pagos;

    const facturar = (folio: string) => {
        router.post(receiptsLookup.store().url, { folio });
    };

    return (
        <>
            <Head title="Pagos generales" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    title="Pagos generales"
                    description="Pagos pagados en el sistema de Pagos municipales, listos para facturar."
                />

                {tipos.length > 1 && (
                    <div className="flex flex-wrap gap-1">
                        <Button
                            variant={
                                tipoFilter === null ? 'default' : 'outline'
                            }
                            size="sm"
                            onClick={() => setTipoFilter(null)}
                        >
                            Todos ({pagos.length})
                        </Button>
                        {tipos.map((tipo) => (
                            <Button
                                key={tipo}
                                variant={
                                    tipoFilter === tipo ? 'default' : 'outline'
                                }
                                size="sm"
                                onClick={() => setTipoFilter(tipo)}
                            >
                                {tipo} (
                                {
                                    pagos.filter(
                                        (pago) => pago.tipo_pago === tipo,
                                    ).length
                                }
                                )
                            </Button>
                        ))}
                    </div>
                )}

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Folio</TableHead>
                                <TableHead>Tipo</TableHead>
                                <TableHead>Fecha</TableHead>
                                <TableHead>Contribuyente</TableHead>
                                <TableHead>Descripción</TableHead>
                                <TableHead>Monto</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead className="text-right">
                                    Acciones
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {filteredPagos.length === 0 && (
                                <TableEmpty colSpan={8}>
                                    No hay pagos que coincidan.
                                </TableEmpty>
                            )}
                            {filteredPagos.map((pago) => (
                                <TableRow key={pago.folio}>
                                    <TableCell className="font-mono text-xs">
                                        {pago.folio}
                                    </TableCell>
                                    <TableCell>
                                        {pago.tipo_pago ?? '—'}
                                    </TableCell>
                                    <TableCell>{pago.fecha ?? '—'}</TableCell>
                                    <TableCell>
                                        {pago.contribuyente_nombre ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        {pago.descripcion ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        {currency.format(Number(pago.monto))}
                                    </TableCell>
                                    <TableCell>
                                        {pago.receipt_status === 'invoiced' ? (
                                            <Badge>Facturado</Badge>
                                        ) : (
                                            <Badge variant="outline">
                                                Sin facturar
                                            </Badge>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {pago.receipt_status === 'invoiced' &&
                                        pago.invoice_id ? (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={invoices.show(
                                                        pago.invoice_id,
                                                    )}
                                                >
                                                    Ver factura
                                                </Link>
                                            </Button>
                                        ) : (
                                            <Button
                                                size="sm"
                                                onClick={() =>
                                                    facturar(pago.folio)
                                                }
                                            >
                                                Facturar
                                            </Button>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}

PagosGenerales.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Recibos', href: receipts.index() },
        { title: 'Pagos generales', href: pagosGenerales.index() },
    ],
};
