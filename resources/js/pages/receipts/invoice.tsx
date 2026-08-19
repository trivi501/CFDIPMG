import { Head, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { CFDI_USES, PAYMENT_FORMS, PAYMENT_METHODS } from '@/lib/sat-catalogs';
import { dashboard } from '@/routes';
import receipts from '@/routes/receipts';

type Receipt = {
    id: number;
    external_id: string;
    source_system: string;
    amount: string;
    currency: string;
    concept: string | null;
    customer_payload: { name?: string; email?: string; rfc?: string };
};

type Customer = { id: number; legal_name: string; rfc: string };

type Product = {
    id: number;
    description: string;
    sat_product_key: string;
    sat_unit_key: string;
    unit_price: string;
};

type Item = {
    description: string;
    sat_product_key: string;
    sat_unit_key: string;
    quantity: string;
    unit_price: string;
};

type JsonRecord = Record<string, unknown>;

type PagosConcepto = {
    cuenta_codigo?: string;
    cuenta_descripcion?: string;
    concepto?: string;
    cantidad?: number;
    monto?: number;
};

type PagosFormaPago = {
    forma_pago?: string;
    clave_sat?: string;
    monto?: number;
};

type PagosContext = {
    contribuyente: JsonRecord | null;
    datos_facturacion: JsonRecord | null;
    predio: JsonRecord | null;
    conceptos: PagosConcepto[];
    formas_pago: PagosFormaPago[];
    caja: JsonRecord | null;
};

function humanizeKey(key: string): string {
    return key.charAt(0).toUpperCase() + key.slice(1).replaceAll('_', ' ');
}

function DataList({ data }: { data: JsonRecord }) {
    const entries = Object.entries(data).filter(
        ([, value]) => value !== null && value !== undefined && value !== '',
    );

    if (entries.length === 0) {
        return <p className="text-sm text-muted-foreground">Sin datos.</p>;
    }

    return (
        <dl className="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
            {entries.map(([key, value]) => (
                <div key={key} className="contents">
                    <dt className="text-muted-foreground">
                        {humanizeKey(key)}
                    </dt>
                    <dd>
                        {typeof value === 'object' ? (
                            <DataList data={value as JsonRecord} />
                        ) : (
                            String(value)
                        )}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

function emptyItem(receipt: Receipt): Item {
    return {
        description: receipt.concept ?? '',
        sat_product_key: '',
        sat_unit_key: '',
        quantity: '1',
        unit_price: receipt.amount,
    };
}

export default function ReceiptInvoice({
    receipt,
    customers,
    products,
    pagos,
    suggestedCustomerId,
    suggestedPaymentForm,
}: {
    receipt: Receipt;
    customers: Customer[];
    products: Product[];
    pagos: PagosContext | null;
    suggestedCustomerId: number | null;
    suggestedPaymentForm: string | null;
}) {
    const { data, setData, post, processing, errors } = useForm({
        customer_id: suggestedCustomerId ? String(suggestedCustomerId) : '',
        payment_form: suggestedPaymentForm ?? '03',
        payment_method: 'PUE',
        use: 'G03',
        items: [emptyItem(receipt)] as Item[],
    });

    const updateItem = (index: number, patch: Partial<Item>) => {
        setData(
            'items',
            data.items.map((item, i) =>
                i === index ? { ...item, ...patch } : item,
            ),
        );
    };

    const applyProduct = (index: number, productId: string) => {
        const product = products.find((p) => String(p.id) === productId);

        if (!product) {
            return;
        }

        updateItem(index, {
            description: product.description,
            sat_product_key: product.sat_product_key,
            sat_unit_key: product.sat_unit_key,
            unit_price: product.unit_price,
        });
    };

    const addItem = () => setData('items', [...data.items, emptyItem(receipt)]);

    const removeItem = (index: number) =>
        setData(
            'items',
            data.items.filter((_, i) => i !== index),
        );

    const total = data.items.reduce(
        (sum, item) =>
            sum + (Number(item.quantity) || 0) * (Number(item.unit_price) || 0),
        0,
    );

    const currency = new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(receipts.invoice.store(receipt).url);
    };

    return (
        <>
            <Head title="Facturar recibo" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    title="Facturar recibo"
                    description={`${receipt.source_system} · ${receipt.external_id} · ${currency.format(Number(receipt.amount))}`}
                />

                <form onSubmit={submit} className="max-w-3xl space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Datos del cliente y CFDI</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-3">
                            <div className="grid gap-2 sm:col-span-3">
                                <Label htmlFor="customer_id">Cliente</Label>
                                <NativeSelect
                                    id="customer_id"
                                    value={data.customer_id}
                                    onChange={(e) =>
                                        setData('customer_id', e.target.value)
                                    }
                                    required
                                >
                                    <option value="" disabled>
                                        {receipt.customer_payload?.name
                                            ? `Selecciona un cliente (recibo a nombre de: ${receipt.customer_payload.name})`
                                            : 'Selecciona un cliente'}
                                    </option>
                                    {customers.map((customer) => (
                                        <option
                                            key={customer.id}
                                            value={customer.id}
                                        >
                                            {customer.legal_name} —{' '}
                                            {customer.rfc}
                                        </option>
                                    ))}
                                </NativeSelect>
                                <InputError message={errors.customer_id} />
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
                                <InputError message={errors.payment_form} />
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
                                <InputError message={errors.payment_method} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="use">Uso de CFDI</Label>
                                <NativeSelect
                                    id="use"
                                    value={data.use}
                                    onChange={(e) =>
                                        setData('use', e.target.value)
                                    }
                                    required
                                >
                                    {CFDI_USES.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                                <InputError message={errors.use} />
                            </div>
                        </CardContent>
                    </Card>

                    {pagos && (
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    Datos del pago (sistema de Pagos
                                    municipales)
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {pagos.contribuyente && (
                                    <div>
                                        <p className="mb-1 text-sm font-medium">
                                            Contribuyente
                                        </p>
                                        <DataList data={pagos.contribuyente} />
                                    </div>
                                )}

                                {pagos.datos_facturacion ? (
                                    <div>
                                        <p className="mb-1 text-sm font-medium">
                                            Datos de facturación capturados
                                        </p>
                                        <DataList
                                            data={pagos.datos_facturacion}
                                        />
                                        {!suggestedCustomerId && (
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                No encontramos un cliente con
                                                ese RFC ya registrado: crea o
                                                elige el cliente manualmente
                                                arriba usando estos datos.
                                            </p>
                                        )}
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        Este contribuyente no tiene datos de
                                        facturación capturados en el sistema de
                                        pagos. Selecciona o crea el cliente
                                        manualmente.
                                    </p>
                                )}

                                {pagos.predio && (
                                    <div>
                                        <p className="mb-1 text-sm font-medium">
                                            Predio
                                        </p>
                                        <DataList data={pagos.predio} />
                                    </div>
                                )}

                                {pagos.conceptos.length > 0 && (
                                    <div>
                                        <p className="mb-1 text-sm font-medium">
                                            Desglose de conceptos
                                        </p>
                                        <div className="rounded-lg border">
                                            <Table>
                                                <TableHeader>
                                                    <TableRow>
                                                        <TableHead>
                                                            Cuenta
                                                        </TableHead>
                                                        <TableHead>
                                                            Concepto
                                                        </TableHead>
                                                        <TableHead className="text-right">
                                                            Monto
                                                        </TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    {pagos.conceptos.map(
                                                        (concepto, index) => (
                                                            <TableRow
                                                                key={index}
                                                            >
                                                                <TableCell className="text-xs">
                                                                    {
                                                                        concepto.cuenta_codigo
                                                                    }
                                                                    <span className="block text-muted-foreground">
                                                                        {
                                                                            concepto.cuenta_descripcion
                                                                        }
                                                                    </span>
                                                                </TableCell>
                                                                <TableCell>
                                                                    {
                                                                        concepto.concepto
                                                                    }
                                                                </TableCell>
                                                                <TableCell className="text-right">
                                                                    {currency.format(
                                                                        Number(
                                                                            concepto.monto ??
                                                                                0,
                                                                        ),
                                                                    )}
                                                                </TableCell>
                                                            </TableRow>
                                                        ),
                                                    )}
                                                </TableBody>
                                            </Table>
                                        </div>
                                    </div>
                                )}

                                {pagos.formas_pago.length > 0 && (
                                    <div>
                                        <p className="mb-1 text-sm font-medium">
                                            Formas de pago registradas
                                        </p>
                                        <ul className="text-sm">
                                            {pagos.formas_pago.map(
                                                (forma, index) => (
                                                    <li key={index}>
                                                        {forma.forma_pago}{' '}
                                                        (clave SAT{' '}
                                                        {forma.clave_sat}) —{' '}
                                                        {currency.format(
                                                            Number(
                                                                forma.monto ??
                                                                    0,
                                                            ),
                                                        )}
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    </div>
                                )}

                                {pagos.caja && (
                                    <div>
                                        <p className="mb-1 text-sm font-medium">
                                            Caja
                                        </p>
                                        <DataList data={pagos.caja} />
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    <Card>
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle>Conceptos</CardTitle>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={addItem}
                            >
                                <Plus /> Agregar línea
                            </Button>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {data.items.map((item, index) => (
                                <div
                                    key={index}
                                    className="space-y-3 rounded-lg border p-3"
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        {products.length > 0 && (
                                            <NativeSelect
                                                className="max-w-xs"
                                                defaultValue=""
                                                onChange={(e) =>
                                                    applyProduct(
                                                        index,
                                                        e.target.value,
                                                    )
                                                }
                                            >
                                                <option value="">
                                                    Cargar desde catálogo…
                                                </option>
                                                {products.map((product) => (
                                                    <option
                                                        key={product.id}
                                                        value={product.id}
                                                    >
                                                        {product.description}
                                                    </option>
                                                ))}
                                            </NativeSelect>
                                        )}
                                        {data.items.length > 1 && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    removeItem(index)
                                                }
                                            >
                                                <Trash2 className="size-4 text-destructive" />
                                            </Button>
                                        )}
                                    </div>

                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="grid gap-2 sm:col-span-2">
                                            <Label>Descripción</Label>
                                            <Input
                                                value={item.description}
                                                onChange={(e) =>
                                                    updateItem(index, {
                                                        description:
                                                            e.target.value,
                                                    })
                                                }
                                                required
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label>
                                                Clave SAT producto/servicio
                                            </Label>
                                            <Input
                                                value={item.sat_product_key}
                                                onChange={(e) =>
                                                    updateItem(index, {
                                                        sat_product_key:
                                                            e.target.value,
                                                    })
                                                }
                                                maxLength={8}
                                                required
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label>Clave SAT unidad</Label>
                                            <Input
                                                value={item.sat_unit_key}
                                                onChange={(e) =>
                                                    updateItem(index, {
                                                        sat_unit_key:
                                                            e.target.value,
                                                    })
                                                }
                                                maxLength={3}
                                                required
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label>Cantidad</Label>
                                            <Input
                                                type="number"
                                                min="0.01"
                                                step="0.01"
                                                value={item.quantity}
                                                onChange={(e) =>
                                                    updateItem(index, {
                                                        quantity:
                                                            e.target.value,
                                                    })
                                                }
                                                required
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label>
                                                Precio unitario (sin IVA)
                                            </Label>
                                            <Input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={item.unit_price}
                                                onChange={(e) =>
                                                    updateItem(index, {
                                                        unit_price:
                                                            e.target.value,
                                                    })
                                                }
                                                required
                                            />
                                        </div>
                                    </div>
                                </div>
                            ))}

                            <div className="text-right text-sm">
                                <p className="text-muted-foreground">
                                    Subtotal: {currency.format(total)} · IVA
                                    (16%): {currency.format(total * 0.16)}
                                </p>
                                <p className="text-lg font-semibold">
                                    Total: {currency.format(total * 1.16)}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Button disabled={processing}>Generar factura</Button>
                </form>
            </div>
        </>
    );
}

ReceiptInvoice.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Recibos', href: receipts.index() },
        { title: 'Facturar', href: '#' },
    ],
};
