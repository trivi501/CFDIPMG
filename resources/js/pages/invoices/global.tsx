import { Head, router, useForm } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { useEffect } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { PAYMENT_FORMS, PAYMENT_METHODS } from '@/lib/sat-catalogs';
import { dashboard } from '@/routes';
import invoices from '@/routes/invoices';
import globalInvoice from '@/routes/invoices/global';

const MONTHS = [
    'Enero',
    'Febrero',
    'Marzo',
    'Abril',
    'Mayo',
    'Junio',
    'Julio',
    'Agosto',
    'Septiembre',
    'Octubre',
    'Noviembre',
    'Diciembre',
];

type Receipt = {
    id: number;
    external_id: string;
    source_system: string;
    amount: string;
    payment_date: string | null;
    received_at: string;
};

const currency = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});

export default function InvoiceGlobal({
    month,
    year,
    receipts,
    total,
}: {
    month: number;
    year: number;
    receipts: Receipt[];
    total: number;
}) {
    const { data, setData, post, processing, errors } = useForm({
        month,
        year,
        payment_form: '01',
        payment_method: 'PUE',
    });

    useEffect(() => {
        setData((current) => ({ ...current, month, year }));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [month, year]);

    const refreshPeriod = (patch: { month?: number; year?: number }) => {
        const next = {
            month: patch.month ?? data.month,
            year: patch.year ?? data.year,
        };
        router.get(globalInvoice.create().url, next, { preserveState: true });
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(globalInvoice.store().url);
    };

    return (
        <>
            <Head title="Factura global" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    title="Factura global"
                    description="Agrupa todos los recibos no facturados de un mes en una sola factura a nombre de público en general."
                />

                <Card className="max-w-lg border-amber-500/50 bg-amber-50 dark:bg-amber-950/20">
                    <CardContent className="flex gap-2 text-sm">
                        <AlertTriangle className="size-4 shrink-0 text-amber-600" />
                        <p>
                            El SAT exige que la factura global mensual se emita
                            a más tardar el día 3 del mes siguiente al periodo
                            que reporta.
                        </p>
                    </CardContent>
                </Card>

                <form onSubmit={submit} className="max-w-3xl space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Periodo y datos del CFDI</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-4">
                            <div className="grid gap-2">
                                <Label htmlFor="month">Mes</Label>
                                <NativeSelect
                                    id="month"
                                    value={data.month}
                                    onChange={(e) =>
                                        refreshPeriod({
                                            month: Number(e.target.value),
                                        })
                                    }
                                >
                                    {MONTHS.map((label, index) => (
                                        <option key={label} value={index + 1}>
                                            {label}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="year">Año</Label>
                                <input
                                    id="year"
                                    type="number"
                                    className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs"
                                    value={data.year}
                                    onChange={(e) =>
                                        refreshPeriod({
                                            year: Number(e.target.value),
                                        })
                                    }
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="payment_form">
                                    Forma de pago
                                </Label>
                                <NativeSelect
                                    id="payment_form"
                                    value={data.payment_form}
                                    onChange={(e) =>
                                        setData('payment_form', e.target.value)
                                    }
                                    required
                                >
                                    {PAYMENT_FORMS.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="payment_method">
                                    Método de pago
                                </Label>
                                <NativeSelect
                                    id="payment_method"
                                    value={data.payment_method}
                                    onChange={(e) =>
                                        setData(
                                            'payment_method',
                                            e.target.value,
                                        )
                                    }
                                    required
                                >
                                    {PAYMENT_METHODS.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>
                                Recibos incluidos ({receipts.length}) —{' '}
                                {MONTHS[month - 1]} {year}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="rounded-lg border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>ID externo</TableHead>
                                            <TableHead>Sistema</TableHead>
                                            <TableHead>Fecha</TableHead>
                                            <TableHead className="text-right">
                                                Monto
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {receipts.length === 0 && (
                                            <TableEmpty colSpan={4}>
                                                No hay recibos pendientes en
                                                este periodo.
                                            </TableEmpty>
                                        )}
                                        {receipts.map((receipt) => (
                                            <TableRow key={receipt.id}>
                                                <TableCell className="font-mono text-xs">
                                                    {receipt.external_id}
                                                </TableCell>
                                                <TableCell>
                                                    {receipt.source_system}
                                                </TableCell>
                                                <TableCell>
                                                    {receipt.payment_date ??
                                                        receipt.received_at}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {currency.format(
                                                        Number(receipt.amount),
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>

                            <p className="text-right text-lg font-semibold">
                                Total: {currency.format(total)}
                            </p>

                            {errors.payment_form && (
                                <p className="text-sm text-destructive">
                                    {errors.payment_form}
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Button disabled={processing || receipts.length === 0}>
                        Generar factura global
                    </Button>
                </form>
            </div>
        </>
    );
}

InvoiceGlobal.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Facturas', href: invoices.index() },
        { title: 'Factura global', href: globalInvoice.create() },
    ],
};
