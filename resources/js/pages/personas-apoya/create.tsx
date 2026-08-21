import { Form, Head } from '@inertiajs/react';
import PersonaApoyaController from '@/actions/App/Http/Controllers/PersonaApoyaController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import personasApoya from '@/routes/personas-apoya';

export default function PersonaApoyaCreate() {
    return (
        <>
            <Head title="Nueva persona de apoyo" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading title="Nueva persona de apoyo" />

                <Form
                    {...PersonaApoyaController.store.form()}
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

PersonaApoyaCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Personas de apoyo', href: personasApoya.index() },
        { title: 'Nueva persona', href: personasApoya.create() },
    ],
};
