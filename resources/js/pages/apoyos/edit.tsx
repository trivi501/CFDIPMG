import { Head, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import { dashboard } from '@/routes';
import apoyos from '@/routes/apoyos';

type Beneficiario = { id: number; nombre: string };
type PersonaApoya = { id: number; nombre: string };

type Detalle = {
    cantidad: string;
    articulo: string;
    costo_unidad: string;
    iva: string;
};

type ApoyoDetalle = {
    id: number;
    cantidad: string;
    articulo: string;
    costo_unidad: string;
    iva: string;
};

type Apoyo = {
    id: number;
    fecha: string;
    beneficiario_id: number;
    persona_apoya_id: number;
    preparado: boolean;
    facturado: boolean;
    solicitud_recibo_path: string | null;
    detalles: ApoyoDetalle[];
};

function toDetalle(d: ApoyoDetalle): Detalle {
    return {
        cantidad: d.cantidad,
        articulo: d.articulo,
        costo_unidad: d.costo_unidad,
        iva: d.iva,
    };
}

const currency = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});

export default function ApoyoEdit({
    apoyo,
    beneficiarios,
    personasApoya,
}: {
    apoyo: Apoyo;
    beneficiarios: Beneficiario[];
    personasApoya: PersonaApoya[];
}) {
    const { data, setData, put, processing, errors } = useForm({
        fecha: apoyo.fecha,
        beneficiario_id: String(apoyo.beneficiario_id),
        persona_apoya_id: String(apoyo.persona_apoya_id),
        preparado: apoyo.preparado,
        facturado: apoyo.facturado,
        solicitud_recibo: null as File | null,
        detalles: apoyo.detalles.map(toDetalle),
    });

    const updateDetalle = (index: number, patch: Partial<Detalle>) => {
        setData(
            'detalles',
            data.detalles.map((detalle, i) =>
                i === index ? { ...detalle, ...patch } : detalle,
            ),
        );
    };

    const addDetalle = () =>
        setData('detalles', [
            ...data.detalles,
            { cantidad: '1', articulo: '', costo_unidad: '', iva: '0' },
        ]);

    const removeDetalle = (index: number) =>
        setData(
            'detalles',
            data.detalles.filter((_, i) => i !== index),
        );

    const subTotal = data.detalles.reduce(
        (sum, d) =>
            sum + (Number(d.cantidad) || 0) * (Number(d.costo_unidad) || 0),
        0,
    );
    const iva = data.detalles.reduce((sum, d) => sum + (Number(d.iva) || 0), 0);
    const montoTotal = subTotal + iva;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(apoyos.update(apoyo).url);
    };

    return (
        <>
            <Head title="Editar apoyo" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading title="Editar apoyo" />

                <form
                    onSubmit={submit}
                    encType="multipart/form-data"
                    className="max-w-3xl space-y-6"
                >
                    <Card>
                        <CardHeader>
                            <CardTitle>Datos del apoyo</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="fecha">Fecha</Label>
                                <Input
                                    id="fecha"
                                    type="date"
                                    value={data.fecha}
                                    onChange={(e) =>
                                        setData('fecha', e.target.value)
                                    }
                                    required
                                />
                                <InputError message={errors.fecha} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="beneficiario_id">
                                    Beneficiario
                                </Label>
                                <NativeSelect
                                    id="beneficiario_id"
                                    value={data.beneficiario_id}
                                    onChange={(e) =>
                                        setData(
                                            'beneficiario_id',
                                            e.target.value,
                                        )
                                    }
                                    required
                                >
                                    {beneficiarios.map((beneficiario) => (
                                        <option
                                            key={beneficiario.id}
                                            value={beneficiario.id}
                                        >
                                            {beneficiario.nombre}
                                        </option>
                                    ))}
                                </NativeSelect>
                                <InputError message={errors.beneficiario_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="persona_apoya_id">
                                    Persona Apoya
                                </Label>
                                <NativeSelect
                                    id="persona_apoya_id"
                                    value={data.persona_apoya_id}
                                    onChange={(e) =>
                                        setData(
                                            'persona_apoya_id',
                                            e.target.value,
                                        )
                                    }
                                    required
                                >
                                    {personasApoya.map((persona) => (
                                        <option
                                            key={persona.id}
                                            value={persona.id}
                                        >
                                            {persona.nombre}
                                        </option>
                                    ))}
                                </NativeSelect>
                                <InputError message={errors.persona_apoya_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="solicitud_recibo">
                                    Solicitud Recibo (PDF)
                                </Label>
                                {apoyo.solicitud_recibo_path && (
                                    <a
                                        href={`/storage/${apoyo.solicitud_recibo_path}`}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="text-sm text-primary underline"
                                    >
                                        Ver archivo actual
                                    </a>
                                )}
                                <Input
                                    id="solicitud_recibo"
                                    type="file"
                                    accept="application/pdf"
                                    onChange={(e) =>
                                        setData(
                                            'solicitud_recibo',
                                            e.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                                <InputError message={errors.solicitud_recibo} />
                            </div>

                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="preparado"
                                    checked={data.preparado}
                                    onCheckedChange={(checked) =>
                                        setData('preparado', checked === true)
                                    }
                                />
                                <Label
                                    htmlFor="preparado"
                                    className="font-normal"
                                >
                                    Preparado
                                </Label>
                            </div>

                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="facturado"
                                    checked={data.facturado}
                                    onCheckedChange={(checked) =>
                                        setData('facturado', checked === true)
                                    }
                                />
                                <Label
                                    htmlFor="facturado"
                                    className="font-normal"
                                >
                                    Facturado
                                </Label>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle>Detalle Apoyos</CardTitle>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={addDetalle}
                            >
                                <Plus /> Agregar línea
                            </Button>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="overflow-x-auto rounded-lg border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Artículo</TableHead>
                                            <TableHead>Cant.</TableHead>
                                            <TableHead>Costo unidad</TableHead>
                                            <TableHead>IVA</TableHead>
                                            <TableHead className="text-right">
                                                Sub total
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Total
                                            </TableHead>
                                            <TableHead />
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {data.detalles.map((detalle, index) => {
                                            const lineSubTotal =
                                                (Number(detalle.cantidad) ||
                                                    0) *
                                                (Number(detalle.costo_unidad) ||
                                                    0);
                                            const lineTotal =
                                                lineSubTotal +
                                                (Number(detalle.iva) || 0);

                                            return (
                                                <TableRow key={index}>
                                                    <TableCell className="min-w-48">
                                                        <Input
                                                            value={
                                                                detalle.articulo
                                                            }
                                                            onChange={(e) =>
                                                                updateDetalle(
                                                                    index,
                                                                    {
                                                                        articulo:
                                                                            e
                                                                                .target
                                                                                .value,
                                                                    },
                                                                )
                                                            }
                                                            required
                                                        />
                                                    </TableCell>
                                                    <TableCell className="w-24">
                                                        <Input
                                                            type="number"
                                                            min="0.01"
                                                            step="0.01"
                                                            value={
                                                                detalle.cantidad
                                                            }
                                                            onChange={(e) =>
                                                                updateDetalle(
                                                                    index,
                                                                    {
                                                                        cantidad:
                                                                            e
                                                                                .target
                                                                                .value,
                                                                    },
                                                                )
                                                            }
                                                            required
                                                        />
                                                    </TableCell>
                                                    <TableCell className="w-32">
                                                        <Input
                                                            type="number"
                                                            min="0"
                                                            step="0.01"
                                                            value={
                                                                detalle.costo_unidad
                                                            }
                                                            onChange={(e) =>
                                                                updateDetalle(
                                                                    index,
                                                                    {
                                                                        costo_unidad:
                                                                            e
                                                                                .target
                                                                                .value,
                                                                    },
                                                                )
                                                            }
                                                            required
                                                        />
                                                    </TableCell>
                                                    <TableCell className="w-32">
                                                        <Input
                                                            type="number"
                                                            min="0"
                                                            step="0.01"
                                                            value={detalle.iva}
                                                            onChange={(e) =>
                                                                updateDetalle(
                                                                    index,
                                                                    {
                                                                        iva: e
                                                                            .target
                                                                            .value,
                                                                    },
                                                                )
                                                            }
                                                        />
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        {currency.format(
                                                            lineSubTotal,
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        {currency.format(
                                                            lineTotal,
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        {data.detalles.length >
                                                            1 && (
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                onClick={() =>
                                                                    removeDetalle(
                                                                        index,
                                                                    )
                                                                }
                                                            >
                                                                <Trash2 className="size-4 text-destructive" />
                                                            </Button>
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
                                    </TableBody>
                                </Table>
                            </div>

                            <div className="text-right text-sm">
                                <p className="text-muted-foreground">
                                    Sub Total: {currency.format(subTotal)} ·
                                    IVA: {currency.format(iva)}
                                </p>
                                <p className="text-lg font-semibold">
                                    Monto Total: {currency.format(montoTotal)}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Button disabled={processing}>Guardar cambios</Button>
                </form>
            </div>
        </>
    );
}

ApoyoEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Apoyos', href: apoyos.index() },
        { title: 'Editar', href: '#' },
    ],
};
