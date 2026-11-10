import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { ConfirmDeleteButton } from '@/components/confirm-delete-button';
import Heading from '@/components/heading';
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
import type { Paquete } from '@/lib/paquetes';
import { dashboard } from '@/routes';
import paquetesRoutes from '@/routes/paquetes';

type PaqueteConFactura = Paquete & {
    factura_compra: { emisor: string | null; fecha: string | null } | null;
    disponibles: number;
};

export default function PaquetesIndex({
    paquetes,
}: {
    paquetes: PaqueteConFactura[];
}) {
    const { can } = usePermissions();

    return (
        <>
            <Head title="Paquetes" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Paquetes"
                        description="Combinaciones de artículos para agregar a un apoyo de una sola vez."
                    />
                    {can('apoyos.manage') && (
                        <Button asChild>
                            <Link href={paquetesRoutes.create()}>
                                <Plus /> Nuevo paquete
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nombre</TableHead>
                                <TableHead>Factura</TableHead>
                                <TableHead>Contenido</TableHead>
                                <TableHead className="text-right">
                                    Disponibles
                                </TableHead>
                                <TableHead className="text-right">
                                    Acciones
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {paquetes.length === 0 && (
                                <TableEmpty colSpan={5}>
                                    No hay paquetes registrados.
                                </TableEmpty>
                            )}
                            {paquetes.map((paquete) => (
                                <TableRow key={paquete.id}>
                                    <TableCell className="font-medium">
                                        {paquete.nombre}
                                    </TableCell>
                                    <TableCell>
                                        {paquete.factura_compra?.emisor ?? '—'}
                                        {paquete.factura_compra?.fecha &&
                                            ` · ${paquete.factura_compra.fecha.slice(0, 10)}`}
                                    </TableCell>
                                    <TableCell className="whitespace-normal">
                                        {paquete.articulos
                                            .map(
                                                (a) =>
                                                    `${Number(a.cantidad)} × ${a.descripcion}`,
                                            )
                                            .join(', ')}
                                    </TableCell>
                                    <TableCell
                                        className={`text-right font-medium tabular-nums ${paquete.disponibles === 0 ? 'text-destructive' : ''}`}
                                    >
                                        {paquete.disponibles}
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
                                                        href={paquetesRoutes.edit(
                                                            paquete,
                                                        )}
                                                    >
                                                        Editar
                                                    </Link>
                                                </Button>
                                                <ConfirmDeleteButton
                                                    href={
                                                        paquetesRoutes.destroy(
                                                            paquete,
                                                        ).url
                                                    }
                                                    confirmMessage={`¿Eliminar el paquete "${paquete.nombre}"?`}
                                                />
                                            </div>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}

PaquetesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Paquetes', href: paquetesRoutes.index() },
    ],
};
