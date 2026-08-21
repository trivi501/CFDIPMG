import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ConfirmDeleteButton } from '@/components/confirm-delete-button';
import Heading from '@/components/heading';
import { Pagination  } from '@/components/pagination';
import type {Paginator} from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
import personasApoya from '@/routes/personas-apoya';

type PersonaApoya = {
    id: number;
    nombre: string;
};

type PageProps = {
    personasApoya: Paginator<PersonaApoya>;
    filters: { search?: string };
};

export default function PersonasApoyaIndex({
    personasApoya: paginator,
    filters,
}: PageProps) {
    const { can } = usePermissions();
    const [search, setSearch] = useState(filters.search ?? '');

    useEffect(() => {
        const timeout = setTimeout(() => {
            if (search !== (filters.search ?? '')) {
                router.get(
                    personasApoya.index().url,
                    { search: search || undefined },
                    { preserveState: true, replace: true },
                );
            }
        }, 300);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    return (
        <>
            <Head title="Personas de apoyo" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Personas de apoyo"
                        description="Personal que gestiona/autoriza los apoyos."
                    />
                    {can('apoyos.manage') && (
                        <Button asChild>
                            <Link href={personasApoya.create()}>
                                <Plus /> Nueva persona
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="relative max-w-sm">
                    <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                    <Input
                        placeholder="Buscar por nombre…"
                        className="pl-8"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nombre</TableHead>
                                <TableHead className="text-right">
                                    Acciones
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {paginator.data.length === 0 && (
                                <TableEmpty colSpan={2}>
                                    No hay personas registradas.
                                </TableEmpty>
                            )}
                            {paginator.data.map((persona) => (
                                <TableRow key={persona.id}>
                                    <TableCell className="font-medium">
                                        {persona.nombre}
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
                                                        href={personasApoya.edit(
                                                            persona.id,
                                                        )}
                                                    >
                                                        Editar
                                                    </Link>
                                                </Button>
                                                <ConfirmDeleteButton
                                                    href={
                                                        personasApoya.destroy(
                                                            persona.id,
                                                        ).url
                                                    }
                                                    confirmMessage={`¿Eliminar a ${persona.nombre}?`}
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

PersonasApoyaIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Personas de apoyo', href: personasApoya.index() },
    ],
};
