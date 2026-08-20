import { Head, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { CFDI_USES, PAYMENT_FORMS, PAYMENT_METHODS } from '@/lib/sat-catalogs';
import { dashboard } from '@/routes';
import invoices from '@/routes/invoices';

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

function emptyItem(): Item {
    return {
        description: '',
        sat_product_key: '',
        sat_unit_key: '',
        quantity: '1',
        unit_price: '',
    };
}

export default function InvoiceCreate({
    customers,
    products,
}: {
    customers: Customer[];
    products: Product[];
}) {
    const { data, setData, post, processing, errors } = useForm({
        customer_id: '',
        payment_form: '03',
        payment_method: 'PUE',
        use: 'G03',
        items: [emptyItem()] as Item[],
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

    const addItem = () => setData('items', [...data.items, emptyItem()]);

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
        post(invoices.store().url);
    };

    return (
        <>
            <Head title="Factura abierta" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    title="Generar factura abierta"
                    description="Para cuando no hay un pago registrado en el sistema: captura el cliente y los conceptos manualmente."
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
                                        Selecciona un cliente
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

InvoiceCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Facturas', href: invoices.index() },
        { title: 'Factura abierta', href: invoices.create() },
    ],
};
