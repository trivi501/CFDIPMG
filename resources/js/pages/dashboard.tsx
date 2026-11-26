import { Head, Link } from '@inertiajs/react';
import type { Graficas } from '@/components/dashboard-graficas';
import { DashboardGraficas } from '@/components/dashboard-graficas';
import { EstatusBadge } from '@/components/estatus-badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { nombreReceptor } from '@/lib/receptor';
import { dashboard } from '@/routes';
import invoices from '@/routes/invoices';

type Invoice = {
    id: number;
    uuid: string | null;
    folio: string | null;
    status: string;
    total: string;
    issued_at: string | null;
    receptor_nombre: string | null;
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
    graficas: Graficas | null;
};

const currency = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});

export default function Dashboard({
    stats,
    recentInvoices,
    graficas,
}: PageProps) {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="grid auto-rows-min gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {[
                        {
                            titulo: 'Recibos pendientes',
                            valor: stats.pendingReceipts.toLocaleString(
                                'es-MX',
                            ),
                        },
                        {
                            titulo: 'Facturadas este mes',
                            valor: stats.invoicedThisMonth.toLocaleString(
                                'es-MX',
                            ),
                        },
                        {
                            titulo: 'Recibos fallidos',
                            valor: stats.failedReceipts.toLocaleString('es-MX'),
                        },
                        {
                            titulo: 'Monto facturado (mes)',
                            valor: currency.format(
                                Number(stats.totalAmountThisMonth),
                            ),
                        },
                    ].map((tarjeta) => (
                        <div
                            key={tarjeta.titulo}
                            className="rounded-xl border bg-card p-4 text-card-foreground shadow-xs"
                        >
                            <p className="text-xs font-medium text-muted-foreground">
                                {tarjeta.titulo}
                            </p>
                            <p className="mt-1 text-2xl font-semibold tabular-nums">
                                {tarjeta.valor}
                            </p>
                        </div>
                    ))}
                </div>

                {graficas && <DashboardGraficas graficas={graficas} />}

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
                                                {nombreReceptor(invoice) ??
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
                                            <EstatusBadge
                                                tipo="factura"
                                                estatus={invoice.status}
                                                className="mt-1"
                                            />
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
