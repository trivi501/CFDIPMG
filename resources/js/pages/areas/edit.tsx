import { Form, Head } from '@inertiajs/react';
import AreaController from '@/actions/App/Http/Controllers/AreaController';
import { AreaFormFields } from '@/components/area-form-fields';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import type { AreaOpcion } from '@/lib/areas';
import { dashboard } from '@/routes';
import areas from '@/routes/areas';

export default function AreaEdit({ area }: { area: AreaOpcion }) {
    return (
        <>
            <Head title="Editar área" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading title="Editar área" description={area.nombre} />

                <Form
                    {...AreaController.update.form(area)}
                    className="max-w-md space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <AreaFormFields
                                errors={errors}
                                nombre={area.nombre}
                                tipo={area.tipo}
                            />
                            <Button disabled={processing}>
                                Guardar cambios
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

AreaEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Áreas', href: areas.index() },
        { title: 'Editar', href: '#' },
    ],
};
