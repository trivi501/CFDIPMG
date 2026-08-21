import { Form, Head } from '@inertiajs/react';
import BeneficiarioController from '@/actions/App/Http/Controllers/BeneficiarioController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import beneficiarios from '@/routes/beneficiarios';

type Beneficiario = { id: number; nombre: string };

export default function BeneficiarioEdit({
    beneficiario,
}: {
    beneficiario: Beneficiario;
}) {
    return (
        <>
            <Head title={`Editar ${beneficiario.nombre}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    title="Editar beneficiario"
                    description={beneficiario.nombre}
                />

                <Form
                    {...BeneficiarioController.update.form(beneficiario)}
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
                                    defaultValue={beneficiario.nombre}
                                    autoFocus
                                />
                                <InputError message={errors.nombre} />
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

BeneficiarioEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Beneficiarios', href: beneficiarios.index() },
        { title: 'Editar', href: '#' },
    ],
};
