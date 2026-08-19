import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ConfirmDeleteButton } from '@/components/confirm-delete-button';
import Heading from '@/components/heading';
import { Pagination } from '@/components/pagination';
import type { Paginator } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
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
import products from '@/routes/products';

type Product = {
    id: number;
    description: string;
    sat_product_key: string;
    sat_unit_key: string;
    unit_price: string;
    is_active: boolean;
};

type PageProps = {
    products: Paginator<Product>;
    filters: { search?: string };
};

const currency = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});

export default function ProductsIndex({
    products: paginator,
    filters,
}: PageProps) {
    const { can } = usePermissions();
    const [search, setSearch] = useState(filters.search ?? '');

    useEffect(() => {
        const timeout = setTimeout(() => {
            if (search !== (filters.search ?? '')) {
                router.get(
                    products.index().url,
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
            <Head title="Productos" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Productos"
                        description="Catálogo de conceptos reutilizables para las facturas."
                    />
                    {can('products.manage') && (
                        <Button asChild>
                            <Link href={products.create()}>
                                <Plus /> Nuevo producto
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="relative max-w-sm">
                    <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                    <Input
                        placeholder="Buscar…"
                        className="pl-8"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Descripción</TableHead>
                                <TableHead>Clave SAT prod/serv</TableHead>
                                <TableHead>Clave SAT unidad</TableHead>
                                <TableHead>Precio</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead className="text-right">
                                    Acciones
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {paginator.data.length === 0 && (
                                <TableEmpty colSpan={6}>
                                    No hay productos registrados.
                                </TableEmpty>
                            )}
                            {paginator.data.map((product) => (
                                <TableRow key={product.id}>
                                    <TableCell className="font-medium">
                                        {product.description}
                                    </TableCell>
                                    <TableCell>
                                        {product.sat_product_key}
                                    </TableCell>
                                    <TableCell>
                                        {product.sat_unit_key}
                                    </TableCell>
                                    <TableCell>
                                        {currency.format(
                                            Number(product.unit_price),
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                product.is_active
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {product.is_active
                                                ? 'Activo'
                                                : 'Inactivo'}
                                        </Badge>
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
                                                        href={products.edit(
                                                            product,
                                                        )}
                                                    >
                                                        Editar
                                                    </Link>
                                                </Button>
                                                <ConfirmDeleteButton
                                                    href={
                                                        products.destroy(
                                                            product,
                                                        ).url
                                                    }
                                                    confirmMessage={`¿Eliminar "${product.description}"?`}
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

ProductsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Productos', href: products.index() },
    ],
};
