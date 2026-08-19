import { Form, Head } from '@inertiajs/react';
import ProductController from '@/actions/App/Http/Controllers/ProductController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import products from '@/routes/products';

export default function ProductCreate() {
    return (
        <>
            <Head title="Nuevo producto" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    title="Nuevo producto"
                    description="Concepto reutilizable para líneas de factura."
                />

                <Form
                    {...ProductController.store.form()}
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
                                        placeholder="84111506"
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
                                        placeholder="E48"
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
                                />
                                <InputError message={errors.unit_price} />
                            </div>

                            <div className="flex gap-2">
                                <Button disabled={processing}>Guardar</Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

ProductCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Productos', href: products.index() },
        { title: 'Nuevo producto', href: products.create() },
    ],
};
