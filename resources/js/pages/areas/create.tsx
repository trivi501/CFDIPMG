import { Form, Head } from '@inertiajs/react';
import AreaController from '@/actions/App/Http/Controllers/AreaController';
import { AreaFormFields } from '@/components/area-form-fields';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import areas from '@/routes/areas';

export default function AreaCreate() {
    return (
        <>
            <Head title="Nueva área" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading title="Nueva área" />

                <Form
                    {...AreaController.store.form()}
                    className="max-w-md space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <AreaFormFields errors={errors} />
                            <Button disabled={processing}>Guardar</Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

AreaCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Áreas', href: areas.index() },
        { title: 'Nueva área', href: areas.create() },
    ],
};
