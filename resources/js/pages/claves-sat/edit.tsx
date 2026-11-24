import { Form, Head } from '@inertiajs/react';
import ClaveSatController from '@/actions/App/Http/Controllers/ClaveSatController';
import { ClaveSatFields } from '@/components/clave-sat-fields';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import type { ClaveSat } from '@/lib/claves-sat';
import { dashboard } from '@/routes';
import clavesSat from '@/routes/claves-sat';

export default function ClaveSatEdit({ clave }: { clave: ClaveSat }) {
    return (
        <>
            <Head title={`Editar clave ${clave.clave}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    title={`Editar clave ${clave.clave}`}
                    description={clave.concepto}
                />

                <Form
                    {...ClaveSatController.update.form(clave)}
                    className="max-w-xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <ClaveSatFields clave={clave} errors={errors} />
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

ClaveSatEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Claves SAT', href: clavesSat.index() },
        { title: 'Editar', href: '#' },
    ],
};
