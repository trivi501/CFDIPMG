import { Head, router } from '@inertiajs/react';
import { Ban, FileText, FileType } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    canceled_at: string | null;
    error_message: string | null;
    customer: { legal_name: string; rfc: string; email: string | null } | null;
    paymentReceipt: {
        external_id: string;
        source_system: string;
        concept: string | null;
    } | null;
};

const STATUS_LABELS: Record<Invoice['status'], string> = {
    valid: 'Vigente',
    canceled: 'Cancelada',
    failed: 'Fallida',
};

const currency = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});

export default function InvoiceShow({ invoice }: { invoice: Invoice }) {
    const { can } = usePermissions();

    const cancel = () => {
        if (
            confirm(
                '¿Cancelar esta factura ante el SAT? Esta acción no se puede deshacer.',
            )
        ) {
            router.post(invoices.cancel(invoice).url);
        }
    };

    return (
        <>
            <Head title={`Factura ${invoice.folio ?? invoice.id}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title={`Factura ${invoice.series ?? ''}${invoice.folio ?? ''}`}
                        description={invoice.customer?.legal_name}
                    />
                    <Badge
                        variant={
                            invoice.status === 'valid'
                                ? 'default'
                                : invoice.status === 'failed'
                                  ? 'destructive'
                                  : 'secondary'
                        }
                    >
                        {STATUS_LABELS[invoice.status]}
                    </Badge>
                </div>

                <div className="grid max-w-3xl gap-4 sm:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Cliente</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1 text-sm">
                            <p className="font-medium">
                                {invoice.customer?.legal_name}
                            </p>
                            <p className="text-muted-foreground">
                                RFC: {invoice.customer?.rfc}
                            </p>
                            {invoice.customer?.email && (
                                <p className="text-muted-foreground">
                                    {invoice.customer.email}
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Datos fiscales</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1 text-sm">
                            <p>
                                UUID:{' '}
                                <span className="font-mono text-xs">
                                    {invoice.uuid ?? '—'}
                                </span>
                            </p>
                            <p className="text-muted-foreground">
                                Emitida: {invoice.issued_at ?? '—'}
                            </p>
                            {invoice.canceled_at && (
                                <p className="text-muted-foreground">
                                    Cancelada: {invoice.canceled_at}
                                </p>
                            )}
                            <p className="text-lg font-semibold">
                                {currency.format(Number(invoice.total))}
                            </p>
                        </CardContent>
                    </Card>

                    {invoice.paymentReceipt && (
                        <Card className="sm:col-span-2">
                            <CardHeader>
                                <CardTitle>Recibo de origen</CardTitle>
                            </CardHeader>
                            <CardContent className="text-sm">
                                <p>
                                    {invoice.paymentReceipt.source_system} ·{' '}
                                    {invoice.paymentReceipt.external_id}
                                </p>
                                {invoice.paymentReceipt.concept && (
                                    <p className="text-muted-foreground">
                                        {invoice.paymentReceipt.concept}
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    {invoice.status === 'failed' && invoice.error_message && (
                        <Card className="border-destructive sm:col-span-2">
                            <CardHeader>
                                <CardTitle className="text-destructive">
                                    Error al facturar
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="text-sm">
                                {invoice.error_message}
                            </CardContent>
                        </Card>
                    )}
                </div>

                {invoice.status === 'valid' && (
                    <div className="flex gap-2">
                        {can('invoices.download') && (
                            <>
                                <Button variant="outline" asChild>
                                    <a
                                        href={invoices.pdf(invoice).url}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <FileText /> PDF
                                    </a>
                                </Button>
                                <Button variant="outline" asChild>
                                    <a href={invoices.xml(invoice).url}>
                                        <FileType /> XML
                                    </a>
                                </Button>
                            </>
                        )}
                        {can('invoices.cancel') && (
                            <Button variant="destructive" onClick={cancel}>
                                <Ban /> Cancelar factura
                            </Button>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

InvoiceShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Facturas', href: invoices.index() },
        { title: 'Detalle', href: '#' },
    ],
};
