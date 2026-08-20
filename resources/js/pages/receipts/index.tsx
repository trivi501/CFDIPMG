import { Head, Link, router } from '@inertiajs/react';
import { List, Search } from 'lucide-react';
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
import receipts from '@/routes/receipts';
import pagosGenerales from '@/routes/receipts/pagos-generales';

type Receipt = {
    id: number;
    external_id: string;
    source_system: string;
    amount: string;
    currency: string;
    concept: string | null;
    status: 'pending' | 'invoiced' | 'canceled' | 'failed';
    received_at: string;
};

type PageProps = {
    receipts: Paginator<Receipt>;
    filters: { status?: string };
};

const STATUS_LABELS: Record<Receipt['status'], string> = {
    pending: 'Pendiente',
    invoiced: 'Facturado',
    canceled: 'Cancelado',
    failed: 'Fallido',
};

const STATUS_VARIANTS: Record<
    Receipt['status'],
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    pending: 'outline',
    invoiced: 'default',
    canceled: 'secondary',
    failed: 'destructive',
};

const currency = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});

export default function ReceiptsIndex({
    receipts: paginator,
    filters,
}: PageProps) {
    const { can } = usePermissions();

    return (
        <>
            <Head title="Recibos" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Recibos de pago"
                        description="Recibos recibidos del sistema externo, listos para facturar."
                    />
                    {can('receipts.import') && (
                        <div className="flex gap-2">
                            <Button variant="outline" asChild>
                                <Link href={pagosGenerales.index()}>
                                    <List /> Pagos generales
                                </Link>
                            </Button>
                            <Button asChild>
                                <Link href={receipts.lookup.create()}>
                                    <Search /> Checar folio para facturar
                                </Link>
                            </Button>
                        </div>
                    )}
                </div>

                <div className="flex gap-1">
                    {[
                        undefined,
                        'pending',
                        'invoiced',
                        'failed',
                        'canceled',
                    ].map((status) => (
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
                                    receipts.index().url,
                                    status ? { status } : {},
                                    { preserveState: true },
                                )
                            }
                        >
                            {status
                                ? STATUS_LABELS[status as Receipt['status']]
                                : 'Todos'}
                        </Button>
                    ))}
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>ID externo</TableHead>
                                <TableHead>Sistema origen</TableHead>
                                <TableHead>Concepto</TableHead>
                                <TableHead>Monto</TableHead>
                                <TableHead>Recibido</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead className="text-right">
                                    Acciones
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {paginator.data.length === 0 && (
                                <TableEmpty colSpan={7}>
                                    No hay recibos.
                                </TableEmpty>
                            )}
                            {paginator.data.map((receipt) => (
                                <TableRow key={receipt.id}>
                                    <TableCell className="font-mono text-xs">
                                        {receipt.external_id}
                                    </TableCell>
                                    <TableCell>
                                        {receipt.source_system}
                                    </TableCell>
                                    <TableCell>
                                        {receipt.concept ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        {currency.format(
                                            Number(receipt.amount),
                                        )}
                                    </TableCell>
                                    <TableCell>{receipt.received_at}</TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                STATUS_VARIANTS[receipt.status]
                                            }
                                        >
                                            {STATUS_LABELS[receipt.status]}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {receipt.status === 'pending' &&
                                            can('invoices.create') && (
                                                <Button size="sm" asChild>
                                                    <Link
                                                        href={receipts.invoice.create(
                                                            receipt,
                                                        )}
                                                    >
                                                        Facturar
                                                    </Link>
                                                </Button>
                                            )}
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

ReceiptsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Recibos', href: receipts.index() },
    ],
};
