import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { ConfirmDeleteButton } from '@/components/confirm-delete-button';
import Heading from '@/components/heading';
import { Pagination  } from '@/components/pagination';
import type {Paginator} from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import apoyos from '@/routes/apoyos';

type Apoyo = {
    id: number;
    fecha: string;
    sub_total: string;
    iva: string;
    monto_total: string;
    preparado: boolean;
    facturado: boolean;
    beneficiario: { nombre: string } | null;
    persona_apoya: { nombre: string } | null;
};

type PageProps = {
    apoyos: Paginator<Apoyo>;
    filters: { facturado?: string };
};

const currency = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});

export default function ApoyosIndex({ apoyos: paginator, filters }: PageProps) {
    const { can } = usePermissions();

    return (
        <>
            <Head title="Apoyos" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Apoyos"
                        description="Apoyos económicos entregados a beneficiarios."
                    />
                    {can('apoyos.manage') && (
                        <Button asChild>
                            <Link href={apoyos.create()}>
                                <Plus /> Nuevo apoyo
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="flex gap-1">
                    {[
                        { label: 'Todos', value: undefined },
                        { label: 'Sin facturar', value: '0' },
                        { label: 'Facturado', value: '1' },
                    ].map((option) => (
                        <Button
                            key={option.label}
                            variant={
                                filters.facturado === option.value
                                    ? 'default'
                                    : 'outline'
                            }
                            size="sm"
                            onClick={() =>
                                router.get(
                                    apoyos.index().url,
                                    option.value
                                        ? { facturado: option.value }
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
                                <TableHead>Preparado</TableHead>
                                <TableHead>Facturado</TableHead>
                                <TableHead className="text-right">
                                    Acciones
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {paginator.data.length === 0 && (
                                <TableEmpty colSpan={9}>
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
                                                apoyo.preparado
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                        >
                                            {apoyo.preparado ? 'Sí' : 'No'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                apoyo.facturado
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                        >
                                            {apoyo.facturado ? 'Sí' : 'No'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {can('apoyos.manage') && (
                                            <div className="flex justify-end gap-1">
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
                                                        apoyos.destroy(apoyo)
                                                            .url
                                                    }
                                                    confirmMessage={`¿Eliminar el apoyo de "${apoyo.beneficiario?.nombre}"?`}
                                                />
                                            </div>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <Pagination paginator={paginator} />
            </div>
        </>
    );
}

ApoyosIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Apoyos', href: apoyos.index() },
    ],
};
