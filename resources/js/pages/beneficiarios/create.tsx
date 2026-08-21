import { Form, Head } from '@inertiajs/react';
import BeneficiarioController from '@/actions/App/Http/Controllers/BeneficiarioController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import beneficiarios from '@/routes/beneficiarios';

export default function BeneficiarioCreate() {
    return (
        <>
            <Head title="Nuevo beneficiario" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading title="Nuevo beneficiario" />

                <Form
                    {...BeneficiarioController.store.form()}
                    className="max-w-md space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="nombre">Nombre</Label>
                                <Input
                                    id="nombre"
                                    name="nombre"
                                    required
                                    autoFocus
                                />
                                <InputError message={errors.nombre} />
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

BeneficiarioCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Beneficiarios', href: beneficiarios.index() },
        { title: 'Nuevo beneficiario', href: beneficiarios.create() },
    ],
};
