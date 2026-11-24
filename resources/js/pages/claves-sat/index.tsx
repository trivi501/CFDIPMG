import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ConfirmDeleteButton } from '@/components/confirm-delete-button';
import Heading from '@/components/heading';
import { Pagination } from '@/components/pagination';
import type { Paginator } from '@/components/pagination';
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
import type { ClaveSat } from '@/lib/claves-sat';
import { dashboard } from '@/routes';
import clavesSat from '@/routes/claves-sat';

type PageProps = {
    claves: Paginator<ClaveSat>;
    filters: { search?: string };
};

export default function ClavesSatIndex({
    claves: paginator,
    filters,
}: PageProps) {
    const { can } = usePermissions();
    const [search, setSearch] = useState(filters.search ?? '');

    useEffect(() => {
        const timeout = setTimeout(() => {
            if (search !== (filters.search ?? '')) {
                router.get(
                    clavesSat.index().url,
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
            <Head title="Claves SAT" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        title="Claves SAT"
                        description="Clave de producto o servicio con su unidad y concepto. Al escribir la clave en una factura, se llenan solos."
                    />
                    {can('products.manage') && (
                        <Button asChild>
                            <Link href={clavesSat.create()}>
                                <Plus /> Nueva clave
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="relative max-w-sm">
                    <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                    <Input
                        placeholder="Buscar por clave, unidad o concepto…"
                        className="pl-8"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Clave producto/servicio</TableHead>
                                <TableHead>Unidad</TableHead>
                                <TableHead>Concepto</TableHead>
                                <TableHead className="text-right">
                                    Acciones
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {paginator.data.length === 0 && (
                                <TableEmpty colSpan={4}>
                                    {filters.search
                                        ? 'Ninguna clave coincide con la búsqueda.'
                                        : 'No hay claves registradas.'}
                                </TableEmpty>
                            )}
                            {paginator.data.map((clave) => (
                                <TableRow key={clave.id}>
                                    <TableCell className="font-mono">
                                        {clave.clave}
                                    </TableCell>
                                    <TableCell className="font-mono">
                                        {clave.unidad}
                                    </TableCell>
                                    <TableCell className="font-medium">
                                        {clave.concepto}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {can('products.manage') && (
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={clavesSat.edit(
                                                            clave,
                                                        )}
                                                    >
                                                        Editar
                                                    </Link>
                                                </Button>
                                                <ConfirmDeleteButton
                                                    href={
                                                        clavesSat.destroy(clave)
                                                            .url
                                                    }
                                                    confirmMessage={`¿Eliminar la clave ${clave.clave} (${clave.concepto})?`}
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

ClavesSatIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Claves SAT', href: clavesSat.index() },
    ],
};
