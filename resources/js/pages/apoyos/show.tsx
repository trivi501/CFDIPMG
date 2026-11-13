import { Head, Link, router, useForm } from '@inertiajs/react';
import { EstatusBadge } from '@/components/estatus-badge';
import Heading from '@/components/heading';
import { ImpuestosFields } from '@/components/impuestos-fields';
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
import { usePermissions } from '@/hooks/use-permissions';
import { fechaMx } from '@/lib/fechas';
import {
    PAYMENT_FORMS,
    pickUseForRegime,
    TAX_SYSTEMS,
    usesForRegime,
} from '@/lib/sat-catalogs';
import { dashboard } from '@/routes';
import apoyos from '@/routes/apoyos';
import invoices from '@/routes/invoices';

type Estatus = 'preparado' | 'aprobado' | 'facturado' | 'rechazado';

type ApoyoDetalle = {
    id: number;
    articulo: string;
    cantidad: string;
    costo_unidad: string;
    iva: string;
    sub_total: string;
    total: string;
};

type Invoice = {
    id: number;
    uuid: string | null;
    series: string | null;
    folio: string | null;
    status: 'valid' | 'canceled' | 'failed';
    total: string;
};

type Apoyo = {
    id: number;
    fecha: string;
    estatus: Estatus;
    sub_total: string;
    iva: string;
    monto_total: string;
    solicitud_recibo_path: string | null;
    curp: string | null;
    curp_path: string | null;
    rfc: string | null;
    rfc_path: string | null;
    ine_path: string | null;
    comprobante_domicilio_path: string | null;
    beneficiario: { nombre: string } | null;
    persona_apoya: { nombre: string } | null;
    user: { name: string } | null;
    area: { nombre: string } | null;
    invoice: Invoice | null;
    detalles: ApoyoDetalle[];
};

const currency = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});

function isImagen(path: string): boolean {
    return /\.(jpe?g|png|gif|webp)$/i.test(path);
}

function DocumentoViewer({
    label,
    path,
    url,
}: {
    label: string;
    path: string | null;
    url: string;
}) {
    if (!path) {
        return (
            <div className="grid gap-2">
                <p className="text-sm font-medium">{label}</p>
                <p className="text-sm text-muted-foreground">
                    No se subió este documento.
                </p>
            </div>
        );
    }

    return (
        <div className="grid gap-2">
            <div className="flex items-center justify-between">
                <p className="text-sm font-medium">{label}</p>
                <a
                    href={url}
                    target="_blank"
                    rel="noreferrer"
                    className="text-sm text-primary underline"
                >
                    Abrir en pestaña nueva
                </a>
            </div>
            {isImagen(path) ? (
                <img
                    src={url}
                    alt={label}
                    className="max-h-[500px] w-full rounded-lg border object-contain"
                />
            ) : (
                <iframe
                    src={url}
                    title={label}
                    className="h-[500px] w-full rounded-lg border"
                />
            )}
        </div>
    );
}

export default function ApoyoShow({ apoyo }: { apoyo: Apoyo }) {
    const { can } = usePermissions();
    const documentoUrl = (tipo: string) =>
        apoyos.documento({ apoyo: apoyo.id, tipo }).url;

    const aprobar = () => {
        router.patch(apoyos.aprobar(apoyo).url, {}, { preserveScroll: true });
    };

    const rechazar = () => {
        if (!confirm('¿Rechazar este apoyo?')) {
            return;
        }

        router.patch(apoyos.rechazar(apoyo).url, {}, { preserveScroll: true });
    };

    const facturarForm = useForm({
        rfc: apoyo.rfc || 'XAXX010101000',
        legal_name: apoyo.beneficiario?.nombre ?? 'PUBLICO EN GENERAL',
        tax_system: '616',
        zip: '01000',
        email: '',
        payment_form: '01',
        use: pickUseForRegime('G03', '616'),
        iva: false,
        isr_rate: '',
    });

    const facturar = (e: React.FormEvent) => {
        e.preventDefault();
        facturarForm.patch(apoyos.facturar(apoyo).url, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title={`Apoyo de ${apoyo.beneficiario?.nombre ?? ''}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        title={`Apoyo de ${apoyo.beneficiario?.nombre ?? '—'}`}
                        description={`Registrado por ${apoyo.user?.name ?? '—'}${apoyo.area ? ` (${apoyo.area.nombre})` : ''} el ${fechaMx(apoyo.fecha)}`}
                    />
                    <div className="flex flex-wrap gap-2">
                        {apoyo.estatus === 'preparado' &&
                            can('apoyos.approve') && (
                                <>
                                    <Button onClick={aprobar}>Aprobado</Button>
                                    <Button
                                        variant="destructive"
                                        onClick={rechazar}
                                    >
                                        Rechazado
                                    </Button>
                                </>
                            )}
                        {can('apoyos.manage') && (
                            <Button variant="outline" asChild>
                                <Link href={apoyos.edit(apoyo)}>Editar</Link>
                            </Button>
                        )}
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Datos generales</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <p className="text-sm text-muted-foreground">
                                Estatus
                            </p>
                            <EstatusBadge
                                tipo="apoyo"
                                estatus={apoyo.estatus}
                            />
                        </div>
                        <div>
                            <p className="text-sm text-muted-foreground">
                                Persona Apoya
                            </p>
                            <p className="font-medium">
                                {apoyo.persona_apoya?.nombre ?? '—'}
                            </p>
                        </div>
                        <div>
                            <p className="text-sm text-muted-foreground">
                                CURP
                            </p>
                            <p className="font-medium">{apoyo.curp || '—'}</p>
                        </div>
                        <div>
                            <p className="text-sm text-muted-foreground">RFC</p>
                            <p className="font-medium">{apoyo.rfc || '—'}</p>
                        </div>
                        <div>
                            <p className="text-sm text-muted-foreground">
                                Sub Total
                            </p>
                            <p className="font-medium">
                                {currency.format(Number(apoyo.sub_total))}
                            </p>
                        </div>
                        <div>
                            <p className="text-sm text-muted-foreground">IVA</p>
                            <p className="font-medium">
                                {currency.format(Number(apoyo.iva))}
                            </p>
                        </div>
                        <div>
                            <p className="text-sm text-muted-foreground">
                                Monto Total
                            </p>
                            <p className="font-medium">
                                {currency.format(Number(apoyo.monto_total))}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Detalle Apoyos</CardTitle>
                    </CardHeader>
                    <CardContent>
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
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {apoyo.detalles.map((detalle) => (
                                        <TableRow key={detalle.id}>
                                            <TableCell>
                                                {detalle.articulo}
                                            </TableCell>
                                            <TableCell>
                                                {detalle.cantidad}
                                            </TableCell>
                                            <TableCell>
                                                {currency.format(
                                                    Number(
                                                        detalle.costo_unidad,
                                                    ),
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {currency.format(
                                                    Number(detalle.iva),
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {currency.format(
                                                    Number(detalle.sub_total),
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {currency.format(
                                                    Number(detalle.total),
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Documentos</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-6 lg:grid-cols-2">
                        <DocumentoViewer
                            label="Solicitud Recibo"
                            path={apoyo.solicitud_recibo_path}
                            url={documentoUrl('solicitud_recibo')}
                        />
                        <DocumentoViewer
                            label="CURP (archivo)"
                            path={apoyo.curp_path}
                            url={documentoUrl('curp')}
                        />
                        <DocumentoViewer
                            label="RFC (archivo)"
                            path={apoyo.rfc_path}
                            url={documentoUrl('rfc')}
                        />
                        <DocumentoViewer
                            label="INE"
                            path={apoyo.ine_path}
                            url={documentoUrl('ine')}
                        />
                        <DocumentoViewer
                            label="Comprobante de domicilio"
                            path={apoyo.comprobante_domicilio_path}
                            url={documentoUrl('comprobante_domicilio')}
                        />
                    </CardContent>
                </Card>

                {apoyo.invoice && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Factura</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    Folio fiscal (UUID)
                                </p>
                                <p className="font-medium break-all">
                                    {apoyo.invoice.uuid ?? '—'}
                                </p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    Total
                                </p>
                                <p className="font-medium">
                                    {currency.format(
                                        Number(apoyo.invoice.total),
                                    )}
                                </p>
                            </div>
                            <div className="flex items-end gap-2">
                                {can('invoices.view') && (
                                    <Button variant="outline" asChild>
                                        <Link
                                            href={invoices.show(apoyo.invoice)}
                                        >
                                            Ver factura
                                        </Link>
                                    </Button>
                                )}
                                {apoyo.invoice.uuid && (
                                    <>
                                        <Button variant="outline" asChild>
                                            <a
                                                href={
                                                    apoyos.factura({
                                                        apoyo: apoyo.id,
                                                        formato: 'pdf',
                                                    }).url
                                                }
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                PDF
                                            </a>
                                        </Button>
                                        <Button variant="outline" asChild>
                                            <a
                                                href={
                                                    apoyos.factura({
                                                        apoyo: apoyo.id,
                                                        formato: 'xml',
                                                    }).url
                                                }
                                            >
                                                XML
                                            </a>
                                        </Button>
                                    </>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {apoyo.estatus === 'aprobado' && can('apoyos.facturar') && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Facturar</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={facturar}
                                className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="rfc">RFC</Label>
                                    <Input
                                        id="rfc"
                                        value={facturarForm.data.rfc}
                                        onChange={(e) =>
                                            facturarForm.setData(
                                                'rfc',
                                                e.target.value.toUpperCase(),
                                            )
                                        }
                                        maxLength={13}
                                        required
                                    />
                                    <InputError
                                        message={facturarForm.errors.rfc}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="legal_name">
                                        Razón social
                                    </Label>
                                    <Input
                                        id="legal_name"
                                        value={facturarForm.data.legal_name}
                                        onChange={(e) =>
                                            facturarForm.setData(
                                                'legal_name',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError
                                        message={facturarForm.errors.legal_name}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="tax_system">
                                        Régimen fiscal
                                    </Label>
                                    <NativeSelect
                                        id="tax_system"
                                        value={facturarForm.data.tax_system}
                                        onChange={(e) =>
                                            facturarForm.setData({
                                                ...facturarForm.data,
                                                tax_system: e.target.value,
                                                use: pickUseForRegime(
                                                    'G03',
                                                    e.target.value,
                                                ),
                                            })
                                        }
                                    >
                                        {TAX_SYSTEMS.map((option) => (
                                            <option
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                    <InputError
                                        message={facturarForm.errors.tax_system}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="zip">Código postal</Label>
                                    <Input
                                        id="zip"
                                        value={facturarForm.data.zip}
                                        onChange={(e) =>
                                            facturarForm.setData(
                                                'zip',
                                                e.target.value,
                                            )
                                        }
                                        maxLength={5}
                                        required
                                    />
                                    <InputError
                                        message={facturarForm.errors.zip}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="email">
                                        Correo (opcional)
                                    </Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={facturarForm.data.email}
                                        onChange={(e) =>
                                            facturarForm.setData(
                                                'email',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={facturarForm.errors.email}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="use">Uso CFDI</Label>
                                    <NativeSelect
                                        id="use"
                                        value={facturarForm.data.use}
                                        onChange={(e) =>
                                            facturarForm.setData(
                                                'use',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        {usesForRegime(
                                            facturarForm.data.tax_system,
                                        ).map((option) => (
                                            <option
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                    <InputError
                                        message={facturarForm.errors.use}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="payment_form">
                                        Forma de pago
                                    </Label>
                                    <NativeSelect
                                        id="payment_form"
                                        value={facturarForm.data.payment_form}
                                        onChange={(e) =>
                                            facturarForm.setData(
                                                'payment_form',
                                                e.target.value,
                                            )
                                        }
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
                                    <InputError
                                        message={
                                            facturarForm.errors.payment_form
                                        }
                                    />
                                </div>
                                <p className="text-sm text-muted-foreground sm:col-span-2 lg:col-span-4">
                                    Comprobante de Egreso, método de pago PUE,
                                    clave de producto 93161700 y unidad C62.
                                </p>
                                <div className="sm:col-span-2 lg:col-span-4">
                                    <ImpuestosFields
                                        data={facturarForm.data}
                                        onChange={(impuestos) =>
                                            facturarForm.setData({
                                                ...facturarForm.data,
                                                ...impuestos,
                                            })
                                        }
                                        errors={facturarForm.errors}
                                    />
                                </div>
                                <div className="sm:col-span-2 lg:col-span-4">
                                    <Button
                                        type="submit"
                                        disabled={facturarForm.processing}
                                    >
                                        Generar factura
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

ApoyoShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Apoyos', href: apoyos.index() },
        { title: 'Ver apoyo', href: '#' },
    ],
};
