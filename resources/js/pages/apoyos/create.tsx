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

function emptyDetalle(): Detalle {
    return { cantidad: '1', articulo: '', costo_unidad: '', iva: '0' };
}

const currency = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});

export default function ApoyoCreate({
    beneficiarios,
    personasApoya,
}: {
    beneficiarios: Beneficiario[];
    personasApoya: PersonaApoya[];
}) {
    const { data, setData, post, processing, errors } = useForm({
        fecha: new Date().toISOString().slice(0, 10),
        beneficiario_id: '',
        persona_apoya_id: '',
        preparado: false as boolean,
        facturado: false as boolean,
        solicitud_recibo: null as File | null,
        curp: '',
        curp_archivo: null as File | null,
        rfc: '',
        rfc_archivo: null as File | null,
        ine: null as File | null,
        comprobante_domicilio: null as File | null,
        detalles: [emptyDetalle()] as Detalle[],
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
        setData('detalles', [...data.detalles, emptyDetalle()]);

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
        post(apoyos.store().url);
    };

    return (
        <>
            <Head title="Nuevo apoyo" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    title="Nuevo apoyo"
                    description="Registra un apoyo económico entregado a un beneficiario."
                />

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
                                    <option value="" disabled>
                                        Selecciona un beneficiario
                                    </option>
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
                                    <option value="" disabled>
                                        Selecciona una persona
                                    </option>
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

                            <div className="grid gap-2">
                                <Label htmlFor="curp">CURP</Label>
                                <Input
                                    id="curp"
                                    value={data.curp}
                                    maxLength={18}
                                    onChange={(e) =>
                                        setData('curp', e.target.value)
                                    }
                                />
                                <InputError message={errors.curp} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="curp_archivo">
                                    CURP (archivo)
                                </Label>
                                <Input
                                    id="curp_archivo"
                                    type="file"
                                    accept="application/pdf,image/*"
                                    onChange={(e) =>
                                        setData(
                                            'curp_archivo',
                                            e.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                                <InputError message={errors.curp_archivo} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="rfc">RFC</Label>
                                <Input
                                    id="rfc"
                                    value={data.rfc}
                                    maxLength={13}
                                    onChange={(e) =>
                                        setData('rfc', e.target.value)
                                    }
                                />
                                <InputError message={errors.rfc} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="rfc_archivo">
                                    RFC (archivo)
                                </Label>
                                <Input
                                    id="rfc_archivo"
                                    type="file"
                                    accept="application/pdf,image/*"
                                    onChange={(e) =>
                                        setData(
                                            'rfc_archivo',
                                            e.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                                <InputError message={errors.rfc_archivo} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="ine">INE</Label>
                                <Input
                                    id="ine"
                                    type="file"
                                    accept="application/pdf,image/*"
                                    onChange={(e) =>
                                        setData(
                                            'ine',
                                            e.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                                <InputError message={errors.ine} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="comprobante_domicilio">
                                    Comprobante de domicilio
                                </Label>
                                <Input
                                    id="comprobante_domicilio"
                                    type="file"
                                    accept="application/pdf,image/*"
                                    onChange={(e) =>
                                        setData(
                                            'comprobante_domicilio',
                                            e.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                                <InputError
                                    message={errors.comprobante_domicilio}
                                />
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

                    <Button disabled={processing}>Guardar apoyo</Button>
                </form>
            </div>
        </>
    );
}

ApoyoCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Apoyos', href: apoyos.index() },
        { title: 'Nuevo apoyo', href: apoyos.create() },
    ],
};
