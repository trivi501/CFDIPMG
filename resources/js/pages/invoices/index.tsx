import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { Pagination } from '@/components/pagination';
import type { Paginator } from '@/components/pagination';
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
import { usePermissions } from '@/hooks/use-permissions';
import { dashboard } from '@/routes';
import invoices from '@/routes/invoices';

type Invoice = {
    id: number;
    uuid: string | null;
    series: string | null;
    folio: string | null;
    status: 'valid' | 'canceled' | 'failed';
    total: string;
    issued_at: string | null;
    customer: { legal_name: string } | null;
};

type PageProps = {
    invoices: Paginator<Invoice>;
    filters: { status?: string };
};

const STATUS_LABELS: Record<Invoice['status'], string> = {
    valid: 'Vigente',
    canceled: 'Cancelada',
    failed: 'Fallida',
};

const STATUS_VARIANTS: Record<
    Invoice['status'],
    'default' | 'secondary' | 'destructive'
> = {
    valid: 'default',
    canceled: 'secondary',
    failed: 'destructive',
};

const currency = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});

export default function InvoicesIndex({
    invoices: paginator,
    filters,
}: PageProps) {
    const { can } = usePermissions();

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
                        <Button asChild>
                            <Link href={invoices.create()}>
                                <Plus /> Factura abierta
                            </Link>
                        </Button>
                    )}
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
                                        status ? { status } : {},
                                        { preserveState: true },
                                    )
                                }
                            >
                                {status
                                    ? STATUS_LABELS[status as Invoice['status']]
                                    : 'Todas'}
                            </Button>
                        ),
                    )}
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
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
                                <TableEmpty colSpan={6}>
                                    No hay facturas generadas.
                                </TableEmpty>
                            )}
                            {paginator.data.map((invoice) => (
                                <TableRow
                                    key={invoice.id}
                                    className="cursor-pointer"
                                    onClick={() =>
                                        router.get(invoices.show(invoice).url)
                                    }
                                >
                                    <TableCell className="font-medium">
                                        <Link
                                            href={invoices.show(invoice)}
                                            className="hover:underline"
                                        >
                                            {invoice.customer?.legal_name ??
                                                '—'}
                                        </Link>
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
                                        {invoice.issued_at ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                STATUS_VARIANTS[invoice.status]
                                            }
                                        >
                                            {STATUS_LABELS[invoice.status]}
                                        </Badge>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <Pagination paginator={paginator} />
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
