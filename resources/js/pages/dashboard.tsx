import { Head, Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import invoices from '@/routes/invoices';

type Invoice = {
    id: number;
    uuid: string | null;
    folio: string | null;
    status: string;
    total: string;
    issued_at: string | null;
    customer: { legal_name: string } | null;
};

type PageProps = {
    stats: {
        pendingReceipts: number;
        invoicedThisMonth: number;
        failedReceipts: number;
        totalAmountThisMonth: string;
    };
    recentInvoices: Invoice[];
};

const currency = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});

export default function Dashboard({ stats, recentInvoices }: PageProps) {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="grid auto-rows-min gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Recibos pendientes
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-2xl font-semibold">
                            {stats.pendingReceipts}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Facturadas este mes
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-2xl font-semibold">
                            {stats.invoicedThisMonth}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Recibos fallidos
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-2xl font-semibold">
                            {stats.failedReceipts}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Monto facturado (mes)
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-2xl font-semibold">
                            {currency.format(
                                Number(stats.totalAmountThisMonth),
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card className="flex-1">
                    <CardHeader>
                        <CardTitle>Facturas recientes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {recentInvoices.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Aún no se han generado facturas.
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {recentInvoices.map((invoice) => (
                                    <li
                                        key={invoice.id}
                                        className="flex items-center justify-between py-3 text-sm"
                                    >
                                        <div>
                                            <Link
                                                href={invoices.show(invoice)}
                                                className="font-medium hover:underline"
                                            >
                                                {invoice.customer?.legal_name ??
                                                    'Cliente'}
                                            </Link>
                                            <p className="text-muted-foreground">
                                                Folio {invoice.folio ?? '—'}
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p className="font-medium">
                                                {currency.format(
                                                    Number(invoice.total),
                                                )}
                                            </p>
                                            <p className="text-muted-foreground capitalize">
                                                {invoice.status}
                                            </p>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
