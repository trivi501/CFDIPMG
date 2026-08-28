import { Head, useForm } from '@inertiajs/react';
import { Plus, Trash2, Upload } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Combobox } from '@/components/ui/combobox';
import type { ComboboxOption } from '@/components/ui/combobox';
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
import { getXsrfToken } from '@/lib/csrf';
import { dashboard } from '@/routes';
import apoyos from '@/routes/apoyos';
import beneficiariosRoutes from '@/routes/beneficiarios';
import facturasCompra from '@/routes/facturas-compra';

type Beneficiario = { id: number; nombre: string };
type PersonaApoya = { id: number; nombre: string };

type ArticuloDisponible = {
    id: number;
    descripcion: string;
    cantidad_total: string;
    costo_unidad: string;
    iva: string;
    cantidad_disponible: string;
};

type FacturaDisponible = {
    id: number;
    emisor: string | null;
    fecha: string | null;
    articulos: ArticuloDisponible[];
};

type Detalle = {
    articulo_factura_compra_id: number | null;
    cantidad: string;
    articulo: string;
    costo_unidad: string;
    iva: string;
};

type ApoyoDetalle = {
    id: number;
    articulo_factura_compra_id: number | null;
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
    curp: string | null;
    curp_path: string | null;
    rfc: string | null;
    rfc_path: string | null;
    ine_path: string | null;
    comprobante_domicilio_path: string | null;
    detalles: ApoyoDetalle[];
};

function toDetalle(d: ApoyoDetalle): Detalle {
    return {
        articulo_factura_compra_id: d.articulo_factura_compra_id,
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
    facturasDisponibles: initialFacturasDisponibles,
}: {
    apoyo: Apoyo;
    beneficiarios: Beneficiario[];
    personasApoya: PersonaApoya[];
    facturasDisponibles: FacturaDisponible[];
}) {
    const { data, setData, put, processing, errors } = useForm({
        fecha: apoyo.fecha,
        beneficiario_id: String(apoyo.beneficiario_id),
        persona_apoya_id: String(apoyo.persona_apoya_id),
        preparado: apoyo.preparado,
        facturado: apoyo.facturado,
        solicitud_recibo: null as File | null,
        curp: apoyo.curp ?? '',
        curp_archivo: null as File | null,
        rfc: apoyo.rfc ?? '',
        rfc_archivo: null as File | null,
        ine: null as File | null,
        comprobante_domicilio: null as File | null,
        detalles: apoyo.detalles.map(toDetalle),
    });

    const [beneficiarioOptions, setBeneficiarioOptions] = useState<
        ComboboxOption[]
    >(
        beneficiarios.map((beneficiario) => ({
            value: String(beneficiario.id),
            label: beneficiario.nombre,
        })),
    );

    const createBeneficiario = async (
        nombre: string,
    ): Promise<ComboboxOption | null> => {
        const response = await fetch(beneficiariosRoutes.store().url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getXsrfToken() ?? '',
            },
            body: JSON.stringify({ nombre }),
        });

        if (!response.ok) {
            return null;
        }

        const created: { id: number; nombre: string } = await response.json();
        const option = { value: String(created.id), label: created.nombre };
        setBeneficiarioOptions((options) => [...options, option]);

        return option;
    };

    const [facturasDisponibles, setFacturasDisponibles] = useState<
        FacturaDisponible[]
    >(initialFacturasDisponibles);
    const [selectedFacturaId, setSelectedFacturaId] = useState('');
    const [selectedArticuloId, setSelectedArticuloId] = useState('');
    const [cantidadAOtorgar, setCantidadAOtorgar] = useState('1');
    const [facturaFile, setFacturaFile] = useState<File | null>(null);
    const [uploadingFactura, setUploadingFactura] = useState(false);
    const [facturaError, setFacturaError] = useState<string | null>(null);

    const selectedFactura = facturasDisponibles.find(
        (factura) => String(factura.id) === selectedFacturaId,
    );
    const articulosDeFactura = selectedFactura?.articulos ?? [];
    const selectedArticulo = articulosDeFactura.find(
        (articulo) => String(articulo.id) === selectedArticuloId,
    );

    const uploadFactura = async () => {
        if (!facturaFile) {
            return;
        }

        setUploadingFactura(true);
        setFacturaError(null);

        try {
            const formData = new FormData();
            formData.append('xml', facturaFile);

            const response = await fetch(facturasCompra.store().url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken() ?? '',
                },
                body: formData,
            });

            const result: {
                facturaCompra?: FacturaDisponible;
                message?: string;
            } = await response.json();

            if (!response.ok || !result.facturaCompra) {
                setFacturaError(result.message ?? 'No se pudo leer el XML.');

                return;
            }

            setFacturasDisponibles((current) => [
                result.facturaCompra!,
                ...current,
            ]);
            setSelectedFacturaId(String(result.facturaCompra.id));
            setSelectedArticuloId('');
            setFacturaFile(null);
        } catch {
            setFacturaError('No se pudo leer el XML.');
        } finally {
            setUploadingFactura(false);
        }
    };

    const addDetalleDesdeArticulo = () => {
        if (!selectedArticulo) {
            return;
        }

        const cantidad = Number(cantidadAOtorgar) || 0;
        const disponible = Number(selectedArticulo.cantidad_disponible);

        if (cantidad <= 0 || cantidad > disponible) {
            return;
        }

        const cantidadTotal = Number(selectedArticulo.cantidad_total) || 1;
        const ivaPorUnidad = Number(selectedArticulo.iva) / cantidadTotal;
        const iva = Math.round(ivaPorUnidad * cantidad * 100) / 100;

        setData('detalles', [
            ...data.detalles,
            {
                articulo_factura_compra_id: selectedArticulo.id,
                articulo: selectedArticulo.descripcion,
                cantidad: String(cantidad),
                costo_unidad: selectedArticulo.costo_unidad,
                iva: String(iva),
            },
        ]);

        setFacturasDisponibles((current) =>
            current.map((factura) =>
                factura.id === selectedFactura?.id
                    ? {
                          ...factura,
                          articulos: factura.articulos.map((articulo) =>
                              articulo.id === selectedArticulo.id
                                  ? {
                                        ...articulo,
                                        cantidad_disponible: String(
                                            Number(
                                                articulo.cantidad_disponible,
                                            ) - cantidad,
                                        ),
                                    }
                                  : articulo,
                          ),
                      }
                    : factura,
            ),
        );
        setSelectedArticuloId('');
        setCantidadAOtorgar('1');
    };

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
            {
                articulo_factura_compra_id: null,
                cantidad: '1',
                articulo: '',
                costo_unidad: '',
                iva: '0',
            },
        ]);

    const removeDetalle = (index: number) => {
        const detalle = data.detalles[index];

        if (detalle.articulo_factura_compra_id !== null) {
            const cantidad = Number(detalle.cantidad) || 0;
            const articuloId = detalle.articulo_factura_compra_id;

            setFacturasDisponibles((current) =>
                current.map((factura) => ({
                    ...factura,
                    articulos: factura.articulos.map((articulo) =>
                        articulo.id === articuloId
                            ? {
                                  ...articulo,
                                  cantidad_disponible: String(
                                      Number(articulo.cantidad_disponible) +
                                          cantidad,
                                  ),
                              }
                            : articulo,
                    ),
                })),
            );
        }

        setData(
            'detalles',
            data.detalles.filter((_, i) => i !== index),
        );
    };

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
                    className="w-full space-y-6"
                >
                    <Card>
                        <CardHeader>
                            <CardTitle>Datos del apoyo</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
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
                                <Combobox
                                    id="beneficiario_id"
                                    options={beneficiarioOptions}
                                    value={data.beneficiario_id}
                                    onChange={(value) =>
                                        setData('beneficiario_id', value)
                                    }
                                    onCreate={createBeneficiario}
                                    placeholder="Busca o crea un beneficiario"
                                />
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
                                {apoyo.curp_path && (
                                    <a
                                        href={`/storage/${apoyo.curp_path}`}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="text-sm text-primary underline"
                                    >
                                        Ver archivo actual
                                    </a>
                                )}
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
                                {apoyo.rfc_path && (
                                    <a
                                        href={`/storage/${apoyo.rfc_path}`}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="text-sm text-primary underline"
                                    >
                                        Ver archivo actual
                                    </a>
                                )}
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
                                {apoyo.ine_path && (
                                    <a
                                        href={`/storage/${apoyo.ine_path}`}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="text-sm text-primary underline"
                                    >
                                        Ver archivo actual
                                    </a>
                                )}
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
                                {apoyo.comprobante_domicilio_path && (
                                    <a
                                        href={`/storage/${apoyo.comprobante_domicilio_path}`}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="text-sm text-primary underline"
                                    >
                                        Ver archivo actual
                                    </a>
                                )}
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
                        <CardHeader>
                            <CardTitle>
                                Artículos de facturas de compra
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex flex-wrap items-end gap-4 rounded-lg border p-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="factura_xml">
                                        Registrar nueva factura (XML)
                                    </Label>
                                    <Input
                                        id="factura_xml"
                                        type="file"
                                        accept=".xml,text/xml,application/xml"
                                        onChange={(e) =>
                                            setFacturaFile(
                                                e.target.files?.[0] ?? null,
                                            )
                                        }
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={!facturaFile || uploadingFactura}
                                    onClick={uploadFactura}
                                >
                                    <Upload />{' '}
                                    {uploadingFactura
                                        ? 'Leyendo…'
                                        : 'Registrar'}
                                </Button>
                                {facturaError && (
                                    <p className="text-sm text-destructive">
                                        {facturaError}
                                    </p>
                                )}
                            </div>

                            <div className="flex flex-wrap items-end gap-4">
                                <div className="grid min-w-64 gap-2">
                                    <Label htmlFor="factura_disponible">
                                        Factura
                                    </Label>
                                    <NativeSelect
                                        id="factura_disponible"
                                        value={selectedFacturaId}
                                        onChange={(e) => {
                                            setSelectedFacturaId(
                                                e.target.value,
                                            );
                                            setSelectedArticuloId('');
                                        }}
                                    >
                                        <option value="">
                                            Selecciona una factura
                                        </option>
                                        {facturasDisponibles.map((factura) => (
                                            <option
                                                key={factura.id}
                                                value={factura.id}
                                            >
                                                {factura.emisor ??
                                                    'Factura de compra'}
                                                {factura.fecha
                                                    ? ` · ${factura.fecha.slice(0, 10)}`
                                                    : ''}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </div>
                                <div className="grid min-w-64 gap-2">
                                    <Label htmlFor="articulo_disponible">
                                        Artículo disponible
                                    </Label>
                                    <NativeSelect
                                        id="articulo_disponible"
                                        value={selectedArticuloId}
                                        disabled={!selectedFactura}
                                        onChange={(e) =>
                                            setSelectedArticuloId(
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <option value="">
                                            {selectedFactura
                                                ? 'Selecciona un artículo'
                                                : 'Primero selecciona una factura'}
                                        </option>
                                        {articulosDeFactura.map((articulo) => (
                                            <option
                                                key={articulo.id}
                                                value={articulo.id}
                                            >
                                                {articulo.descripcion} ·
                                                disponible{' '}
                                                {articulo.cantidad_disponible}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </div>
                                <div className="grid w-32 gap-2">
                                    <Label htmlFor="cantidad_a_otorgar">
                                        Cantidad
                                    </Label>
                                    <Input
                                        id="cantidad_a_otorgar"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        max={
                                            selectedArticulo?.cantidad_disponible
                                        }
                                        value={cantidadAOtorgar}
                                        onChange={(e) =>
                                            setCantidadAOtorgar(e.target.value)
                                        }
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={!selectedArticulo}
                                    onClick={addDetalleDesdeArticulo}
                                >
                                    <Plus /> Agregar al detalle
                                </Button>
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
                            <InputError message={errors.detalles} />

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
