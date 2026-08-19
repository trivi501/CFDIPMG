import { Form, Head } from '@inertiajs/react';
import ProductController from '@/actions/App/Http/Controllers/ProductController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

export default function ProductEdit({ product }: { product: Product }) {
    return (
        <>
            <Head title={`Editar ${product.description}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    title="Editar producto"
                    description={product.description}
                />

                <Form
                    {...ProductController.update.form(product)}
                    className="max-w-xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="description">Descripción</Label>
                                <Input
                                    id="description"
                                    name="description"
                                    required
                                    defaultValue={product.description}
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="sat_product_key">
                                        Clave SAT producto/servicio
                                    </Label>
                                    <Input
                                        id="sat_product_key"
                                        name="sat_product_key"
                                        required
                                        maxLength={8}
                                        defaultValue={product.sat_product_key}
                                    />
                                    <InputError
                                        message={errors.sat_product_key}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="sat_unit_key">
                                        Clave SAT unidad
                                    </Label>
                                    <Input
                                        id="sat_unit_key"
                                        name="sat_unit_key"
                                        required
                                        maxLength={3}
                                        defaultValue={product.sat_unit_key}
                                    />
                                    <InputError message={errors.sat_unit_key} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="unit_price">
                                    Precio unitario (sin IVA)
                                </Label>
                                <Input
                                    id="unit_price"
                                    name="unit_price"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    required
                                    defaultValue={product.unit_price}
                                />
                                <InputError message={errors.unit_price} />
                            </div>

                            <div className="flex items-center gap-2">
                                <input
                                    type="hidden"
                                    name="is_active"
                                    value="0"
                                />
                                <Checkbox
                                    id="is_active"
                                    name="is_active"
                                    value="1"
                                    defaultChecked={product.is_active}
                                />
                                <Label htmlFor="is_active">
                                    Producto activo
                                </Label>
                            </div>

                            <div className="flex gap-2">
                                <Button disabled={processing}>
                                    Guardar cambios
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

ProductEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Productos', href: products.index() },
        { title: 'Editar', href: '#' },
    ],
};
