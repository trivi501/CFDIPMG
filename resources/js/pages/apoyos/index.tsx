import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDeleteButton } from '@/components/confirm-delete-button';
import Heading from '@/components/heading';
import { Pagination  } from '@/components/pagination';
import type {Paginator} from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { usePermissions } from '@/hooks/use-permissions';
import { CFDI_USES, PAYMENT_FORMS, PAYMENT_METHODS } from '@/lib/sat-catalogs';
import { dashboard } from '@/routes';
import apoyos from '@/routes/apoyos';

type Estatus = 'preparado' | 'aprobado' | 'facturado' | 'rechazado';

type Apoyo = {
    id: number;
    fecha: string;
    sub_total: string;
    iva: string;
    monto_total: string;
    estatus: Estatus;
    beneficiario: { nombre: string } | null;
    persona_apoya: { nombre: string } | null;
};

type PageProps = {
    apoyos: Paginator<Apoyo>;
    filters: { estatus?: string };
};

const currency = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});

const estatusLabels: Record<Estatus, string> = {
    preparado: 'Preparado',
    aprobado: 'Aprobado',
    facturado: 'Facturado',
    rechazado: 'Rechazado',
};

const estatusVariants: Record<
    Estatus,
    'outline' | 'secondary' | 'default' | 'destructive'
> = {
    preparado: 'outline',
    aprobado: 'secondary',
    facturado: 'default',
    rechazado: 'destructive',
};

export default function ApoyosIndex({ apoyos: paginator, filters }: PageProps) {
    const { can } = usePermissions();
    const puedeVerTodo = can('apoyos.view-all');

    const aprobar = (apoyo: Apoyo) => {
        router.patch(apoyos.aprobar(apoyo).url, {}, { preserveScroll: true });
    };

    const rechazar = (apoyo: Apoyo) => {
        if (!confirm('¿Rechazar este apoyo?')) {
            return;
        }

        router.patch(apoyos.rechazar(apoyo).url, {}, { preserveScroll: true });
    };

    const [facturarTodoOpen, setFacturarTodoOpen] = useState(false);
    const facturarTodoForm = useForm({
        payment_form: '01',
        payment_method: 'PUE',
        use: 'G03',
    });

    const facturarTodo = (e: React.FormEvent) => {
        e.preventDefault();
        facturarTodoForm.post(apoyos.facturarTodo().url, {
            preserveScroll: true,
            onSuccess: () => setFacturarTodoOpen(false),
        });
    };

    return (
        <>
            <Head title="Apoyos" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        title="Apoyos"
                        description={
                            puedeVerTodo
                                ? 'Apoyos económicos entregados a beneficiarios.'
                                : 'Apoyos económicos que has registrado.'
                        }
                    />
                    <div className="flex flex-wrap gap-2">
                        {puedeVerTodo && (
                            <Button variant="outline" asChild>
                                <a href={apoyos.descargarXmls().url}>
                                    Descargar XMLs facturados
                                </a>
                            </Button>
                        )}
                        {can('apoyos.facturar') && (
                            <Button
                                variant="outline"
                                onClick={() => setFacturarTodoOpen(true)}
                            >
                                Facturar todo
                            </Button>
                        )}
                        {can('apoyos.manage') && (
                            <Button asChild>
                                <Link href={apoyos.create()}>
                                    <Plus /> Nuevo apoyo
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <div className="flex gap-1">
                    {[
                        { label: 'Todos', value: undefined },
                        { label: 'Preparado', value: 'preparado' },
                        { label: 'Aprobado', value: 'aprobado' },
                        { label: 'Facturado', value: 'facturado' },
                        { label: 'Rechazado', value: 'rechazado' },
                    ].map((option) => (
                        <Button
                            key={option.label}
                            variant={
                                filters.estatus === option.value
                                    ? 'default'
                                    : 'outline'
                            }
                            size="sm"
                            onClick={() =>
                                router.get(
                                    apoyos.index().url,
                                    option.value
                                        ? { estatus: option.value }
                                        : {},
                                    { preserveState: true },
                                )
                            }
                        >
                            {option.label}
                        </Button>
                    ))}
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Fecha</TableHead>
                                <TableHead>Beneficiario</TableHead>
                                <TableHead>Persona Apoya</TableHead>
                                <TableHead className="text-right">
                                    Sub Total
                                </TableHead>
                                <TableHead className="text-right">
                                    IVA
                                </TableHead>
                                <TableHead className="text-right">
                                    Monto Total
                                </TableHead>
                                <TableHead>Estatus</TableHead>
                                <TableHead className="text-right">
                                    Acciones
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {paginator.data.length === 0 && (
                                <TableEmpty colSpan={8}>
                                    No hay apoyos registrados.
                                </TableEmpty>
                            )}
                            {paginator.data.map((apoyo) => (
                                <TableRow key={apoyo.id}>
                                    <TableCell>{apoyo.fecha}</TableCell>
                                    <TableCell className="font-medium">
                                        {apoyo.beneficiario?.nombre ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        {apoyo.persona_apoya?.nombre ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {currency.format(
                                            Number(apoyo.sub_total),
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {currency.format(Number(apoyo.iva))}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {currency.format(
                                            Number(apoyo.monto_total),
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                estatusVariants[apoyo.estatus]
                                            }
                                        >
                                            {estatusLabels[apoyo.estatus]}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={apoyos.show(apoyo)}
                                                >
                                                    Ver
                                                </Link>
                                            </Button>
                                            {apoyo.estatus === 'preparado' &&
                                                can('apoyos.approve') && (
                                                    <>
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() =>
                                                                aprobar(apoyo)
                                                            }
                                                        >
                                                            Aprobado
                                                        </Button>
                                                        <Button
                                                            variant="destructive"
                                                            size="sm"
                                                            onClick={() =>
                                                                rechazar(apoyo)
                                                            }
                                                        >
                                                            Rechazado
                                                        </Button>
                                                    </>
                                                )}
                                            {can('apoyos.manage') && (
                                                <>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={apoyos.edit(
                                                                apoyo,
                                                            )}
                                                        >
                                                            Editar
                                                        </Link>
                                                    </Button>
                                                    <ConfirmDeleteButton
                                                        href={
                                                            apoyos.destroy(
                                                                apoyo,
                                                            ).url
                                                        }
                                                        confirmMessage={`¿Eliminar el apoyo de "${apoyo.beneficiario?.nombre}"?`}
                                                    />
                                                </>
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <Pagination paginator={paginator} />
            </div>

            <Dialog open={facturarTodoOpen} onOpenChange={setFacturarTodoOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Facturar todos los aprobados</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={facturarTodo} className="grid gap-4">
                        <p className="text-sm text-muted-foreground">
                            Se generará una factura real para cada apoyo
                            aprobado visible, usando el RFC capturado en cada
                            uno (o "Público en general" si no tiene) con
                            estos mismos datos fiscales.
                        </p>
                        <div className="grid gap-2">
                            <Label htmlFor="bulk_use">Uso CFDI</Label>
                            <NativeSelect
                                id="bulk_use"
                                value={facturarTodoForm.data.use}
                                onChange={(e) =>
                                    facturarTodoForm.setData(
                                        'use',
                                        e.target.value,
                                    )
                                }
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
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="bulk_payment_form">
                                Forma de pago
                            </Label>
                            <NativeSelect
                                id="bulk_payment_form"
                                value={facturarTodoForm.data.payment_form}
                                onChange={(e) =>
                                    facturarTodoForm.setData(
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
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="bulk_payment_method">
                                Método de pago
                            </Label>
                            <NativeSelect
                                id="bulk_payment_method"
                                value={facturarTodoForm.data.payment_method}
                                onChange={(e) =>
                                    facturarTodoForm.setData(
                                        'payment_method',
                                        e.target.value,
                                    )
                                }
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
                        <DialogFooter>
                            <Button
                                type="submit"
                                disabled={facturarTodoForm.processing}
                            >
                                Facturar todo
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

ApoyosIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Apoyos', href: apoyos.index() },
    ],
};
