import { Head, useForm } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Combobox } from '@/components/ui/combobox';
import type { ComboboxOption } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import facturasCompra from '@/routes/facturas-compra';

type Articulo = {
    id: number;
    descripcion: string;
    cantidad_total: string;
    costo_unidad: string;
    iva: string;
    cantidad_disponible: string;
};

type Factura = {
    id: number;
    emisor: string | null;
    fecha: string | null;
    total: string;
    xml_path: string;
    articulos: Articulo[];
};

const currency = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});

export default function FacturasCompraIndex({
    facturas,
}: {
    facturas: Factura[];
}) {
    const { can } = usePermissions();
    const { data, setData, post, processing, errors, reset } = useForm({
        xml: null as File | null,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(facturasCompra.store().url, {
            forceFormData: true,
            onSuccess: () => reset(),
        });
    };

    const [selectedFacturaId, setSelectedFacturaId] = useState('');

    const facturaOptions: ComboboxOption[] = facturas.map((factura) => ({
        value: String(factura.id),
        label:
            (factura.emisor ?? 'Factura de compra') +
            (factura.fecha ? ` · ${factura.fecha}` : ''),
    }));

    const facturasAMostrar = selectedFacturaId
        ? facturas.filter((factura) => String(factura.id) === selectedFacturaId)
        : facturas;

    return (
        <>
            <Head title="Facturas de compra" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    title="Facturas de compra"
                    description="Registra el XML de una factura de compra para repartir sus artículos entre varios apoyos, descontando el inventario disponible conforme se usan."
                />

                {can('apoyos.manage') && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Registrar factura</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={submit}
                                encType="multipart/form-data"
                                className="flex flex-wrap items-end gap-4"
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="xml">Factura XML</Label>
                                    <Input
                                        id="xml"
                                        type="file"
                                        accept=".xml,text/xml,application/xml"
                                        onChange={(e) =>
                                            setData(
                                                'xml',
                                                e.target.files?.[0] ?? null,
                                            )
                                        }
                                    />
                                    <InputError message={errors.xml} />
                                </div>
                                <Button disabled={processing || !data.xml}>
                                    <Upload /> Registrar
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {facturas.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        No hay facturas de compra registradas.
                    </p>
                )}

                {facturas.length > 0 && (
                    <div className="flex max-w-sm items-end gap-2">
                        <div className="grid flex-1 gap-2">
                            <Label htmlFor="buscar_factura">
                                Buscar factura
                            </Label>
                            <Combobox
                                id="buscar_factura"
                                options={facturaOptions}
                                value={selectedFacturaId}
                                onChange={setSelectedFacturaId}
                                placeholder="Busca por proveedor o fecha"
                            />
                        </div>
                        {selectedFacturaId && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setSelectedFacturaId('')}
                            >
                                Ver todas
                            </Button>
                        )}
                    </div>
                )}

                {facturasAMostrar.map((factura) => (
                    <Card key={factura.id}>
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle>
                                {factura.emisor ?? 'Factura de compra'}
                                {factura.fecha && (
                                    <span className="ml-2 text-sm font-normal text-muted-foreground">
                                        {factura.fecha}
                                    </span>
                                )}
                            </CardTitle>
                            <a
                                href={`/storage/${factura.xml_path}`}
                                target="_blank"
                                rel="noreferrer"
                                className="text-sm text-primary underline"
                            >
                                Ver XML
                            </a>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto rounded-lg border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Artículo</TableHead>
                                            <TableHead className="text-right">
                                                Comprado
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Disponible
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Costo unidad
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {factura.articulos.length === 0 && (
                                            <TableEmpty colSpan={4}>
                                                Sin artículos.
                                            </TableEmpty>
                                        )}
                                        {factura.articulos.map((articulo) => (
                                            <TableRow key={articulo.id}>
                                                <TableCell className="font-medium">
                                                    {articulo.descripcion}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {articulo.cantidad_total}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <span
                                                        className={
                                                            Number(
                                                                articulo.cantidad_disponible,
                                                            ) === 0
                                                                ? 'text-muted-foreground'
                                                                : 'font-medium text-primary'
                                                        }
                                                    >
                                                        {
                                                            articulo.cantidad_disponible
                                                        }
                                                    </span>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {currency.format(
                                                        Number(
                                                            articulo.costo_unidad,
                                                        ),
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>
        </>
    );
}

FacturasCompraIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Facturas de compra', href: facturasCompra.index() },
    ],
};
