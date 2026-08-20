import { Head, Link, router } from '@inertiajs/react';
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
    const facturar = (folio: string) => {
        router.post(receiptsLookup.store().url, { folio });
    };

    return (
        <>
            <Head title="Pagos generales" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    title="Pagos generales"
                    description='Ingresos pagados en el sistema de Pagos municipales, listos para facturar (categoría "Ingresos").'
                />

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Folio</TableHead>
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
                            {pagos.length === 0 && (
                                <TableEmpty colSpan={7}>
                                    No hay pagos generales pendientes.
                                </TableEmpty>
                            )}
                            {pagos.map((pago) => (
                                <TableRow key={pago.folio}>
                                    <TableCell className="font-mono text-xs">
                                        {pago.folio}
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
