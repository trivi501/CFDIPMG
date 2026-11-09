import { useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Combobox } from '@/components/ui/combobox';
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
import type { ArticuloStock, Paquete } from '@/lib/paquetes';
import paquetes from '@/routes/paquetes';

export type FacturaConEmisor = {
    id: number;
    emisor: string | null;
    fecha: string | null;
    articulos: ArticuloStock[];
};

type Fila = {
    articulo_factura_compra_id: number;
    descripcion: string;
    cantidad: string;
};

export function PaqueteForm({
    paquete,
    facturas,
}: {
    paquete?: Paquete;
    facturas: FacturaConEmisor[];
}) {
    const form = useForm({
        factura_compra_id: paquete ? String(paquete.factura_compra_id) : '',
        nombre: paquete?.nombre ?? '',
        articulos: (paquete?.articulos ?? []).map((a) => ({
            articulo_factura_compra_id: a.articulo_factura_compra_id,
            descripcion: a.descripcion,
            cantidad: String(Number(a.cantidad)),
        })) as Fila[],
    });

    const [articuloId, setArticuloId] = useState('');
    const [cantidad, setCantidad] = useState('1');

    const opcionesFactura = facturas.map((factura) => ({
        value: String(factura.id),
        label:
            (factura.emisor ?? 'Factura de compra') +
            (factura.fecha ? ` · ${factura.fecha.slice(0, 10)}` : ''),
    }));

    const opciones = (
        facturas.find((f) => String(f.id) === form.data.factura_compra_id)
            ?.articulos ?? []
    ).map((articulo) => ({
        value: String(articulo.id),
        label: `${articulo.descripcion} · disponible ${Number(articulo.cantidad_disponible)}`,
        descripcion: articulo.descripcion,
    }));

    // Un paquete solo puede llevar artículos de una factura.
    const cambiarFactura = (facturaId: string) => {
        if (facturaId === form.data.factura_compra_id) {
            return;
        }

        form.setData({
            ...form.data,
            factura_compra_id: facturaId,
            articulos: [],
        });
        setArticuloId('');
    };

    const agregar = () => {
        const opcion = opciones.find((o) => o.value === articuloId);

        if (
            !opcion ||
            Number(cantidad) <= 0 ||
            form.data.articulos.some(
                (a) => a.articulo_factura_compra_id === Number(articuloId),
            )
        ) {
            return;
        }

        form.setData('articulos', [
            ...form.data.articulos,
            {
                articulo_factura_compra_id: Number(articuloId),
                descripcion: opcion.descripcion,
                cantidad,
            },
        ]);
        setArticuloId('');
        setCantidad('1');
    };

    const actualizarCantidad = (index: number, valor: string) =>
        form.setData(
            'articulos',
            form.data.articulos.map((a, i) =>
                i === index ? { ...a, cantidad: valor } : a,
            ),
        );

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        if (paquete) {
            form.put(paquetes.update(paquete).url);
        } else {
            form.post(paquetes.store().url);
        }
    };

    const erroresDeArticulos = Object.entries(form.errors)
        .filter(([campo]) => campo.startsWith('articulos'))
        .map(([, mensaje]) => mensaje);

    return (
        <form onSubmit={submit} className="w-full max-w-4xl space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Datos del paquete</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="nombre">Nombre</Label>
                        <Input
                            id="nombre"
                            value={form.data.nombre}
                            onChange={(e) =>
                                form.setData('nombre', e.target.value)
                            }
                            placeholder="Ej. Despensa básica"
                            required
                            autoFocus
                        />
                        <InputError message={form.errors.nombre} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="factura_compra_id">Factura</Label>
                        <Combobox
                            id="factura_compra_id"
                            options={opcionesFactura}
                            value={form.data.factura_compra_id}
                            onChange={cambiarFactura}
                            placeholder="Busca una factura"
                        />
                        <p className="text-sm text-muted-foreground">
                            Todos los artículos deben ser de esta factura. Si la
                            cambias, se quitan los artículos ya agregados.
                        </p>
                        <InputError message={form.errors.factura_compra_id} />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Artículos</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="flex flex-wrap items-end gap-4">
                        <div className="grid min-w-80 flex-1 gap-2">
                            <Label htmlFor="articulo">Artículo del XML</Label>
                            <Combobox
                                id="articulo"
                                options={opciones}
                                value={articuloId}
                                onChange={setArticuloId}
                                disabled={!form.data.factura_compra_id}
                                placeholder={
                                    form.data.factura_compra_id
                                        ? 'Busca un artículo'
                                        : 'Primero selecciona una factura'
                                }
                            />
                        </div>
                        <div className="grid w-32 gap-2">
                            <Label htmlFor="cantidad">Cantidad</Label>
                            <Input
                                id="cantidad"
                                type="number"
                                min="0.01"
                                step="0.01"
                                value={cantidad}
                                onChange={(e) => setCantidad(e.target.value)}
                            />
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={!articuloId}
                            onClick={agregar}
                        >
                            <Plus /> Agregar
                        </Button>
                    </div>

                    <div className="overflow-x-auto rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Artículo</TableHead>
                                    <TableHead className="w-32">
                                        Cantidad
                                    </TableHead>
                                    <TableHead className="w-12" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {form.data.articulos.length === 0 && (
                                    <TableEmpty colSpan={3}>
                                        Agrega los artículos que lleva cada
                                        paquete.
                                    </TableEmpty>
                                )}
                                {form.data.articulos.map((articulo, index) => (
                                    <TableRow
                                        key={
                                            articulo.articulo_factura_compra_id
                                        }
                                    >
                                        <TableCell className="font-medium">
                                            {articulo.descripcion}
                                        </TableCell>
                                        <TableCell>
                                            <Input
                                                type="number"
                                                min="0.01"
                                                step="0.01"
                                                value={articulo.cantidad}
                                                onChange={(e) =>
                                                    actualizarCantidad(
                                                        index,
                                                        e.target.value,
                                                    )
                                                }
                                                required
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    form.setData(
                                                        'articulos',
                                                        form.data.articulos.filter(
                                                            (_, i) =>
                                                                i !== index,
                                                        ),
                                                    )
                                                }
                                            >
                                                <Trash2 className="size-4 text-destructive" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>

                    {erroresDeArticulos.map((mensaje) => (
                        <InputError key={mensaje} message={mensaje} />
                    ))}
                </CardContent>
            </Card>

            <Button disabled={form.processing}>Guardar paquete</Button>
        </form>
    );
}
