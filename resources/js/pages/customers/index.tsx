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
import { dashboard } from '@/routes';
import customers from '@/routes/customers';

type Customer = {
    id: number;
    legal_name: string;
    rfc: string;
    tax_system: string;
    email: string | null;
    zip: string;
};

type PageProps = {
    customers: Paginator<Customer>;
    filters: { search?: string };
};

export default function CustomersIndex({
    customers: paginator,
    filters,
}: PageProps) {
    const { can } = usePermissions();
    const [search, setSearch] = useState(filters.search ?? '');

    useEffect(() => {
        const timeout = setTimeout(() => {
            if (search !== (filters.search ?? '')) {
                router.get(
                    customers.index().url,
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
            <Head title="Clientes" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Clientes"
                        description="Datos fiscales usados para timbrar facturas."
                    />
                    {can('customers.manage') && (
                        <Button asChild>
                            <Link href={customers.create()}>
                                <Plus /> Nuevo cliente
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="relative max-w-sm">
                    <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                    <Input
                        placeholder="Buscar por razón social o RFC…"
                        className="pl-8"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Razón social</TableHead>
                                <TableHead>RFC</TableHead>
                                <TableHead>Régimen fiscal</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>CP</TableHead>
                                <TableHead className="text-right">
                                    Acciones
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {paginator.data.length === 0 && (
                                <TableEmpty colSpan={6}>
                                    No hay clientes registrados.
                                </TableEmpty>
                            )}
                            {paginator.data.map((customer) => (
                                <TableRow key={customer.id}>
                                    <TableCell className="font-medium">
                                        {customer.legal_name}
                                    </TableCell>
                                    <TableCell>{customer.rfc}</TableCell>
                                    <TableCell>{customer.tax_system}</TableCell>
                                    <TableCell>
                                        {customer.email ?? '—'}
                                    </TableCell>
                                    <TableCell>{customer.zip}</TableCell>
                                    <TableCell className="text-right">
                                        {can('customers.manage') && (
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={customers.edit(
                                                            customer,
                                                        )}
                                                    >
                                                        Editar
                                                    </Link>
                                                </Button>
                                                <ConfirmDeleteButton
                                                    href={
                                                        customers.destroy(
                                                            customer,
                                                        ).url
                                                    }
                                                    confirmMessage={`¿Eliminar a ${customer.legal_name}?`}
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

CustomersIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Clientes', href: customers.index() },
    ],
};
