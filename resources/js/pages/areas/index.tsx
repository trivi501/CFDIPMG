import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { ConfirmDeleteButton } from '@/components/confirm-delete-button';
import Heading from '@/components/heading';
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
import { TIPOS_AREA } from '@/lib/areas';
import { dashboard } from '@/routes';
import areasRoutes from '@/routes/areas';

type Area = {
    id: number;
    nombre: string;
    tipo: keyof typeof TIPOS_AREA;
    users_count: number;
};

export default function AreasIndex({ areas }: { areas: Area[] }) {
    return (
        <>
            <Head title="Áreas" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Áreas"
                        description="Secretarías y regidurías. Los usuarios de una misma área ven los apoyos de esa área."
                    />
                    <Button asChild>
                        <Link href={areasRoutes.create()}>
                            <Plus /> Nueva área
                        </Link>
                    </Button>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nombre</TableHead>
                                <TableHead>Tipo</TableHead>
                                <TableHead>Usuarios</TableHead>
                                <TableHead className="text-right">
                                    Acciones
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {areas.length === 0 && (
                                <TableEmpty colSpan={4}>
                                    No hay áreas registradas.
                                </TableEmpty>
                            )}
                            {areas.map((area) => (
                                <TableRow key={area.id}>
                                    <TableCell className="font-medium">
                                        {area.nombre}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="secondary">
                                            {TIPOS_AREA[area.tipo]}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>{area.users_count}</TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={areasRoutes.edit(
                                                        area,
                                                    )}
                                                >
                                                    Editar
                                                </Link>
                                            </Button>
                                            <ConfirmDeleteButton
                                                href={
                                                    areasRoutes.destroy(area)
                                                        .url
                                                }
                                                confirmMessage={`¿Eliminar "${area.nombre}"? Sus usuarios quedarán sin área y sus apoyos solo los verá quien los registró y el Admin.`}
                                            />
                                        </div>
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

AreasIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Áreas', href: areasRoutes.index() },
    ],
};
