import { Form, Head } from '@inertiajs/react';
import ClaveSatController from '@/actions/App/Http/Controllers/ClaveSatController';
import { ClaveSatFields } from '@/components/clave-sat-fields';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import clavesSat from '@/routes/claves-sat';

export default function ClaveSatCreate() {
    return (
        <>
            <Head title="Nueva clave SAT" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    title="Nueva clave SAT"
                    description="Al escribir esta clave en una factura se llenan solos su unidad y su concepto."
                />

                <Form
                    {...ClaveSatController.store.form()}
                    className="max-w-xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <ClaveSatFields errors={errors} />
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

ClaveSatCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Claves SAT', href: clavesSat.index() },
        { title: 'Nueva clave', href: clavesSat.create() },
    ],
};
