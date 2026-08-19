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
import { dashboard } from '@/routes';
import roles from '@/routes/roles';

type Role = {
    id: number;
    name: string;
    permissions_count: number;
    users_count: number;
};

export default function RolesIndex({ roles: roleList }: { roles: Role[] }) {
    return (
        <>
            <Head title="Roles y permisos" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Roles y permisos"
                        description="Define qué puede hacer cada tipo de usuario."
                    />
                    <Button asChild>
                        <Link href={roles.create()}>
                            <Plus /> Nuevo rol
                        </Link>
                    </Button>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Rol</TableHead>
                                <TableHead>Permisos</TableHead>
                                <TableHead>Usuarios</TableHead>
                                <TableHead className="text-right">
                                    Acciones
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {roleList.length === 0 && (
                                <TableEmpty colSpan={4}>
                                    No hay roles definidos.
                                </TableEmpty>
                            )}
                            {roleList.map((role) => (
                                <TableRow key={role.id}>
                                    <TableCell className="font-medium">
                                        {role.name}
                                    </TableCell>
                                    <TableCell>
                                        {role.permissions_count}
                                    </TableCell>
                                    <TableCell>{role.users_count}</TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                asChild
                                            >
                                                <Link href={roles.edit(role)}>
                                                    Editar
                                                </Link>
                                            </Button>
                                            <ConfirmDeleteButton
                                                href={roles.destroy(role).url}
                                                confirmMessage={`¿Eliminar el rol "${role.name}"?`}
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

RolesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Roles y permisos', href: roles.index() },
    ],
};
